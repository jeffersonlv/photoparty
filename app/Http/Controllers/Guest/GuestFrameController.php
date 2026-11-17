<?php

namespace App\Http\Controllers\Guest;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Frames\FrameLibraryController;
use App\Models\Frame;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Config e PNG das molduras personalizadas para a página do convidado (módulo 8).
 *
 * GET /e/{slug}/frames/{frame}/{config.json|portrait.png|landscape.png}?t={upload_token}
 *
 * Mesma autorização do upload: evento localizado por slug + hash do token (errado => 404),
 * evento no ar, e a moldura tem de estar ativa NESTE evento (Event::activeFrames(): mesma
 * conta, plano com molduras personalizadas, não desativada). Qualquer outra => 404.
 * O motor JS resolve "portrait.png" relativo ao config e repassa o ?t= (resolveAssetUrl).
 */
class GuestFrameController extends Controller
{
    use ResolvesGuestEvent;

    public function show(Request $request, string $slug, string $frame, string $file): Response|JsonResponse
    {
        $event = $this->resolveGuestEvent($request, $slug);

        abort_unless($event->computeStatus() === EventStatus::Live, 404);

        $model = $event->activeFrames()->first(
            fn (Frame $f) => $f->isCustom() && hash_equals($f->uuid, strtolower($frame)),
        );

        abort_if($model === null, 404);

        if ($file === 'config.json') {
            return response()->json($model->config, 200, [
                'Cache-Control' => 'private, max-age=600',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return FrameLibraryController::servePng($model, basename($file, '.png'));
    }
}
