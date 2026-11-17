<?php

namespace App\Http\Controllers\Gallery;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Event;
use App\Models\Photo;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Galeria do evento (fotos aprovadas) e download individual.
 *
 * Quem vê: qualquer usuário da conta (EventPolicy::view; outra conta => 404). Quem baixa:
 * só conta com plano pago (Account::canDownloadPhotos); as demais recebem 404 no download.
 */
class GalleryController extends Controller
{
    public const PER_PAGE = 24;

    public function index(Event $event): Response
    {
        $this->authorize('view', $event);

        $account = $this->accountOf($event);

        $photos = $event->photos()
            ->where('status', 'approved')
            ->orderBy('approved_at')
            ->orderBy('id')
            ->paginate(self::PER_PAGE);

        $canDownload = $account->canDownloadPhotos();

        return response()->view('gallery.index', [
            'event' => $event,
            'photos' => $photos,
            'canDownload' => $canDownload,
            'approvedCount' => $event->photos()->where('status', 'approved')->count(),
            'export' => $canDownload ? $event->exports()->latest('id')->first() : null,
            'exportInProgress' => $canDownload && ExportController::inProgress($event),
            'firstNumber' => ($photos->currentPage() - 1) * self::PER_PAGE + 1,
        ]);
    }

    public function download(Event $event, Photo $photo): BinaryFileResponse
    {
        $this->authorize('view', $event);

        $account = $this->accountOf($event);

        abort_unless($account->canDownloadPhotos() && $photo->isApproved(), 404);

        $path = $photo->original_path;
        $disk = Storage::disk('local');

        abort_if(
            $path === null
            || ! str_starts_with($path, $account->storagePath().'/')
            || str_contains($path, '..')
            || ! $disk->exists($path),
            404,
        );

        $response = response()->download($disk->path($path), self::fileName($event, $this->position($event, $photo)), [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        return $response->setPrivate();
    }

    /**
     * Nome amigável: "{slug}-0001.jpg" (número = posição entre as aprovadas).
     */
    public static function fileName(Event $event, int $number): string
    {
        return $event->slug.'-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT).'.jpg';
    }

    private function position(Event $event, Photo $photo): int
    {
        // Valor cru do banco (com microssegundos): um Carbon seria truncado ao segundo no binding.
        $approvedAt = $photo->getRawOriginal('approved_at');

        return $event->photos()
            ->where('status', 'approved')
            ->where(fn ($q) => $q->where('approved_at', '<', $approvedAt)
                ->orWhere(fn ($q) => $q->where('approved_at', $approvedAt)->where('id', '<=', $photo->id)))
            ->count();
    }

    private function accountOf(Event $event): Account
    {
        $account = Account::query()->find($event->account_id);

        abort_if($account === null, 404);

        return $account;
    }
}
