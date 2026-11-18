<?php

namespace App\Http\Controllers\Guest;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Services\Geofence\LocationCheck;
use App\Services\GuestDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;

/**
 * POST /e/{slug}/location?t=... — conferência "envio só no local do evento".
 *
 * Corpo: latitude, longitude, accuracy (m), timestamp (ms, Position.timestamp).
 * 200 {ok:true, expires_in} => passe de local na sessão (aparelho + evento, 30 min).
 * 422 {ok:false, code: outside|inaccurate|stale|invalid, distance_m?, message}.
 * Evento sem geofence em vigor (ou conta sem o recurso) => 200 {ok:true, required:false}.
 *
 * LGPD/GDPR: as coordenadas só alimentam o cálculo; nunca vão para banco, log ou sessão.
 * Nenhum Log:: aqui, e o validador não ecoa os valores nas mensagens.
 */
class GuestLocationController extends Controller
{
    use ResolvesGuestEvent;

    public function __construct(
        private readonly GuestDevice $device,
        private readonly LocationCheck $check,
    ) {
    }

    public function store(Request $request, string $slug): JsonResponse
    {
        $event = $this->resolveGuestEvent($request, $slug);

        if (in_array($event->language, config('photoparty.locales'), true)) {
            App::setLocale($event->language);
        }

        if ($event->computeStatus() !== EventStatus::Live) {
            return response()->json(['ok' => false, 'code' => 'ended', 'message' => __('upload.errors.event_not_live')], 410);
        }

        if (! $event->geofenceActive()) {
            return response()->json(['ok' => true, 'required' => false]);
        }

        $validator = Validator::make($request->only(['latitude', 'longitude', 'accuracy', 'timestamp']), [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'timestamp' => ['required', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'code' => 'invalid', 'message' => __('guest.geo_error')], 422);
        }

        $data = $validator->validated();
        $deviceHash = $this->device->hash($request);

        $verdict = $this->check->evaluate(
            $event,
            (float) $data['latitude'],
            (float) $data['longitude'],
            (float) $data['accuracy'],
            (int) $data['timestamp'],
        );

        if ($verdict['result'] === LocationCheck::OK) {
            $ttl = $this->check->grantPass($request->session(), $event, $deviceHash);

            return response()->json(['ok' => true, 'required' => true, 'expires_in' => $ttl]);
        }

        $body = ['ok' => false, 'code' => $verdict['result']];

        if ($verdict['result'] === LocationCheck::OUTSIDE) {
            $body['distance_m'] = $verdict['distance_m'];
            $body['message'] = __('guest.geo_outside', ['distance' => LocationCheck::formatDistance($verdict['distance_m'], App::getLocale())]);
        } else {
            $body['message'] = __('guest.geo_'.$verdict['result']);
        }

        return response()->json($body, 422);
    }
}
