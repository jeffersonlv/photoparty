<?php

namespace App\Services\Frames;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Frame;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Cria, ativa por evento e exclui molduras personalizadas (módulo 8).
 * account_id vem sempre da conta autenticada (parâmetro), nunca do request.
 */
class CustomFrameManager
{
    /** sort_order das molduras custom no evento: depois dos 4 modelos prontos. */
    public const EVENT_SORT_ORDER = 100;

    public function __construct(private readonly CustomFrameImage $images) {}

    /**
     * @param  array<string, UploadedFile|null>  $files  ['portrait' => ..., 'landscape' => ...]
     *
     * @throws ValidationException
     */
    public function create(Account $account, string $name, array $files): Frame
    {
        $processed = [];

        foreach (Frame::ORIENTATIONS as $orientation) {
            $file = $files[$orientation] ?? null;

            if (! $file instanceof UploadedFile) {
                continue;
            }

            try {
                $processed[$orientation] = $this->images->process($file, $orientation);
            } catch (InvalidFrameImage $e) {
                [$w, $h] = CustomFrameImage::SIZES[$orientation];

                throw ValidationException::withMessages([
                    $orientation => __('frames.errors.'.$e->reason(), ['width' => $w, 'height' => $h]),
                ]);
            }
        }

        if ($processed === []) {
            throw ValidationException::withMessages(['portrait' => __('frames.errors.at_least_one')]);
        }

        $uuid = (string) Str::uuid();
        $dir = $account->storagePath().'/frames/'.$uuid;
        $disk = Storage::disk('local');

        $config = [
            'version' => 1,
            'key' => $uuid,
            'name' => ['pt' => $name, 'en' => $name],
            'orientations' => [],
        ];

        foreach ($processed as $orientation => $result) {
            [$w, $h] = CustomFrameImage::SIZES[$orientation];
            $config['orientations'][$orientation] = [
                'width' => $w,
                'height' => $h,
                'background' => null,
                'window' => $result['window'],
                'overlay' => $orientation.'.png',
                'texts' => [],
                'effect' => null,
            ];
        }

        try {
            return DB::transaction(function () use ($account, $name, $uuid, $dir, $disk, $config, $processed) {
                // Trava a conta: envios simultâneos não furam o limite de 20.
                Account::query()->whereKey($account->id)->lockForUpdate()->first();

                $count = Frame::withoutGlobalScopes()
                    ->where('account_id', $account->id)
                    ->where('type', Frame::TYPE_CUSTOM)
                    ->count();

                if ($count >= Frame::MAX_CUSTOM_PER_ACCOUNT) {
                    throw ValidationException::withMessages([
                        'name' => __('frames.errors.limit', ['max' => Frame::MAX_CUSTOM_PER_ACCOUNT]),
                    ]);
                }

                foreach ($processed as $orientation => $result) {
                    if (! $disk->put("{$dir}/{$orientation}.png", $result['png'])) {
                        throw new \RuntimeException('Falha ao gravar a moldura.');
                    }
                }

                $disk->put("{$dir}/config.json", json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                $frame = new Frame(['name' => $name, 'type' => Frame::TYPE_CUSTOM, 'config' => $config]);
                $frame->forceFill([
                    'uuid' => $uuid,
                    'account_id' => $account->id,
                    'template_key' => null,
                    'portrait_path' => isset($processed['portrait']) ? "{$dir}/portrait.png" : null,
                    'landscape_path' => isset($processed['landscape']) ? "{$dir}/landscape.png" : null,
                    'is_active' => true,
                    'sort_order' => 0,
                ])->save();

                AuditLog::record('frame.uploaded', $frame, [
                    'name' => $name,
                    'orientations' => array_keys($processed),
                ]);

                return $frame;
            });
        } catch (Throwable $e) {
            $disk->deleteDirectory($dir);

            throw $e;
        }
    }

    /**
     * Ativa a moldura nos eventos escolhidos e desativa nos demais candidatos.
     * Eventos são buscados pela conta da moldura: uuid de outra conta é ignorado.
     *
     * @param  list<string>  $eventUuids
     * @param  \Illuminate\Support\Collection<int, Event>  $candidates
     */
    public function syncEvents(Frame $frame, \Illuminate\Support\Collection $candidates, array $eventUuids): void
    {
        $selected = $candidates->whereIn('uuid', $eventUuids)->pluck('id')->all();
        $unselected = $candidates->whereNotIn('uuid', $eventUuids)->pluck('id')->all();

        DB::transaction(function () use ($frame, $selected, $unselected) {
            if ($unselected !== []) {
                $frame->events()->detach($unselected);
            }

            $frame->events()->syncWithoutDetaching(
                collect($selected)->mapWithKeys(fn (int $id) => [$id => ['sort_order' => self::EVENT_SORT_ORDER]])->all(),
            );
        });

        AuditLog::record('frame.events_updated', $frame, ['events' => count($selected)]);
    }

    public function delete(Frame $frame): void
    {
        $dir = $frame->storageDirectory();

        DB::transaction(function () use ($frame) {
            AuditLog::record('frame.deleted', $frame, ['name' => $frame->name]);
            $frame->delete();
        });

        if ($dir !== null && str_contains($dir, '/frames/') && ! str_contains($dir, '..')) {
            Storage::disk('local')->deleteDirectory($dir);
        }
    }
}
