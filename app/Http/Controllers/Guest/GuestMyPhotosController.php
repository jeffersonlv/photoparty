<?php

namespace App\Http\Controllers\Guest;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Event;
use App\Models\Photo;
use App\Services\GuestDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * "Minhas fotos" do convidado (direitos de acesso e portabilidade, LGPD art. 18 II e V /
 * GDPR arts. 15 e 20): só as fotos que ESTE aparelho enviou NESTE evento.
 *
 * GET /e/{slug}/my-photos?t=...                        => JSON da lista
 * GET /e/{slug}/my-photos/{photo}/thumb?t=...          => miniatura (inline)
 * GET /e/{slug}/my-photos/{photo}/download?t=...       => original (attachment)
 *
 * Autorização: evento por slug + hash do token de upload (errado => 404) E device_hash do
 * cookie anônimo do aparelho. Foto de outro aparelho/evento/conta => 404. Evento fora do
 * status live => 404 (decisão do produto: disponível enquanto o evento está ativo).
 *
 * Regra de plano: a foto é do próprio convidado; o download dele é sempre o original, sem
 * faixa de demonstração, mesmo se a conta do organizador estiver em teste (README).
 */
class GuestMyPhotosController extends Controller
{
    use ResolvesGuestEvent;

    public const LIST_LIMIT = 100;

    public function index(Request $request, string $slug): JsonResponse
    {
        $event = $this->liveEvent($request, $slug);
        $device = GuestDevice::limiterKey($request);
        $token = $this->guestToken($request);

        $photos = $device === null ? collect() : Photo::withoutGlobalScopes()
            ->where('event_id', $event->id)
            ->where('device_hash', $device)
            ->whereIn('status', ['pending', 'approved', 'rejected'])
            ->latest('id')
            ->limit(self::LIST_LIMIT)
            ->get(['id', 'uuid', 'status', 'orientation', 'created_at']);

        $params = fn (Photo $photo) => ['slug' => $event->slug, 'photo' => $photo->uuid, 't' => $token];

        return response()->json([
            'photos' => $photos->map(fn (Photo $photo) => [
                'uuid' => $photo->uuid,
                'status' => $photo->status,
                'orientation' => $photo->orientation,
                'createdAt' => $photo->created_at?->toIso8601String(),
                'thumbUrl' => route('guest.mine.thumb', $params($photo), false),
                'downloadUrl' => route('guest.mine.download', $params($photo), false),
                'deleteUrl' => route('guest.photos.destroy', $params($photo), false),
            ])->values()->all(),
        ], 200, ['Cache-Control' => 'no-store, private']);
    }

    public function thumb(Request $request, string $slug, string $photo): BinaryFileResponse
    {
        $model = $this->ownPhoto($request, $slug, $photo);

        return $this->serve($model, $model->thumb_path, 'inline');
    }

    public function download(Request $request, string $slug, string $photo): BinaryFileResponse
    {
        $model = $this->ownPhoto($request, $slug, $photo);

        return $this->serve($model, $model->original_path, 'attachment');
    }

    private function liveEvent(Request $request, string $slug): Event
    {
        $event = $this->resolveGuestEvent($request, $slug);

        abort_unless($event->computeStatus() === EventStatus::Live, 404);

        return $event;
    }

    private function ownPhoto(Request $request, string $slug, string $photo): Photo
    {
        $event = $this->liveEvent($request, $slug);
        $device = GuestDevice::limiterKey($request);

        abort_if($device === null, 404);

        return Photo::withoutGlobalScopes()
            ->where('event_id', $event->id)
            ->where('account_id', $event->account_id)
            ->where('uuid', strtolower($photo))
            ->where('device_hash', $device)
            ->whereIn('status', ['pending', 'approved', 'rejected'])
            ->firstOr(fn () => abort(404));
    }

    private function serve(Photo $photo, ?string $path, string $disposition): BinaryFileResponse
    {
        $account = Account::query()->find($photo->account_id);
        $disk = Storage::disk('local');

        // Defesa extra: o caminho tem de estar dentro da pasta do tenant.
        abort_if(
            $account === null
            || $path === null
            || ! str_starts_with($path, $account->storagePath().'/')
            || str_contains($path, '..')
            || ! $disk->exists($path),
            404,
        );

        $response = new BinaryFileResponse($disk->path($path), 200, [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
        ], false);

        $response->setContentDisposition($disposition, 'photo-party-'.substr($photo->uuid, 0, 8).'.jpg');

        // Privado: nunca em cache compartilhado; o download não fica em cache.
        $response->setPrivate();
        $disposition === 'attachment'
            ? $response->headers->set('Cache-Control', 'private, no-store')
            : $response->setMaxAge(300);

        return $response;
    }
}
