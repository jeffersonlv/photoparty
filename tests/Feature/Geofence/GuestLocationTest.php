<?php

use App\Enums\AccountStatus;
use App\Models\Account;
use App\Models\Event;
use App\Models\Photo;
use App\Models\Plan;
use App\Services\Geofence\LocationCheck;
use App\Services\GuestDevice;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\GuestUpload;

/*
| "Envio só no local do evento": POST /e/{slug}/location e exigência do passe no upload.
| Local do evento: -23.561414, -46.655881 (raio 100 m).
*/

const GEO_LAT = -23.561414;
const GEO_LNG = -46.655881;

beforeEach(function () {
    Storage::fake('local');
    $this->freezeSecond();
    $this->account = Account::factory()->active(Plan::factory()->create(['allows_geofence' => true]))->create();
    $this->event = geofencedEvent($this->account);
    $this->device = str_repeat('G', 40);
});

function geofencedEvent(Account $account, array $attributes = []): Event
{
    $event = GuestUpload::liveEvent($account);
    $event->forceFill(array_merge([
        'geofence_enabled' => true,
        'geofence_lat' => GEO_LAT,
        'geofence_lng' => GEO_LNG,
        'geofence_radius_m' => 100,
    ], $attributes))->save();

    return $event->refresh();
}

/** Ponto a ~$meters metros ao norte do local do evento. */
function northOf(float $meters): float
{
    return GEO_LAT + $meters / 111195.0;
}

function locate(Event $event, array $overrides = [], ?string $device = null, ?string $token = null)
{
    $test = test();
    withGuestCookies($test, $device);

    // fetch com credentials same-origin: o navegador manda os cookies (aparelho e sessão).
    $response = $test->withCredentials()->postJson(route('guest.location', ['slug' => $event->slug, 't' => $token ?? $event->uploadToken()]), array_merge([
        'latitude' => GEO_LAT,
        'longitude' => GEO_LNG,
        'accuracy' => 20,
        'timestamp' => now()->getTimestampMs(),
    ], $overrides));

    // O cliente de teste não guarda cookies entre requisições: repassa o da sessão (como o navegador).
    $test->guestSessionId = session()->getId();

    return $response;
}

function withGuestCookies($test, ?string $device = null): void
{
    $test->withCookie(GuestDevice::COOKIE, $device ?? $test->device);

    if (isset($test->guestSessionId)) {
        $test->withCookie(config('session.cookie'), $test->guestSessionId);
    }
}

/** Payload do app do convidado (bloco JSON pp-event). */
function guestPayload(string $html): array
{
    preg_match('#<script type="application/json" id="pp-event">(.*?)</script>#s', $html, $m);

    return json_decode($m[1] ?? 'null', true) ?? [];
}

function sendPhoto(Event $event, ?string $device = null)
{
    $test = test();
    withGuestCookies($test, $device);

    return $test->post(GuestUpload::storeUrl($event), GuestUpload::payload(), ['Accept' => 'application/json']);
}

it('computes haversine distances', function () {
    expect(LocationCheck::distanceMeters(GEO_LAT, GEO_LNG, GEO_LAT, GEO_LNG))->toBe(0.0)
        ->and(LocationCheck::distanceMeters(GEO_LAT, GEO_LNG, northOf(500), GEO_LNG))->toBeGreaterThan(499.0)->toBeLessThan(501.0)
        // São Paulo -> Rio de Janeiro ~ 360 km
        ->and(LocationCheck::distanceMeters(-23.5505, -46.6333, -22.9068, -43.1729) / 1000)->toBeGreaterThan(355.0)->toBeLessThan(365.0);
});

it('accepts a guest inside the radius and grants a 30 minute pass', function () {
    locate($this->event, ['latitude' => northOf(60)])
        ->assertOk()
        ->assertJson(['ok' => true, 'required' => true, 'expires_in' => 1800]);

    $response = sendPhoto($this->event)->assertCreated();

    expect(Photo::withoutGlobalScopes()->where('uuid', $response->json('uuid'))->sole()->location_verified)->toBeTrue();
});

it('accepts when distance minus accuracy is within the radius', function () {
    // 200 m de distância, precisão 120 m => 80 <= 100
    locate($this->event, ['latitude' => northOf(200), 'accuracy' => 120])->assertOk()->assertJson(['ok' => true]);
});

it('refuses a guest outside the radius with the rounded distance', function () {
    locate($this->event, ['latitude' => northOf(437), 'accuracy' => 30])
        ->assertStatus(422)
        ->assertJson(['ok' => false, 'code' => 'outside', 'distance_m' => 440])
        ->assertJsonPath('message', fn (string $m) => str_contains($m, 'Você precisa estar no local do evento para enviar fotos') && str_contains($m, '440 m'));

    sendPhoto($this->event)->assertForbidden()->assertJson(['code' => 'location_required']);
});

it('refuses an inaccurate reading (> 150 m)', function () {
    locate($this->event, ['accuracy' => 151])
        ->assertStatus(422)
        ->assertJson(['ok' => false, 'code' => 'inaccurate'])
        ->assertJsonPath('message', __('guest.geo_inaccurate'));
});

it('refuses a reading older than 2 minutes', function () {
    locate($this->event, ['timestamp' => now()->subSeconds(121)->getTimestampMs()])
        ->assertStatus(422)
        ->assertJson(['ok' => false, 'code' => 'stale']);

    locate($this->event, ['timestamp' => now()->subSeconds(100)->getTimestampMs()])->assertOk();
});

it('rejects malformed readings', function (array $body) {
    locate($this->event, $body)->assertStatus(422)->assertJson(['ok' => false, 'code' => 'invalid']);
})->with([
    'latitude fora' => [['latitude' => 95]],
    'sem precisão' => [['accuracy' => null]],
    'timestamp texto' => [['timestamp' => 'ontem']],
]);

it('requires the pass for uploads: 403 location_required without it', function () {
    sendPhoto($this->event)
        ->assertForbidden()
        ->assertJson(['code' => 'location_required', 'message' => __('upload.errors.location_required')]);

    expect(Photo::withoutGlobalScopes()->count())->toBe(0);
});

it('does not accept the pass of another event', function () {
    $other = geofencedEvent($this->account);

    locate($other)->assertOk();
    sendPhoto($this->event)->assertForbidden()->assertJson(['code' => 'location_required']);
    sendPhoto($other)->assertCreated();
});

it('binds the pass to the device', function () {
    locate($this->event)->assertOk();

    sendPhoto($this->event, str_repeat('H', 40))->assertForbidden()->assertJson(['code' => 'location_required']);
});

it('expires the pass after 30 minutes', function () {
    locate($this->event)->assertOk();
    sendPhoto($this->event)->assertCreated();

    $this->travel(29)->minutes();
    sendPhoto($this->event)->assertCreated();

    $this->travel(2)->minutes();
    sendPhoto($this->event)->assertForbidden()->assertJson(['code' => 'location_required']);

    // refaz a checagem e volta a enviar
    locate($this->event)->assertOk();
    sendPhoto($this->event)->assertCreated();
});

it('never stores guest coordinates in the database, logs or session', function () {
    $logged = [];
    app('events')->listen(MessageLogged::class, function (MessageLogged $e) use (&$logged) {
        $logged[] = $e->message.' '.json_encode($e->context);
    });

    $lat = -23.561987;
    $lng = -46.656123;
    locate($this->event, ['latitude' => $lat, 'longitude' => $lng, 'accuracy' => 33.3])->assertOk();
    locate($this->event, ['latitude' => -23.5719, 'longitude' => -46.6719])->assertStatus(422);
    sendPhoto($this->event)->assertCreated();

    // Nenhuma coluna de coordenadas do convidado; a foto só tem o booleano.
    expect(Schema::getColumnListing('photos'))->toContain('location_verified')
        ->not->toContain('latitude')->not->toContain('longitude')->not->toContain('accuracy');

    $dump = '';
    foreach (DB::select("SELECT name FROM sqlite_master WHERE type = 'table'") as $table) {
        $dump .= json_encode(DB::table($table->name)->get());
    }

    expect($dump)->not->toContain('23.561987')->not->toContain('46.656123')
        ->not->toContain('23.5719')->not->toContain('46.6719')
        ->and(implode("\n", $logged))->not->toContain('23.561987')->not->toContain('46.656123')
        ->and(json_encode(session()->all()))->not->toContain('23.561987')->not->toContain('46.656123');

    // Arquivos de log em disco também não.
    foreach (glob(storage_path('logs/*.log')) ?: [] as $file) {
        expect(file_get_contents($file))->not->toContain('23.561987');
    }
});

it('rate limits the location endpoint per device', function () {
    for ($i = 0; $i < 10; $i++) {
        locate($this->event, ['accuracy' => 999])->assertStatus(422);
    }

    locate($this->event)->assertStatus(429);
});

it('returns 404 for a wrong token (isolation)', function () {
    $foreign = geofencedEvent(Account::factory()->active(Plan::factory()->create(['allows_geofence' => true]))->create());

    locate($this->event, [], null, $foreign->uploadToken())->assertNotFound();
});

it('sends the location step only when the geofence is active', function () {
    $html = $this->withCookie(GuestDevice::COOKIE, $this->device)
        ->get(GuestUpload::pageUrl($this->event))
        ->assertOk()
        ->assertSee(__('guest.geo_allow'))
        ->getContent();

    $location = guestPayload($html)['location'];
    expect($location['url'])->toBe('/e/'.$this->event->slug.'/location?t='.$this->event->uploadToken())
        ->and($location['passExpiresIn'])->toBe(0)
        ->and($html)->not->toContain((string) GEO_LAT);

    $plain = GuestUpload::liveEvent($this->account);
    $html = $this->get(GuestUpload::pageUrl($plain))->assertOk()->getContent();
    expect(guestPayload($html))->toHaveKey('location', null);
});

it('changes nothing for events without geofence', function () {
    $plain = GuestUpload::liveEvent($this->account);

    locate($plain)->assertOk()->assertJson(['ok' => true, 'required' => false]);

    $response = sendPhoto($plain)->assertCreated();
    expect(Photo::withoutGlobalScopes()->where('uuid', $response->json('uuid'))->sole()->location_verified)->toBeFalse();
});

it('stops requiring location when the account loses the feature', function (string $change) {
    if ($change === 'plan') {
        $this->account->plan->update(['allows_geofence' => false]);
    } else {
        $this->account->forceFill(['status' => AccountStatus::PastDue])->save();
    }

    $event = $this->event->refresh();
    expect($event->geofence_enabled)->toBeTrue()->and($event->geofenceActive())->toBeFalse();

    $html = $this->get(GuestUpload::pageUrl($event))->assertOk()->getContent();
    expect(guestPayload($html))->toHaveKey('location', null);

    if ($change === 'plan') {
        // active continua aceitando fotos, agora sem pedir localização
        sendPhoto($event)->assertCreated();
    }

    locate($event)->assertOk()->assertJson(['required' => false]);
})->with(['plan', 'status']);
