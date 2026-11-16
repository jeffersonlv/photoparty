<?php

use App\Models\Account;
use App\Models\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Support\GuestUpload;

/*
| Módulo 5: página do telão (/screen/{token}).
*/

beforeEach(function () {
    Storage::fake('local');
    $this->account = Account::factory()->active()->create();
    $this->event = GuestUpload::liveEvent($this->account, ['name' => 'Casamento Ana & Bia']);
});

function screenUrl(Event $event, ?string $token = null): string
{
    return route('screen.show', ['token' => $token ?? $event->screenToken()]);
}

it('shows the screen for a live event without login and with safe headers', function () {
    $response = $this->get(screenUrl($this->event))
        ->assertOk()
        ->assertSee('Casamento Ana &amp; Bia', false)
        ->assertSee('id="pp-screen"', false)
        ->assertHeader('Referrer-Policy', 'no-referrer');

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    expect($response->headers->get('Content-Security-Policy'))->toContain("script-src 'self'");

    // CSP estrita: todo <script> é arquivo externo ou bloco de dados JSON.
    preg_match_all('/<script\b([^>]*)>/i', $response->getContent(), $scripts);
    foreach ($scripts[1] as $attrs) {
        expect($attrs)->toMatch('/src=|type="(application\/json|module)"/');
    }

    expect($response->getContent())->not->toContain(' onclick=')->not->toContain('style="');
});

it('puts the settings of the event in the data block', function () {
    $this->event->update(['screen_settings' => ['seconds_per_photo' => 9, 'transition' => 'zoom', 'show_qr' => true]]);

    $this->get(screenUrl($this->event))
        ->assertSee('"secondsPerPhoto":9', false)
        ->assertSee('"transition":"zoom"', false);
});

it('falls back to defaults for missing or invalid settings', function () {
    $this->event->update(['screen_settings' => ['seconds_per_photo' => 999, 'transition' => 'explode']]);

    $this->get(screenUrl($this->event))
        ->assertSee('"secondsPerPhoto":60', false)
        ->assertSee('"transition":"fade"', false);

    $this->event->forceFill(['screen_settings' => null])->save();

    $this->get(screenUrl($this->event))
        ->assertSee('"secondsPerPhoto":6', false)
        ->assertSee('"transition":"fade"', false);
});

it('renders the event QR inline (data URI) only when enabled', function () {
    $this->get(screenUrl($this->event))->assertSee('class="sc-qr"', false)->assertSee('data:image/svg+xml;base64,', false);

    $this->event->update(['screen_settings' => ['seconds_per_photo' => 6, 'transition' => 'fade', 'show_qr' => false]]);

    $this->get(screenUrl($this->event))->assertDontSee('class="sc-qr"', false);
});

it('never prints tokens in the page', function () {
    $html = $this->get(screenUrl($this->event))->getContent();

    expect($html)->not->toContain((string) $this->event->uploadToken());
});

it('shows the Photo Party seal while the account is on trial', function () {
    $this->get(screenUrl($this->event))->assertDontSee('class="sc-seal"', false);

    $trialEvent = GuestUpload::liveEvent(Account::factory()->create(), ['is_trial' => true]);
    $this->get(screenUrl($trialEvent))->assertSee('class="sc-seal"', false);

    $trialAccount = Account::factory()->create();
    expect($trialAccount->isOnTrial())->toBeTrue();
    $other = GuestUpload::liveEvent($trialAccount);
    $this->get(screenUrl($other))->assertSee('class="sc-seal"', false);
});

it('returns 404 for unknown, malformed or revoked tokens', function () {
    $old = $this->event->screenToken();

    $this->get(screenUrl($this->event, str_repeat('a', 64)))->assertNotFound();
    $this->get('/screen/curto')->assertNotFound();
    $this->get(screenUrl($this->event, (string) $this->event->uploadToken()))->assertNotFound();

    $this->get(screenUrl($this->event, $old))->assertOk();

    $this->event->issueScreenToken();
    $this->event->save();

    $this->get(screenUrl($this->event, $old))->assertNotFound();
    $this->get(screenUrl($this->event))->assertOk();
});

it('returns 404 for draft and expired events', function () {
    $draft = Event::factory()->for($this->account)->create();
    $this->get(screenUrl($draft))->assertNotFound();

    $expired = GuestUpload::liveEvent($this->account, [
        'starts_at' => now()->subDays(40),
        'ends_at' => now()->subDays(39),
        'expires_at' => now()->subDay(),
    ]);
    $this->get(screenUrl($expired))->assertNotFound();
});

it('shows a starts-at notice for scheduled events', function () {
    $scheduled = Event::factory()->for($this->account)->scheduled()->create(['name' => 'Festa futura']);

    $this->get(screenUrl($scheduled))
        ->assertOk()
        ->assertSee('Festa futura')
        ->assertSee('Começa em')
        ->assertSee('http-equiv="refresh"', false)
        ->assertDontSee('id="pp-screen"', false);
});

it('keeps showing an ended event with a discreet notice and without the QR', function () {
    $ended = GuestUpload::liveEvent($this->account, [
        'starts_at' => now()->subHours(5),
        'ends_at' => now()->subHour(),
    ]);

    $response = $this->get(screenUrl($ended))
        ->assertOk()
        ->assertSee('"status":"ended"', false)
        ->assertDontSee('class="sc-qr"', false);

    expect($response->getContent())->toContain('id="sc-ended"')
        ->and($response->getContent())->not->toMatch('/id="sc-ended"[^>]*\shidden/');

    // Evento ao vivo: o aviso existe mas fica escondido.
    expect($this->get(screenUrl($this->event))->getContent())->toMatch('/id="sc-ended"[^>]*\shidden/');
});
