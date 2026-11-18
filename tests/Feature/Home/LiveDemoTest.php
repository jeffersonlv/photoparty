<?php

use App\Enums\EventStatus;
use App\Models\Account;
use App\Models\Event;
use App\Models\HomeSection;
use App\Models\Photo;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Demo\DemoEvent;
use App\Services\GuestDevice;
use Database\Seeders\DemoSeeder;
use Database\Seeders\FrameSeeder;
use Database\Seeders\HomeSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\Support\GuestUpload;
use Tests\Support\PhotoFixtures;

/*
| Demonstração ao vivo da página inicial: evento de demo do sistema, QR + mini-telão, limpeza horária,
| limites e toggle do admin.
*/

beforeEach(function () {
    Storage::fake('local');
    (new HomeSeeder)->run();
    (new FrameSeeder)->run();
});

function demoEvent(): Event
{
    (new DemoSeeder)->run();

    return Event::withoutGlobalScopes()->where('is_demo', true)->sole();
}

/** Visitante da home: código emitido (hash no cache, como no hero) + dados da sessão dele. */
function newVisitor(): array
{
    $code = Str::random(32);
    $expires = now()->addMinutes(DemoEvent::PAIR_TTL_MINUTES);
    Cache::put('demo.pair.'.DemoEvent::hashPair($code), 1, $expires);

    return ['code' => $code, 'session' => [DemoEvent::PAIR_SESSION => ['code' => $code, 'expires_at' => $expires->getTimestamp()]]];
}

function demoPhotos(?array $visitor = null)
{
    return test()->withSession($visitor['session'] ?? [])->getJson('/demo/photos');
}

function demoUpload(Event $event, array $overrides = [], string $ip = '10.0.0.1', ?string $device = null, ?string $code = null)
{
    $code ??= newVisitor()['code'];
    $url = GuestUpload::storeUrl($event).($code === '' ? '' : '&d='.$code);

    return test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->withCookie(GuestDevice::COOKIE, $device ?? str_repeat('d', 40))
        ->post($url, GuestUpload::payload($overrides), ['Accept' => 'application/json']);
}

/** Foto aprovada gravada já pareada com o código (hash). */
function pairedPhoto(Event $event, string $code, string $status = 'approved'): Photo
{
    $photo = GuestUpload::storedPhoto($event, $status);
    Photo::withoutGlobalScopes()->whereKey($photo->id)->update(['demo_pair' => DemoEvent::hashPair($code)]);

    return $photo;
}

it('seeds the demo account and event idempotently', function () {
    (new DemoSeeder)->run();
    $first = Event::withoutGlobalScopes()->where('is_demo', true)->sole();
    $token = $first->uploadToken();

    (new DemoSeeder)->run();
    (new DemoSeeder)->run();

    $events = Event::withoutGlobalScopes()->where('is_demo', true)->get();
    $account = Account::query()->find($first->account_id);

    expect($events)->toHaveCount(1)
        ->and($events->first()->id)->toBe($first->id)
        ->and($events->first()->uploadToken())->toBe($token)
        ->and(Account::query()->where('name', 'Photo Party Demo')->count())->toBe(1)
        ->and($account->isActive())->toBeTrue()
        ->and($account->plan)->not->toBeNull()
        ->and($account->plan->is_public)->toBeFalse()
        ->and($first->slug)->toBe(config('photoparty.demo.slug'))
        ->and($first->moderation)->toBe('post')
        ->and($first->geofence_enabled)->toBeFalse()
        ->and($first->hasGeofenceLocation())->toBeFalse()
        ->and($first->geofenceActive())->toBeFalse();
});

it('does not mistake an organizer account named like the demo for the demo', function () {
    Account::factory()->active()->create(['name' => 'Photo Party Demo']);
    $event = demoEvent();

    expect(Account::query()->where('name', 'Photo Party Demo')->count())->toBe(2)
        ->and($event->is_demo)->toBeTrue()
        ->and(Event::withoutGlobalScopes()->where('is_demo', true)->count())->toBe(1);
});

it('keeps the demo always live, even long after the seed, via demo:reset', function () {
    $event = demoEvent();

    expect($event->computeStatus())->toBe(EventStatus::Live);

    $this->travel(10)->days();
    expect($event->fresh()->computeStatus())->toBe(EventStatus::Ended);

    Artisan::call('demo:reset');

    expect($event->fresh()->computeStatus())->toBe(EventStatus::Live)
        ->and($event->fresh()->starts_at->isPast())->toBeTrue()
        ->and(now()->diffInHours($event->fresh()->ends_at))->toBeGreaterThan(24);
});

it('schedules demo:reset hourly', function () {
    $events = collect(app(Schedule::class)->events())->filter(fn ($e) => str_contains($e->command, 'demo:reset'));

    expect($events)->toHaveCount(1)->and($events->first()->expression)->toBe('0 * * * *');
});

it('deletes all demo photos and files hourly without touching other events', function () {
    $demo = demoEvent();
    $mine = GuestUpload::storedPhoto($demo, 'approved');
    $pending = GuestUpload::storedPhoto($demo, 'pending');
    $deleted = PhotoFixtures::make($demo, Photo::STATUS_DELETED);
    $files = [$mine->original_path, $mine->thumb_path, $pending->original_path, $pending->thumb_path];
    Storage::disk('local')->assertExists($files);

    $otherAccount = Account::factory()->active()->create();
    $other = GuestUpload::liveEvent($otherAccount);
    $otherPhoto = GuestUpload::storedPhoto($other, 'approved');

    $this->artisan('demo:reset')->assertSuccessful();

    expect(Photo::withoutGlobalScopes()->where('event_id', $demo->id)->count())->toBe(0);
    Storage::disk('local')->assertMissing($files);
    expect(Storage::disk('local')->exists('tenants/'.Account::query()->find($demo->account_id)->uuid.'/events/'.$demo->uuid))->toBeFalse();

    // outra conta intacta
    expect(Photo::withoutGlobalScopes()->whereKey($otherPhoto->id)->exists())->toBeTrue();
    Storage::disk('local')->assertExists([$otherPhoto->original_path, $otherPhoto->thumb_path]);
    expect($deleted->id)->not->toBeNull();
});

it('does nothing when there is no demo event', function () {
    $this->artisan('demo:reset')->assertSuccessful();

    expect(Event::withoutGlobalScopes()->where('is_demo', true)->count())->toBe(0);
});

it('refuses to purge a non-demo event', function () {
    $event = GuestUpload::liveEvent();

    expect(fn () => app(DemoEvent::class)->purgePhotos($event))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('accepts uploads on the demo and approves them right away (post moderation)', function () {
    $demo = demoEvent();

    $response = demoUpload($demo)->assertCreated()->assertJson(['status' => 'approved']);

    expect(Photo::withoutGlobalScopes()->where('uuid', $response->json('uuid'))->value('event_id'))->toBe($demo->id);
});

it('limits the demo to 30 photos per hour on the server', function () {
    $demo = demoEvent();
    config(['photoparty.demo.max_photos_per_hour' => 3, 'photoparty.demo.ip_uploads_per_hour' => 100]);
    // o plano interno tem o mesmo teto por evento; o limite por hora vale mesmo com vaga no plano
    $demo->account->plan->update(['max_photos_per_event' => 100]);

    foreach (range(1, 3) as $i) {
        demoUpload($demo, [], '10.0.0.'.$i, str_repeat((string) $i, 40))->assertCreated();
    }

    demoUpload($demo, [], '10.0.0.9', str_repeat('9', 40))
        ->assertStatus(429)
        ->assertJsonFragment(['message' => __('upload.errors.demo_limit')]);

    // uma hora depois (e a limpeza horária) volta a aceitar
    $this->travel(61)->minutes();
    demoUpload($demo, [], '10.0.0.10', str_repeat('8', 40))->assertCreated();
});

it('has a default of 30 photos per hour', function () {
    expect(config('photoparty.demo.max_photos_per_hour'))->toBe(30);
});

it('rate limits demo uploads per IP per hour, without affecting regular events', function () {
    $demo = demoEvent();
    config(['photoparty.demo.ip_uploads_per_hour' => 2, 'photoparty.demo.max_photos_per_hour' => 100]);
    $demo->account->plan->update(['max_photos_per_event' => 100]);
    RateLimiter::clear('guest-upload-demo-ip|10.1.1.1');

    demoUpload($demo, [], '10.1.1.1', str_repeat('a', 40))->assertCreated();
    demoUpload($demo, [], '10.1.1.1', str_repeat('b', 40))->assertCreated();
    demoUpload($demo, [], '10.1.1.1', str_repeat('c', 40))->assertStatus(429);

    // outro IP ainda passa
    demoUpload($demo, [], '10.1.1.2', str_repeat('e', 40))->assertCreated();

    // evento comum não tem o teto por hora por IP
    $regular = GuestUpload::liveEvent();
    foreach (range(1, 3) as $i) {
        demoUpload($regular, [], '10.1.1.1', str_repeat((string) $i, 40).'x')->assertCreated();
    }
});

it('shows the public notice on the demo guest page only', function () {
    $demo = demoEvent();
    $regular = GuestUpload::liveEvent();

    $demoHtml = $this->get(GuestUpload::pageUrl($demo))->assertOk()->getContent();
    $regularHtml = $this->get(GuestUpload::pageUrl($regular))->assertOk()->getContent();

    expect($demoHtml)->toContain('"demo":true')
        ->and($demoHtml)->toContain('aparecem só na tela de quem gerou este QR code e são apagadas a cada hora')
        ->and($regularHtml)->toContain('"demo":false');
});

it('renders the live demo in the hero: QR of the guest link and the mini screen, with strict CSP', function () {
    $demo = demoEvent();

    $response = $this->get('/')->assertOk();
    $html = $response->getContent();

    expect($html)->toContain('data-demo="live"')
        ->and($html)->toContain('id="pp-demo"')
        ->and($html)->toContain('id="demo-stage"')
        ->and($html)->toContain('src="data:image/svg+xml;base64,')
        ->and($html)->toContain(e(route('guest.show', ['slug' => $demo->slug, 't' => $demo->uploadToken()])))
        ->and($html)->toContain('"pollUrl":"\/demo\/photos"')
        ->and($html)->toContain('são apagadas a cada hora')->and($html)->toMatch('/\\?t=[A-Za-z0-9]{64}(&amp;|&)d=[A-Za-z0-9]{32}/')
        ->and($html)->not->toContain('Demonstração ao vivo em breve')
        ->and($html)->not->toContain('data-demo="placeholder"');

    // CSP estrita: scripts só externos ou blocos de dados JSON; nada inline.
    preg_match_all('/<script\b([^>]*)>/i', $html, $scripts);
    foreach ($scripts[1] as $attrs) {
        expect($attrs)->toMatch('/src=|type="(application\/json|module)"/');
    }
    expect($html)->not->toContain(' onclick=')->not->toContain('style="');
    expect($response->headers->get('Content-Security-Policy'))->toContain("script-src 'self'")->not->toContain('unsafe-inline');

    // o token do QR é o do upload do evento de demo (não o do telão)
    expect($html)->not->toContain($demo->screenToken());
});

it('falls back to the static block when there is no demo event', function () {
    $this->get('/')->assertOk()->assertSee('Demonstração ao vivo em breve')->assertSee('data-demo="placeholder"', false);
});

it('hides the demo with the admin toggle and shows the static block', function () {
    demoEvent();
    $admin = User::factory()->admin()->create();
    $section = HomeSection::query()->where('key', 'hero')->first();
    $content = collect(config('photoparty.locales'))->mapWithKeys(fn ($l) => [$l => [
        'title' => 'Título', 'subtitle' => 'Sub', 'cta_primary' => 'Criar',
    ]])->all();

    $this->actingAs($admin)->get('/admin/home/sections/hero')->assertOk()->assertSee('name="demo_live"', false);

    $this->actingAs($admin)->put('/admin/home/sections/hero', ['content' => $content, 'demo_live' => '0'])->assertRedirect();
    expect(SiteSetting::query()->where('key', 'demo_live')->value('value'))->toBe('0');

    auth()->logout();
    $this->get('/')->assertSee('data-demo="placeholder"', false)->assertDontSee('data-demo="live"', false)->assertDontSee('id="pp-demo"', false);
    $this->getJson('/demo/photos')->assertNotFound();

    $this->actingAs($admin)->put('/admin/home/sections/hero', ['content' => $content, 'demo_live' => '1'])->assertRedirect();
    auth()->logout();
    $this->get('/')->assertSee('data-demo="live"', false);
    $this->getJson('/demo/photos')->assertOk();

    expect($section)->not->toBeNull();
});

it('rejects an invalid toggle value', function () {
    $admin = User::factory()->admin()->create();
    $content = collect(config('photoparty.locales'))->mapWithKeys(fn ($l) => [$l => ['title' => 'T', 'subtitle' => 'S', 'cta_primary' => 'C']])->all();

    $this->actingAs($admin)->put('/admin/home/sections/hero', ['content' => $content, 'demo_live' => 'maybe'])->assertSessionHasErrors('demo_live');
});

it('public endpoint returns only approved photos of the visitor pairing, newest first, capped', function () {
    $demo = demoEvent();
    config(['photoparty.demo.recent_photos' => 3]);
    $v = newVisitor();

    $old = pairedPhoto($demo, $v['code']);
    $pending = pairedPhoto($demo, $v['code'], 'pending');
    $rejected = pairedPhoto($demo, $v['code'], 'rejected');
    $ids = [$old->uuid];
    foreach (range(1, 3) as $i) {
        $this->travel(1)->seconds();
        $ids[] = pairedPhoto($demo, $v['code'])->uuid;
    }

    $json = demoPhotos($v)->assertOk()->json();
    $uuids = array_column($json['photos'], 'uuid');

    expect($uuids)->toBe([$ids[3], $ids[2], $ids[1]])
        ->and($uuids)->not->toContain($old->uuid, $pending->uuid, $rejected->uuid)
        ->and($json['photos'][0])->toHaveKeys(['uuid', 'url', 'orientation'])
        ->and($json['photos'][0]['url'])->toStartWith('/media/signed/')
        ->and($json['photos'][0])->not->toHaveKeys(['id', 'account_id', 'event_id', 'device_hash', 'original_path', 'demo_pair']);

    $this->get($json['photos'][0]['url'])->assertOk()->assertHeader('Content-Type', 'image/jpeg');
});

it('pairs each visitor with their own QR: two visitors never see each other photos', function () {
    $demo = demoEvent();
    $a = newVisitor();
    $b = newVisitor();

    $fromA = demoUpload($demo, [], '10.2.0.1', str_repeat('a', 40), $a['code'])->assertCreated()->json('uuid');
    $fromB = demoUpload($demo, [], '10.2.0.2', str_repeat('b', 40), $b['code'])->assertCreated()->json('uuid');

    expect(array_column(demoPhotos($a)->assertOk()->json('photos'), 'uuid'))->toBe([$fromA])
        ->and(array_column(demoPhotos($b)->assertOk()->json('photos'), 'uuid'))->toBe([$fromB]);

    // grava só o hash, nunca o código em claro
    $stored = Photo::withoutGlobalScopes()->where('uuid', $fromA)->value('demo_pair');
    expect($stored)->toBe(DemoEvent::hashPair($a['code']))->not->toBe($a['code']);
    expect(Photo::withoutGlobalScopes()->where('demo_pair', $a['code'])->exists())->toBeFalse();
});

it('returns an empty list without a valid pairing code in the session', function () {
    $demo = demoEvent();
    $v = newVisitor();
    pairedPhoto($demo, $v['code']);

    expect(demoPhotos()->assertOk()->json('photos'))->toBe([]);

    // sessão com código expirado
    $expired = ['code' => $v['code'], 'session' => [DemoEvent::PAIR_SESSION => ['code' => $v['code'], 'expires_at' => now()->subMinute()->getTimestamp()]]];
    expect(demoPhotos($expired)->assertOk()->json('photos'))->toBe([]);

    // fotos antigas sem pareamento (null) nunca aparecem
    GuestUpload::storedPhoto($demo, 'approved');
    expect(array_column(demoPhotos($v)->json('photos'), 'uuid'))->toHaveCount(1);
});

it('rejects demo uploads without a valid pairing code with 422', function (?string $code) {
    $demo = demoEvent();

    demoUpload($demo, [], '10.3.0.1', null, $code)->assertUnprocessable()->assertJsonValidationErrors('photo');

    expect(Photo::withoutGlobalScopes()->where('event_id', $demo->id)->count())->toBe(0);
})->with([
    'missing' => '',
    'wrong format' => 'curto',
    'well formed but never issued' => str_repeat('Z', 32),
]);

it('rejects an expired pairing code on upload', function () {
    $demo = demoEvent();
    $v = newVisitor();

    $this->travel(61)->minutes();

    demoUpload($demo, [], '10.3.0.2', null, $v['code'])->assertUnprocessable();
});

it('does not require a pairing code on regular events', function () {
    $regular = GuestUpload::liveEvent();

    $this->withCookie(GuestDevice::COOKIE, str_repeat('r', 40))
        ->post(GuestUpload::storeUrl($regular), GuestUpload::payload(), ['Accept' => 'application/json'])->assertCreated();
});

it('issues a stable pairing code per visitor session on the home page and puts it in the QR link', function () {
    $demo = demoEvent();

    $html = $this->get('/')->assertOk()->getContent();
    preg_match('/d=([A-Za-z0-9]{32})/', $html, $m);

    expect(Cache::has('demo.pair.'.DemoEvent::hashPair($m[1])))->toBeTrue();

    $again = $this->withSession(session()->all())->get('/')->getContent();
    expect($again)->toContain('d='.$m[1]);

    // o link do convidado com o código guarda o código na página (uploadUrl leva o d=)
    $page = $this->get(GuestUpload::pageUrl($demo).'&d='.$m[1])->assertOk()->getContent();
    expect($page)->toContain('d='.$m[1]);
});

it('uses correct singular and plural for the retention text in both languages', function (string $locale, int $days, string $expected) {
    app()->setLocale($locale);

    expect(trans_choice('guest.consent_retention', $days, ['days' => $days]))->toContain($expected);
})->with([
    ['pt_BR', 1, 'por até 1 dia após'],
    ['pt_BR', 2, 'por até 2 dias após'],
    ['pt_BR', 30, 'por até 30 dias após'],
    ['en', 1, 'up to 1 day after'],
    ['en', 7, 'up to 7 days after'],
]);

it('sends the pluralized retention text in the guest page payload', function () {
    $account = Account::factory()->active()->create();
    $event = GuestUpload::liveEvent($account);
    $account->plan?->update(['retention_days' => 1]);
    $plan = \App\Models\Plan::factory()->create(['retention_days' => 1]);
    $account->forceFill(['plan_id' => $plan->id])->save();

    $html = $this->get(GuestUpload::pageUrl($event))->assertOk()->getContent();

    expect($html)->toContain('por até 1 dia após o evento')->not->toContain('1 dias');
});

it('never leaks photos of another account or event through the demo endpoint', function () {
    $demo = demoEvent();
    $v = newVisitor();
    $mineDemo = pairedPhoto($demo, $v['code']);

    $other = GuestUpload::liveEvent(Account::factory()->active()->create(['name' => 'Photo Party Demo']));
    $foreign = pairedPhoto($other, $v['code']);

    // foto de outra conta gravada no próprio evento de demo (dado inconsistente) também não sai
    $stray = pairedPhoto($other, $v['code']);
    Photo::withoutGlobalScopes()->whereKey($stray->id)->update(['event_id' => $demo->id]);

    $uuids = array_column(demoPhotos($v)->assertOk()->json('photos'), 'uuid');

    expect($uuids)->toBe([$mineDemo->uuid])
        ->and($uuids)->not->toContain($foreign->uuid, $stray->uuid);

    // sem parâmetros manipuláveis: query string não troca de evento nem de pareamento
    $uuids = array_column($this->withSession($v['session'])->getJson('/demo/photos?event='.$other->uuid.'&d='.newVisitor()['code'])->json('photos'), 'uuid');
    expect($uuids)->toBe([$mineDemo->uuid]);
});

it('returns 404 when there is no demo event or it is not live', function () {
    $this->getJson('/demo/photos')->assertNotFound();

    $demo = demoEvent();
    $demo->forceFill(['ends_at' => now()->subMinute()])->save();

    $this->getJson('/demo/photos')->assertNotFound();
});

it('rate limits the public polling endpoint', function () {
    demoEvent();
    RateLimiter::clear('demo-screen|127.0.0.1');

    foreach (range(1, 60) as $i) {
        $this->getJson('/demo/photos')->assertOk();
    }

    $this->getJson('/demo/photos')->assertStatus(429);
});

it('does not let regular events be flagged as demo through the organizer form', function () {
    $account = Account::factory()->active()->create();
    $owner = ownerFor($account);

    $this->actingAs($owner)->post(route('events.store'), eventPayload(['is_demo' => '1']))->assertSessionHasNoErrors();

    expect(Event::withoutGlobalScopes()->where('is_demo', true)->count())->toBe(0);
});

it('keeps the demo event out of retention purges while live', function () {
    $demo = demoEvent();
    $photo = GuestUpload::storedPhoto($demo, 'approved');

    $this->artisan('privacy:enforce-retention')->assertSuccessful();

    expect(Photo::withoutGlobalScopes()->whereKey($photo->id)->exists())->toBeTrue()
        ->and($demo->fresh()->purged_at)->toBeNull();
});
