<?php

use App\Enums\EventStatus;
use App\Models\Account;
use App\Models\Event;
use App\Models\Frame;
use App\Services\GuestDevice;
use Database\Seeders\FrameSeeder;
use Tests\Support\GuestUpload;

/*
| Módulo 3: página do convidado GET /e/{slug}?t={upload_token}.
*/

beforeEach(function () {
    $this->event = GuestUpload::liveEvent(null, ['name' => 'Casamento Ana & Leo', 'ask_guest_name' => true]);
});

it('registers the four system templates idempotently', function () {
    (new FrameSeeder())->run();

    $frames = Frame::withoutGlobalScopes()->whereNull('account_id')->get();

    expect($frames)->toHaveCount(4)
        ->and($frames->pluck('type')->unique()->all())->toBe(['template'])
        ->and($frames->pluck('template_key')->sort()->values()->all())
        ->toBe(['floral', 'led_billboard', 'magazine_cover', 'wanted_poster']);
});

it('activates the system templates on new events by default', function () {
    expect($this->event->activeFrames()->pluck('template_key')->all())
        ->toBe(FrameSeeder::TEMPLATE_KEYS);

    // Também pelo fluxo do organizador.
    $owner = ownerFor(Account::factory()->active()->create());
    $this->actingAs($owner)->post(route('events.store'), eventPayload())->assertSessionHasNoErrors();

    $created = Event::withoutGlobalScopes()->where('account_id', $owner->account_id)->sole();
    expect($created->activeFrames())->toHaveCount(4);
});

it('renders the guest app with the event payload for a live event', function () {
    $response = $this->get(GuestUpload::pageUrl($this->event))
        ->assertOk()
        ->assertViewIs('guest.show')
        ->assertCookie(GuestDevice::COOKIE);

    $payload = $response->viewData('event');

    expect($payload['name'])->toBe('Casamento Ana & Leo')
        ->and($payload['moderation'])->toBeTrue()
        ->and($payload['askGuestName'])->toBeTrue()
        ->and($payload['language'])->toBe('pt_BR')
        ->and($payload['uploadUrl'])->toBe('/e/'.$this->event->slug.'/photos?t='.$this->event->uploadToken())
        ->and(collect($payload['frames'])->pluck('key')->all())->toBe(FrameSeeder::TEMPLATE_KEYS)
        ->and($payload['frames'][0]['configUrl'])->toBe('/frames/led_billboard/config.json')
        ->and($payload['frames'][0]['name'])->toBe('Painel de LED')
        ->and($payload['frames'][0]['texts']['event_name'])->toBe('Casamento Ana & Leo');

    $cookie = collect($response->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === GuestDevice::COOKIE);
    expect($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax')
        // LGPD/GDPR: 30 dias (não mais 1 ano).
        ->and($cookie->getExpiresTime())->toBeGreaterThan(now()->addDays(29)->timestamp)
        ->and($cookie->getExpiresTime())->toBeLessThanOrEqual(now()->addDays(30)->addMinute()->timestamp);

    // Nome do evento escapado; nenhum token de telão vaza na página.
    $response->assertDontSee('Casamento Ana & Leo</', false)
        ->assertDontSee((string) $this->event->screenToken(), false);
});

it('keeps the guest page free of inline executable scripts and with a strict CSP', function () {
    $response = $this->get(GuestUpload::pageUrl($this->event))->assertOk();

    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)->not->toContain('unsafe-inline')->not->toContain('unsafe-eval')
        ->and($csp)->toContain("script-src 'self'");

    preg_match_all('/<script\b([^>]*)>(.*?)<\/script>/is', $response->getContent(), $matches, PREG_SET_ORDER);

    foreach ($matches as [$tag, $attrs, $body]) {
        $isData = str_contains($attrs, 'type="application/json"');
        $isExternal = str_contains($attrs, 'src=');
        expect($isData || ($isExternal && trim($body) === ''))->toBeTrue("Script inline executável: {$tag}");
    }

    expect($response->getContent())->not->toMatch('/\son[a-z]+\s*=/i');
});

it('returns 404 for a missing, wrong or foreign token', function () {
    $other = GuestUpload::liveEvent();

    $this->get(route('guest.show', ['slug' => $this->event->slug]))->assertNotFound();
    $this->get(GuestUpload::pageUrl($this->event, str_repeat('x', 64)))->assertNotFound();
    // Token do outro evento não abre este.
    $this->get(GuestUpload::pageUrl($this->event, $other->uploadToken()))->assertNotFound();
    $this->get(route('guest.show', ['slug' => 'nao-existe', 't' => $this->event->uploadToken()]))->assertNotFound();
});

it('handles each event state', function () {
    $account = Account::factory()->active()->create();

    $draft = Event::factory()->for($account)->create();
    // Rascunho com link válido: aviso amigável (não 404), sem o formulário de envio.
    $this->get(GuestUpload::pageUrl($draft))->assertOk()
        ->assertSee(__('upload.page.not_published'))
        ->assertDontSee('pp-event');
    // Token errado continua 404 mesmo em rascunho.
    $this->get(GuestUpload::pageUrl($draft, str_repeat('x', 64)))->assertNotFound();

    $expired = Event::factory()->for($account)->create([
        'starts_at' => now()->subDays(40), 'ends_at' => now()->subDays(40)->addHours(2),
        'published_at' => now()->subDays(41), 'expires_at' => now()->subDay(), 'status' => EventStatus::Expired,
    ]);
    $this->get(GuestUpload::pageUrl($expired))->assertNotFound();

    $scheduled = Event::factory()->for($account)->scheduled()->create();
    $this->get(GuestUpload::pageUrl($scheduled))
        ->assertOk()
        ->assertViewIs('guest.status')
        ->assertSee('Este evento começa em');

    $ended = Event::factory()->for($account)->create([
        'starts_at' => now()->subHours(5), 'ends_at' => now()->subHour(),
        'published_at' => now()->subDay(), 'expires_at' => now()->addDays(30), 'status' => EventStatus::Ended,
    ]);
    $this->get(GuestUpload::pageUrl($ended))
        ->assertStatus(410)
        ->assertSee('Este evento já terminou.');
});

it('uses the event language on the guest pages', function () {
    $event = GuestUpload::liveEvent(null, ['language' => 'en']);

    $this->get(GuestUpload::pageUrl($event))->assertOk()->assertSee('lang="en"', false);
    expect(app()->getLocale())->toBe('en');
});
