<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Services\Demo\DemoEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint público mínimo do mini-telão da home: GET /demo/photos.
 *
 * Só o evento de demonstração do sistema (events.is_demo) e só fotos aprovadas dele enviadas pelo
 * QR do próprio visitante (demo_pair = hash do código da sessão); nunca de outra conta. O request não escolhe nada: o pareamento vem da sessão. Miniaturas com URL assinada de 10 min.
 * Com o toggle do admin desligado (ou sem evento de demo) => 404.
 */
class DemoController extends Controller
{
    public const URL_TTL_MINUTES = 10;

    public function photos(Request $request, DemoEvent $demo): JsonResponse
    {
        $event = DemoEvent::enabled() ? $demo->event() : null;

        abort_if($event === null || ! $event->isLive(), 404);

        // Só as fotos enviadas pelo QR deste visitante; sem código válido na sessão, lista vazia.
        $pair = $demo->sessionPairHash($request->session());
        $photos = $pair === null ? collect() : $demo->recentPhotos($event, $pair);
        $photos = $photos->map(fn ($photo) => [
            'uuid' => $photo->uuid,
            'url' => $photo->signedMediaUrl('thumb', self::URL_TTL_MINUTES),
            'orientation' => $photo->orientation,
        ])->values()->all();

        return response()->json([
            'photos' => $photos,
            'ttl' => self::URL_TTL_MINUTES * 60,
        ])->header('Cache-Control', 'no-store, private');
    }
}
