<?php

namespace App\Http\Controllers\Screen;

use App\Enums\AccountStatus;
use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Photo;
use App\Services\QrCodeGenerator;
use App\Services\ScreenSettings;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;

/**
 * Telão (SPEC 10): sem login, o evento é localizado pelo token (hash SHA-256).
 *
 * - GET /screen/{token}                página em tela cheia
 * - GET /screen/{token}/photos?after=  JSON com fotos aprovadas novas + uuids removidos
 *
 * Token inválido/revogado, evento draft ou expirado => 404. Nunca usa o global scope da
 * conta (o tenant vem do evento) e só lê fotos aprovadas daquele evento.
 */
class ScreenController extends Controller
{
    /** Fotos por chamada. */
    public const LIMIT = 50;

    /** Máximo de uuids removidos devolvidos por chamada. */
    public const REMOVED_LIMIT = 200;

    /** Validade das URLs assinadas (minutos). O telão renova antes de expirar. */
    public const URL_TTL_MINUTES = 10;

    public function show(string $token, QrCodeGenerator $qr): Response
    {
        $event = $this->resolveEvent($token);
        $status = $event->computeStatus();

        $this->abortUnlessVisible($status);

        if (in_array($event->language, config('photoparty.locales'), true)) {
            App::setLocale($event->language);
        }

        if ($status === EventStatus::Scheduled) {
            $view = response()->view('screen.status', [
                'title' => $event->name,
                'message' => __('screen.starts_at', [
                    'date' => $event->starts_at->locale(App::getLocale())->translatedFormat(__('upload.page.date_format')),
                ]),
            ]);

            return $this->harden($view);
        }

        $settings = ScreenSettings::for($event);
        $account = $event->account;
        $ended = $status === EventStatus::Ended;

        // QR do convidado como imagem data: gerada no servidor (CSP img-src permite data:).
        // Evento encerrado não aceita mais fotos: sem QR.
        $qrImage = null;

        if ($settings['show_qr'] && ! $ended) {
            $qrImage = 'data:image/svg+xml;base64,'.base64_encode($qr->render($event->guestUrl(), 'svg'));
        }

        return $this->harden(response()->view('screen.show', [
            'event' => $event,
            'qrImage' => $qrImage,
            'ended' => $ended,
            'trial' => $event->is_trial || $account?->isOnTrial(),
            // Faixa diagonal "DEMONSTRAÇÃO" para toda conta que não é active.
            'demo' => ! ($account?->canDownloadPhotos() ?? false),
            'config' => [
                'pollUrl' => route('screen.photos', ['token' => $token], false),
                'secondsPerPhoto' => $settings['seconds_per_photo'],
                'transition' => $settings['transition'],
                'status' => $status->value,
            ],
            'settings' => $settings,
        ]));
    }

    public function photos(Request $request, string $token): JsonResponse
    {
        $event = $this->resolveEvent($token);
        $status = $event->computeStatus();

        $this->abortUnlessVisible($status);

        $after = $this->parseCursor($request->query('after'));

        $query = Photo::withoutGlobalScopes()
            ->where('event_id', $event->id)
            ->where('status', 'approved')
            ->whereNotNull('approved_at');

        $removed = [];

        if ($after !== null) {
            $afterAt = $this->microsToString($after['micros']);
            $afterId = (int) Photo::withoutGlobalScopes()
                ->where('event_id', $event->id)
                ->where('uuid', $after['uuid'])
                ->value('id');

            $query->where(function ($q) use ($afterAt, $afterId) {
                $q->where('approved_at', '>', $afterAt)
                    ->orWhere(function ($q) use ($afterAt, $afterId) {
                        $q->where('approved_at', $afterAt)->where('id', '>', $afterId);
                    });
            });

            // Rejeitadas/retiradas desde o cursor anterior. updated_at tem precisão de segundo e
            // a comparação é inclusiva (>=) no segundo do cursor, então nada escapa entre duas
            // chamadas; o telão ignora uuids que não conhece.
            $removed = Photo::withoutGlobalScopes()
                ->where('event_id', $event->id)
                ->where('status', 'rejected')
                ->where('updated_at', '>=', Carbon::createFromTimestamp(intdiv($after['micros'], 1_000_000), config('app.timezone'))->format('Y-m-d H:i:s'))
                ->orderBy('id')
                ->limit(self::REMOVED_LIMIT)
                ->pluck('uuid')
                ->all();
        }

        $rows = $query->orderBy('approved_at')->orderBy('id')->limit(self::LIMIT + 1)->get();
        $hasMore = $rows->count() > self::LIMIT;
        $rows = $rows->take(self::LIMIT);

        // Conta com teste encerrado só entrega miniatura (regra de plano do MediaController).
        $variant = $event->account?->status === AccountStatus::TrialEnded ? 'thumb' : 'original';

        $last = $rows->last();

        return $this->harden(response()->json([
            'status' => $status->value,
            'photos' => $rows->map(fn (Photo $photo) => [
                'uuid' => $photo->uuid,
                'url' => $photo->signedMediaUrl($variant, self::URL_TTL_MINUTES),
                'orientation' => $photo->orientation,
            ])->values()->all(),
            'removed' => $removed,
            'cursor' => $last ? $this->cursorFor($last) : ($after !== null ? $request->query('after') : null),
            'has_more' => $hasMore,
            'ttl' => self::URL_TTL_MINUTES * 60,
        ]));
    }

    private function resolveEvent(string $token): Event
    {
        return Event::withoutGlobalScopes()
            ->whereScreenToken($token)
            ->firstOr(fn () => abort(404));
    }

    private function abortUnlessVisible(EventStatus $status): void
    {
        abort_if(in_array($status, [EventStatus::Draft, EventStatus::Expired], true), 404);
    }

    /**
     * Cursor opaco: "{micros}_{uuid}" (approved_at em microssegundos + uuid da foto, sem id sequencial).
     *
     * @return array{micros: int, uuid: string}|null
     */
    private function parseCursor(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) || ! preg_match('/^(\d{10,17})_([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$/i', $value, $m)) {
            abort(422, 'Cursor inválido.');
        }

        return ['micros' => (int) $m[1], 'uuid' => strtolower($m[2])];
    }

    private function cursorFor(Photo $photo): string
    {
        return $photo->approved_at->format('U').$photo->approved_at->format('u').'_'.$photo->uuid;
    }

    private function microsToString(int $micros): string
    {
        return Carbon::createFromFormat('U u', intdiv($micros, 1_000_000).' '.str_pad((string) ($micros % 1_000_000), 6, '0', STR_PAD_LEFT))
            ->setTimezone(config('app.timezone'))
            ->format('Y-m-d H:i:s.u');
    }

    /**
     * Resposta sem cache e sem Referer: o token do telão está na URL.
     */
    private function harden(Response|JsonResponse $response): Response|JsonResponse
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
