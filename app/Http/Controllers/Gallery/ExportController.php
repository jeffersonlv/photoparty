<?php

namespace App\Http\Controllers\Gallery;

use App\Http\Controllers\Controller;
use App\Jobs\BuildEventExport;
use App\Models\Account;
use App\Models\Event;
use App\Models\Export;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * ZIP do evento: cria o registro em exports e despacha o job (fila database, cron).
 * Só conta com plano pago; um ZIP em andamento por evento.
 */
class ExportController extends Controller
{
    /** Registro "pending/processing" parado há mais que isso é considerado travado. */
    public const STALE_MINUTES = 30;

    public static function inProgress(Event $event): bool
    {
        return $event->exports()
            ->whereIn('status', ['pending', 'processing'])
            ->where('updated_at', '>', now()->subMinutes(self::STALE_MINUTES))
            ->exists();
    }

    public function store(Event $event): RedirectResponse
    {
        $this->authorize('view', $event);

        $account = $this->accountOf($event);

        abort_unless($account->canDownloadPhotos(), 404);

        if ($event->photos()->where('status', 'approved')->doesntExist()) {
            return redirect()->route('events.gallery', $event)->with('error', __('gallery.export_empty'));
        }

        $export = DB::transaction(function () use ($event) {
            // Trava a linha do evento para não enfileirar dois ZIPs ao mesmo tempo.
            Event::query()->whereKey($event->getKey())->lockForUpdate()->first();

            if (self::inProgress($event)) {
                return null;
            }

            $export = new Export();
            $export->account_id = $event->account_id;
            $export->event_id = $event->id;
            $export->status = 'pending';
            $export->save();

            return $export;
        });

        if ($export === null) {
            return redirect()->route('events.gallery', $event)->with('status', __('gallery.export_in_progress'));
        }

        BuildEventExport::dispatch($export->id);

        return redirect()->route('events.gallery', $event)->with('status', __('gallery.export_queued'));
    }

    public function download(Event $event, Export $export): BinaryFileResponse
    {
        $this->authorize('view', $event);

        $account = $this->accountOf($event);

        abort_unless($account->canDownloadPhotos(), 404);

        $path = $export->path;
        $disk = Storage::disk('local');

        abort_if(
            $export->status !== 'ready'
            || ($export->expires_at !== null && $export->expires_at->isPast())
            || $path === null
            || ! str_starts_with($path, $account->storagePath().'/events/'.$event->uuid.'/exports/')
            || str_contains($path, '..')
            || ! $disk->exists($path),
            404,
        );

        return response()->download($disk->path($path), $event->slug.'-fotos.zip', [
            'Content-Type' => 'application/zip',
            'X-Content-Type-Options' => 'nosniff',
        ])->setPrivate();
    }

    private function accountOf(Event $event): Account
    {
        $account = Account::query()->find($event->account_id);

        abort_if($account === null, 404);

        return $account;
    }
}
