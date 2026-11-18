<?php

namespace App\Http\Controllers\Guest;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Frame;
use App\Models\Photo;
use App\Services\Geofence\LocationCheck;
use App\Services\GuestDevice;
use App\Services\Photos\GuestPhotoUploader;
use App\Services\Photos\ImageSanitizer;
use App\Services\Photos\UploadRejected;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Envio e remoção de fotos pelo convidado (SPEC 9, 13.5).
 *
 * POST   /e/{slug}/photos?t=...           => 201 {uuid, status}
 * DELETE /e/{slug}/photos/{photo}?t=...   => 204 (a própria foto, em qualquer status, enquanto o evento está ao vivo)
 *
 * Consentimento (LGPD art. 7º I / GDPR art. 6(1)(a)): o envio exige consent=1 e
 * consent_version igual à versão vigente (config photoparty.privacy.consent_version); sem isso => 422.
 */
class GuestPhotoController extends Controller
{
    use ResolvesGuestEvent;

    /** Chaves aceitas: filtros do front (effects.js) + lista do config. */
    public const FILTERS = ['none', 'bw', 'vintage', 'sepia', 'vivid', 'warm', 'cool'];

    public function __construct(
        private readonly GuestDevice $device,
        private readonly ImageSanitizer $sanitizer,
        private readonly GuestPhotoUploader $uploader,
        private readonly LocationCheck $location,
    ) {
    }

    public function store(Request $request, string $slug): JsonResponse
    {
        $event = $this->resolveGuestEvent($request, $slug);
        $this->useEventLocale($event);

        if ($event->computeStatus() !== EventStatus::Live) {
            throw UploadRejected::ended();
        }

        $deviceHash = $this->device->hash($request);

        // "Envio só no local do evento": exige o passe de local (sessão, aparelho e evento, 30 min).
        // Conta que perdeu o recurso => geofenceActive() false e nada muda.
        $locationVerified = false;

        if ($event->geofenceActive()) {
            if (! $this->location->hasValidPass($request->session(), $event, $deviceHash)) {
                throw UploadRejected::locationRequired();
            }

            $locationVerified = true;
        }

        [$frame, $data] = $this->validated($request, $event);
        $data['location_verified'] = $locationVerified;

        try {
            $photo = $this->uploader->store($event, $frame, $request->file('photo'), $request->file('thumb'), $data, $deviceHash);
        } catch (\RuntimeException $e) {
            if ($e instanceof UploadRejected) {
                throw $e;
            }

            throw ValidationException::withMessages(['photo' => __('upload.errors.not_image')]);
        }

        return response()->json([
            'uuid' => $photo->uuid,
            'status' => $photo->status,
        ], 201);
    }

    public function destroy(Request $request, string $slug, string $photo): JsonResponse
    {
        $event = $this->resolveGuestEvent($request, $slug);
        $this->useEventLocale($event);

        $deviceHash = GuestDevice::limiterKey($request);

        // Decisão do produto: "Minhas fotos" (ver, baixar, excluir) vale enquanto o evento está ao vivo.
        abort_unless($event->computeStatus() === EventStatus::Live, 404);

        $model = $deviceHash === null ? null : Photo::withoutGlobalScopes()
            ->where('event_id', $event->id)
            ->where('uuid', strtolower($photo))
            ->where('device_hash', $deviceHash)
            ->where('status', '!=', Photo::STATUS_DELETED)
            ->first();

        // Foto de outro aparelho/evento: 404 (não revela que existe).
        abort_if($model === null, 404);

        $this->uploader->delete($model);

        return response()->json(null, 204);
    }

    /**
     * @return array{0: ?Frame, 1: array{orientation:string, filter:?string, guest_name:?string, guest_texts:array<string,string>, consent_version:string}}
     */
    private function validated(Request $request, Event $event): array
    {
        $input = Validator::make($request->all(), [
            // max em KB: foto ≤ 3 MB, miniatura ≤ 300 KB.
            'photo' => ['required', 'file', 'max:3072'],
            'thumb' => ['required', 'file', 'max:300'],
            'orientation' => ['required', 'string', 'in:portrait,landscape'],
            'frame_key' => ['nullable', 'string', 'max:64'],
            'filter' => ['nullable', 'string', 'in:'.implode(',', self::FILTERS)],
            'guest_name' => ['nullable', 'string', 'max:60', 'not_regex:/[<>]/'],
            'guest_texts' => ['nullable', 'string', 'max:4000'],
            'consent' => ['required', 'accepted'],
            'consent_version' => ['required', 'string', 'in:'.config('photoparty.privacy.consent_version')],
        ], [
            'consent.required' => __('upload.errors.consent'),
            'consent.accepted' => __('upload.errors.consent'),
            'consent_version.required' => __('upload.errors.consent_version'),
            'consent_version.in' => __('upload.errors.consent_version'),
        ], [
            'photo' => __('upload.attributes.photo'),
            'thumb' => __('upload.attributes.thumb'),
            'guest_name' => __('upload.attributes.guest_name'),
        ])->validate();

        $orientation = $input['orientation'];
        $errors = [];

        /** @var UploadedFile $photo */
        $photo = $request->file('photo');
        /** @var UploadedFile $thumb */
        $thumb = $request->file('thumb');

        if ($key = $this->sanitizer->photoError($photo, $orientation)) {
            $errors['photo'] = __($key);
        }

        if ($key = $this->sanitizer->thumbError($thumb, $orientation)) {
            $errors['thumb'] = __($key);
        }

        // Moldura: vazia ou uma das ativas no evento.
        $frame = null;
        $frameKey = trim((string) ($input['frame_key'] ?? ''));

        if ($frameKey !== '') {
            $frame = $event->activeFrames()->first(fn (Frame $f) => $f->publicKey() === $frameKey);

            if ($frame === null) {
                $errors['frame_key'] = __('upload.errors.frame');
            }
        }

        $texts = $this->guestTexts($input['guest_texts'] ?? null, $frame, $orientation, $errors);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $guestName = trim((string) ($input['guest_name'] ?? ''));

        return [$frame, [
            'orientation' => $orientation,
            'filter' => $input['filter'] ?? 'none',
            'guest_name' => $guestName === '' ? null : $guestName,
            'guest_texts' => $texts,
            'consent_version' => (string) $input['consent_version'],
        ]];
    }

    /**
     * Textos do convidado: JSON objeto {id: texto}; só ids "guest" da moldura/orientação,
     * até maxChars do config, sem HTML. Valores vazios são descartados.
     *
     * @param  array<string, string>  $errors
     * @return array<string, string>
     */
    private function guestTexts(?string $raw, ?Frame $frame, string $orientation, array &$errors): array
    {
        if ($raw === null || trim($raw) === '' || trim($raw) === '{}' || trim($raw) === '[]') {
            return [];
        }

        $decoded = json_decode($raw, true, 4);

        if (! is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            $errors['guest_texts'] = __('upload.errors.texts_format');

            return [];
        }

        $limits = $frame?->guestTextLimits($orientation) ?? [];
        $texts = [];

        foreach ($decoded as $id => $value) {
            if (! is_string($value)) {
                $errors['guest_texts'] = __('upload.errors.texts_format');

                return [];
            }

            $value = trim($value);

            if ($value === '') {
                continue;
            }

            if (! array_key_exists((string) $id, $limits)) {
                $errors['guest_texts'] = __('upload.errors.texts_unknown');

                return [];
            }

            if (mb_strlen($value) > $limits[$id]) {
                $errors['guest_texts'] = __('upload.errors.texts_length', ['max' => $limits[$id]]);

                return [];
            }

            if (preg_match('/[<>]/', $value) || strip_tags($value) !== $value || preg_match('/[\x00-\x1F\x7F]/u', $value)) {
                $errors['guest_texts'] = __('upload.errors.texts_html');

                return [];
            }

            $texts[(string) $id] = $value;
        }

        return $texts;
    }

    private function useEventLocale(Event $event): void
    {
        if (in_array($event->language, config('photoparty.locales'), true)) {
            App::setLocale($event->language);
        }
    }
}
