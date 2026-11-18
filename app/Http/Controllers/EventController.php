<?php

namespace App\Http\Controllers;

use App\Http\Requests\EventRequest;
use App\Models\AuditLog;
use App\Models\Event;
use App\Services\EventScheduler;
use App\Services\QrCodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * CRUD de eventos do organizador.
 *
 * Isolamento: o model Event tem global scope por conta, então {event} de outra
 * conta nem é encontrado no route model binding (404). A policy repete a
 * checagem e também responde 404 (denyAsNotFound).
 */
class EventController extends Controller
{
    public function __construct(private readonly EventScheduler $scheduler)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Event::class);

        $events = Event::query()
            ->orderByDesc('starts_at')
            ->paginate(20);

        // Status sempre atual na tela, mesmo entre execuções do cron.
        foreach ($events as $event) {
            $event->refreshStatus();
        }

        return view('events.index', [
            'events' => $events,
            'account' => $request->user()->account,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $this->authorize('create', Event::class);

        $account = $request->user()->account;

        if ($error = $this->scheduler->creationError($account)) {
            return redirect()->route('events.index')->with('error', $error);
        }

        $startsAt = now()->addHour()->startOfHour();
        $event = new Event([
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours($account->isOnTrial() ? (int) config('photoparty.trial.max_event_hours') : 4),
            'language' => app()->getLocale(),
            'enabled_filters' => config('photoparty.filters'),
        ]);

        return view('events.create', ['event' => $event, 'account' => $account]);
    }

    public function store(EventRequest $request): RedirectResponse
    {
        $this->authorize('create', Event::class);

        $account = $request->user()->account;

        if ($error = $this->scheduler->creationError($account)) {
            return redirect()->route('events.index')->with('error', $error);
        }

        $event = new Event($request->safe()->except('image_authorization'));
        // Declaração LGPD/GDPR aceita (validada como "accepted" no EventRequest).
        $event->declared_at = now();
        $event->is_trial = $account->isOnTrial();
        $event->enabled_filters = $request->validated('enabled_filters', []);
        $event->screen_settings = config('photoparty.screen_defaults');
        // account_id é preenchido pela trait BelongsToAccount (usuário autenticado).
        $event->save();

        AuditLog::record('event.created', $event);

        return redirect()->route('events.show', $event)->with('status', __('app.events.created'));
    }

    public function show(Event $event): View
    {
        $this->authorize('view', $event);

        $event->refreshStatus();

        return view('events.show', [
            'event' => $event,
            'account' => $event->account,
        ]);
    }

    public function edit(Event $event): View
    {
        $this->authorize('update', $event);

        return view('events.edit', ['event' => $event, 'account' => $event->account]);
    }

    public function update(EventRequest $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $event->fill($request->safe()->except('image_authorization'));
        $event->enabled_filters = $request->validated('enabled_filters', []);
        $this->scheduler->syncAfterUpdate($event->account, $event);
        $event->save();

        AuditLog::record('event.updated', $event);

        return redirect()->route('events.show', $event)->with('status', __('app.events.updated'));
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);

        AuditLog::record('event.deleted', $event, ['name' => $event->name]);

        $event->delete();

        return redirect()->route('events.index')->with('status', __('app.events.deleted'));
    }

    public function publish(Event $event): RedirectResponse
    {
        $this->authorize('publish', $event);

        $account = $event->account;
        $errors = $this->scheduler->publishErrors($account, $event);

        if ($errors !== []) {
            return redirect()->route('events.show', $event)->withErrors($errors);
        }

        $this->scheduler->publish($account, $event);

        return redirect()->route('events.show', $event)->with('status', __('app.events.published'));
    }

    public function close(Event $event): RedirectResponse
    {
        $this->authorize('close', $event);

        $this->scheduler->close($event->account, $event);

        return redirect()->route('events.show', $event)->with('status', __('app.events.closed'));
    }

    public function regenerateScreenToken(Event $event): RedirectResponse
    {
        $this->authorize('manageTokens', $event);

        $event->issueScreenToken();
        $event->save();

        // Nunca registrar o token em si.
        AuditLog::record('event.screen_token_regenerated', $event);

        return redirect()->route('events.show', $event)->with('status', __('app.events.screen_token_regenerated'));
    }

    public function qr(Event $event, string $format, QrCodeGenerator $qr): Response
    {
        $this->authorize('downloadQr', $event);

        $content = $qr->render($event->guestUrl(), $format);

        return response($content, 200, [
            'Content-Type' => $qr->contentType($format),
            'Content-Disposition' => 'attachment; filename="qr-'.$event->slug.'.'.$format.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
