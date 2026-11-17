<?php

namespace App\Http\Controllers\Guest;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Frame;
use App\Services\GuestDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;

/**
 * Página do convidado: GET /e/{slug}?t={upload_token} (SPEC 9).
 *
 * Estado decidido no servidor: draft/expired => 404; scheduled => "começa em...";
 * ended => "Este evento já terminou" (410); live => app do convidado.
 */
class GuestEventController extends Controller
{
    use ResolvesGuestEvent;

    public function __construct(private readonly GuestDevice $device)
    {
    }

    public function show(Request $request, string $slug): Response
    {
        $event = $this->resolveGuestEvent($request, $slug);
        $status = $event->computeStatus();

        abort_if($status === EventStatus::Expired, 404);

        if (in_array($event->language, config('photoparty.locales'), true)) {
            App::setLocale($event->language);
        }

        // Identificação anônima do aparelho (cookie de 1 ano).
        $this->device->resolveId($request);

        // Link válido, mas o organizador ainda não publicou: aviso em vez de 404.
        if ($status === EventStatus::Draft) {
            return response()->view('guest.status', [
                'title' => $event->name,
                'message' => __('upload.page.not_published'),
            ])->header('Cache-Control', 'no-store, private');
        }

        if ($status === EventStatus::Scheduled) {
            return response()->view('guest.status', [
                'title' => $event->name,
                'message' => __('upload.page.starts_at', [
                    'date' => $event->starts_at->locale(App::getLocale())->translatedFormat(__('upload.page.date_format')),
                ]),
            ]);
        }

        if ($status === EventStatus::Ended) {
            return response()->view('guest.status', [
                'title' => $event->name,
                'message' => __('upload.page.ended'),
            ], 410);
        }

        return response()->view('guest.show', ['event' => $this->payload($event, $this->guestToken($request))])
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * Objeto $event esperado por resources/views/guest/show.blade.php (e pelo JS).
     *
     * @return array<string, mixed>
     */
    private function payload(Event $event, string $token): array
    {
        $lang = str_starts_with($event->language, 'en') ? 'en' : 'pt';

        return [
            'name' => $event->name,
            'welcome' => (string) $event->welcome_message,
            'moderation' => $event->moderation === 'pre',
            'language' => $event->language,
            'askGuestName' => (bool) $event->ask_guest_name,
            // URLs relativas: mesma origem da página (CSP connect-src 'self').
            'uploadUrl' => route('guest.photos.store', ['slug' => $event->slug, 't' => $token], false),
            // Modelos prontos (public/frames) + molduras personalizadas ativas no evento
            // (Event::activeFrames já exclui custom de outra conta ou de plano sem permissão).
            'frames' => $event->activeFrames()
                ->filter(fn (Frame $frame) => $frame->isCustom() || ($frame->type === Frame::TYPE_TEMPLATE && $frame->template_key !== null))
                ->map(fn (Frame $frame) => [
                    'key' => $frame->publicKey(),
                    'name' => $frame->config['name'][$lang] ?? $frame->name,
                    'configUrl' => $frame->isCustom()
                        ? route('guest.frames.asset', ['slug' => $event->slug, 'frame' => $frame->uuid, 'file' => 'config.json', 't' => $token], false)
                        : '/frames/'.rawurlencode($frame->template_key).'/config.json',
                    'orientations' => $frame->orientations(),
                    'texts' => $this->organizerTexts($event),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Valores dos textos do organizador. Os que faltam usam o "default" do config.
     * (Edição por moldura vem no módulo 8.)
     *
     * @return array<string, string>
     */
    private function organizerTexts(Event $event): array
    {
        $date = $event->starts_at->locale(App::getLocale())->translatedFormat(__('upload.page.frame_date_format'));

        return [
            'event_name' => $event->name,
            'event_date' => $date,
            'issue_date' => $date,
        ];
    }
}
