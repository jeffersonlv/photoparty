<?php

namespace App\Jobs;

use App\Http\Controllers\Gallery\GalleryController;
use App\Models\Account;
use App\Models\Event;
use App\Models\Export;
use App\Models\Photo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

/**
 * Monta o ZIP das fotos aprovadas (originais, sem recompressão: CM_STORE) em
 * storage/app/private/tenants/{account}/events/{event}/exports/{uuid}.zip.
 * Recebe só o id do export; account/evento vêm do registro, nunca de parâmetro externo.
 */
class BuildEventExport implements ShouldQueue
{
    use Queueable;

    public const EXPIRES_DAYS = 7;

    /** Reabre o ZIP a cada N arquivos para não estourar o limite de descritores abertos. */
    private const FLUSH_EVERY = 200;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly int $exportId)
    {
    }

    public function handle(): void
    {
        $export = Export::withoutGlobalScopes()->find($this->exportId);

        if ($export === null || $export->status !== 'pending') {
            return;
        }

        $event = Event::withoutGlobalScopes()->find($export->event_id);
        $account = Account::query()->find($export->account_id);

        if ($event === null || $account === null || $event->account_id !== $account->id || ! $account->canDownloadPhotos()) {
            $this->fail($export);

            return;
        }

        $export->forceFill(['status' => 'processing'])->save();

        $relative = $account->storagePath().'/events/'.$event->uuid.'/exports/'.$export->uuid.'.zip';
        $disk = Storage::disk('local');
        $disk->makeDirectory(dirname($relative));
        $target = $disk->path($relative);

        $added = 0;
        $zip = new ZipArchive();

        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->fail($export);

            return;
        }

        $number = 0;
        $pending = 0;

        $photos = Photo::withoutGlobalScopes()
            ->where('event_id', $event->id)
            ->where('account_id', $account->id)
            ->where('status', 'approved')
            ->orderBy('approved_at')
            ->orderBy('id');

        foreach ($photos->lazyById(100) as $photo) {
            $number++;
            $path = $photo->original_path;

            if ($path === null || ! str_starts_with($path, $account->storagePath().'/') || str_contains($path, '..') || ! $disk->exists($path)) {
                continue;
            }

            $name = GalleryController::fileName($event, $number);
            $zip->addFile($disk->path($path), $name);
            $zip->setCompressionName($name, ZipArchive::CM_STORE);
            $added++;

            if (++$pending >= self::FLUSH_EVERY) {
                $zip->close();
                $zip->open($target);
                $pending = 0;
            }
        }

        $closed = $zip->close();

        if (! $closed || $added === 0 || ! is_file($target)) {
            @unlink($target);
            $this->fail($export);

            return;
        }

        $export->forceFill([
            'status' => 'ready',
            'path' => $relative,
            'bytes' => filesize($target),
            'expires_at' => now()->addDays(self::EXPIRES_DAYS),
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        $export = Export::withoutGlobalScopes()->find($this->exportId);

        if ($export !== null && in_array($export->status, ['pending', 'processing'], true)) {
            $this->fail($export);
        }
    }

    private function fail(Export $export): void
    {
        $export->forceFill(['status' => 'failed', 'path' => null])->save();
    }
}
