<?php

namespace App\Http\Controllers\Privacy;

use App\Http\Controllers\Controller;
use App\Mail\PrivacyRequestReceived;
use App\Models\Event;
use App\Models\PrivacyRequest;
use App\Services\PrivacySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Formulário público do titular (convidado) para pedir acesso/exclusão depois do evento.
 *
 * GET  /privacidade/solicitacao   formulário
 * POST /privacidade/solicitacao   cria privacy_requests (status open), rate limit por IP + honeypot
 *
 * Não guarda IP nem user agent. O evento é identificado pelo slug do link informado (sem exigir
 * o token): só para o admin encaminhar ao organizador; a resposta ao requerente é sempre a mesma.
 * E-mail: só um aviso sem dados pessoais para o contato de privacidade, e apenas se houver SMTP
 * configurado; caso contrário, o pedido fica só registrado e visível no admin.
 */
class PrivacyRequestController extends Controller
{
    public function create(Request $request): View
    {
        $this->useRequestedLocale($request);

        return view('privacy.request', [
            'types' => PrivacyRequest::TYPES,
            'days' => config('photoparty.privacy.response_days'),
        ]);
    }

    public function store(Request $request, PrivacySettings $settings): RedirectResponse
    {
        $this->useRequestedLocale($request);

        // Honeypot: robô preencheu o campo invisível => finge sucesso e não grava nada.
        if (filled($request->input('website'))) {
            return redirect()->route('privacy.request.create')->with('privacy_request_sent', true);
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(PrivacyRequest::TYPES)],
            'event_link' => ['required', 'string', 'max:500'],
            'email' => ['required', 'string', 'email', 'max:254'],
            'details' => ['nullable', 'string', 'max:2000'],
            'confirm' => ['accepted'],
        ], [], [
            'type' => __('privacy.form.type'),
            'event_link' => __('privacy.form.event_link'),
            'email' => __('privacy.form.email'),
            'details' => __('privacy.form.details'),
            'confirm' => __('privacy.form.confirm_attribute'),
        ]);

        $model = new PrivacyRequest([
            'type' => $data['type'],
            'event_link' => trim($data['event_link']),
            'email' => mb_strtolower(trim($data['email'])),
            'details' => isset($data['details']) ? trim($data['details']) : null,
            'locale' => App::getLocale(),
        ]);

        if ($event = $this->eventFromLink($data['event_link'])) {
            $model->event_id = $event->id;
            $model->account_id = $event->account_id;
        }

        $model->save();

        $this->notifyOperator($model, $settings);

        return redirect()->route('privacy.request.create')
            ->with('privacy_request_sent', true)
            ->with('privacy_request_protocol', substr($model->uuid, 0, 8));
    }

    /**
     * Extrai o slug de um link /e/{slug}?t=... (ou do próprio slug) e procura o evento.
     */
    private function eventFromLink(string $link): ?Event
    {
        $path = parse_url(trim($link), PHP_URL_PATH) ?: trim($link);

        if (! preg_match('#(?:^|/)e/([a-z0-9-]{1,80})/?$#', (string) $path, $m)
            && ! preg_match('#^([a-z0-9-]{1,80})$#', (string) $path, $m)) {
            return null;
        }

        return Event::withoutGlobalScopes()->where('slug', $m[1])->first();
    }

    private function notifyOperator(PrivacyRequest $request, PrivacySettings $settings): void
    {
        $to = $settings->contactEmail();

        if ($to === '' || in_array(config('mail.default'), ['log', 'array', null], true)) {
            return;
        }

        try {
            Mail::to($to)->queue(new PrivacyRequestReceived(substr($request->uuid, 0, 8), $request->type));
        } catch (Throwable) {
            // Falha de fila/e-mail não impede o registro; o pedido aparece no admin.
        }
    }

    private function useRequestedLocale(Request $request): void
    {
        $lang = $request->query('lang', $request->input('lang'));

        if (is_string($lang) && in_array($lang, config('photoparty.locales'), true)) {
            App::setLocale($lang);
        }
    }
}
