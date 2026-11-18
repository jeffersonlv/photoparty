<?php

namespace App\Services\Photos;

use App\Enums\EventStatus;
use App\Models\Account;
use App\Models\Event;
use App\Models\Frame;
use App\Models\Photo;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Grava a foto do convidado: arquivos + registro numa transação (OWASP A10).
 *
 * account_id/event_id saem do evento resolvido pelo token, nunca do request.
 * Limites (evento e por convidado) são conferidos dentro da transação, com o
 * evento travado (lockForUpdate) para dois envios simultâneos não furarem o teto.
 */
class GuestPhotoUploader
{
    /** Espaço livre mínimo no disco para aceitar upload. */
    public const MIN_FREE_BYTES = 50 * 1024 * 1024;

    public function __construct(private readonly ImageSanitizer $sanitizer)
    {
    }

    /**
     * @param  array{orientation:string, filter:?string, guest_name:?string, guest_texts:array<string,string>, consent_version:string, location_verified?:bool}  $data
     */
    public function store(
        Event $event,
        ?Frame $frame,
        UploadedFile $photoFile,
        UploadedFile $thumbFile,
        array $data,
        string $deviceHash,
    ): Photo {
        // Reencode antes da transação (CPU fora do lock). Falha de decode = arquivo inválido.
        $original = $this->sanitizer->toJpeg($photoFile, ImageSanitizer::PHOTO_QUALITY);
        $thumb = $this->sanitizer->toJpeg($thumbFile, ImageSanitizer::THUMB_QUALITY);

        $disk = Storage::disk('local');
        $this->ensureFreeSpace($disk, strlen($original) + strlen($thumb));

        $account = Account::query()->findOrFail($event->account_id);
        $uuid = (string) Str::uuid();
        $base = $account->storagePath().'/events/'.$event->uuid;
        $paths = [
            'original' => "{$base}/original/{$uuid}.jpg",
            'thumb' => "{$base}/thumb/{$uuid}.jpg",
        ];

        try {
            return DB::transaction(function () use ($event, $frame, $data, $deviceHash, $disk, $paths, $original, $thumb, $uuid) {
                $locked = Event::withoutGlobalScopes()->lockForUpdate()->findOrFail($event->id);

                if ($locked->computeStatus() !== EventStatus::Live) {
                    throw UploadRejected::ended();
                }

                $photos = Photo::withoutGlobalScopes()->where('event_id', $locked->id);

                // Fotos excluídas pelo próprio convidado não ocupam vaga do evento, mas continuam
                // contando no limite por aparelho (senão excluir/reenviar furaria o limite).
                if ((clone $photos)->where('status', '!=', Photo::STATUS_DELETED)->count() >= $locked->photoLimit()) {
                    throw UploadRejected::eventLimit();
                }

                if ($locked->max_photos_per_guest !== null
                    && (clone $photos)->where('device_hash', $deviceHash)->count() >= $locked->max_photos_per_guest) {
                    throw UploadRejected::guestLimit();
                }

                foreach (['original' => $original, 'thumb' => $thumb] as $variant => $bytes) {
                    if ($disk->put($paths[$variant], $bytes) !== true) {
                        throw UploadRejected::storage();
                    }
                }

                $approved = $locked->moderation === 'post';

                $photo = new Photo([
                    'filter' => $data['filter'],
                    'orientation' => $data['orientation'],
                    'guest_name' => $data['guest_name'],
                    'guest_texts' => $data['guest_texts'] ?: null,
                ]);
                $photo->uuid = $uuid;
                $photo->account_id = $locked->account_id;
                $photo->event_id = $locked->id;
                $photo->frame_id = $frame?->id;
                $photo->device_hash = $deviceHash;
                // Prova do consentimento (LGPD art. 8º §2º / GDPR art. 7(1)).
                $photo->consent_version = $data['consent_version'];
                $photo->consented_at = now();
                // Só o resultado da conferência de local; coordenadas nunca são gravadas.
                $photo->location_verified = (bool) ($data['location_verified'] ?? false);
                $photo->original_path = $paths['original'];
                $photo->thumb_path = $paths['thumb'];
                $photo->bytes = strlen($original) + strlen($thumb);
                $photo->status = $approved ? 'approved' : 'pending';
                $photo->approved_at = $approved ? now() : null;

                // Sem eventos de model: o hook de criação da BelongsToAccount recusaria gravar
                // se o convidado estiver logado como organizador de outra conta. O tenant
                // aqui vem do evento resolvido pelo token, já atribuído acima.
                Photo::withoutEvents(fn () => $photo->save());

                return $photo;
            });
        } catch (Throwable $e) {
            $disk->delete(array_values($paths));

            if ($e instanceof UploadRejected) {
                throw $e;
            }

            Log::error('Falha ao gravar foto do convidado', [
                'event' => $event->uuid,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw UploadRejected::storage();
        }
    }

    /**
     * Exclusão pelo próprio convidado (revogação do consentimento), em qualquer status.
     *
     * Apaga original, miniatura e prévia do disco. Foto que nunca foi ao telão (pendente ou
     * rejeitada) perde também o registro. Foto aprovada vira um registro mínimo com status
     * "deleted" (sem arquivos, nome, textos nem moldura) só para o telão retirá-la no próximo
     * polling; o privacy:enforce-retention apaga esse registro depois de algumas horas.
     */
    public function delete(Photo $photo): void
    {
        $paths = array_values(array_filter([$photo->original_path, $photo->thumb_path, $photo->previewPath()]));
        $tombstone = $photo->status === 'approved';

        DB::transaction(function () use ($photo, $tombstone) {
            $query = Photo::withoutGlobalScopes()->whereKey($photo->getKey());

            if (! $tombstone) {
                $query->delete();

                return;
            }

            $query->update([
                'status' => Photo::STATUS_DELETED,
                'guest_name' => null,
                'guest_texts' => null,
                'frame_id' => null,
                'original_path' => null,
                'thumb_path' => null,
                'bytes' => 0,
                'approved_at' => null,
                'updated_at' => now()->format('Y-m-d H:i:s.u'),
            ]);
        });

        Storage::disk('local')->delete($paths);
    }

    private function ensureFreeSpace(Filesystem $disk, int $needed): void
    {
        $root = method_exists($disk, 'path') ? $disk->path('') : null;
        $free = $root !== null && is_dir($root) ? @disk_free_space($root) : false;

        if ($free !== false && $free < $needed + self::MIN_FREE_BYTES) {
            Log::critical('Disco quase cheio: upload de foto recusado', ['free_bytes' => $free]);

            throw UploadRejected::storage();
        }
    }
}
