<?php

use App\Models\Account;
use App\Models\Photo;
use App\Services\GuestDevice;
use Illuminate\Support\Facades\Storage;
use Tests\Support\GuestUpload;

/*
| "Minhas fotos" do convidado: só do próprio aparelho/evento, só enquanto o evento está ao vivo,
| download original sem faixa de demonstração, exclusão apaga o arquivo e tira do telão.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->account = Account::factory()->active()->create();
    $this->event = GuestUpload::liveEvent($this->account);
    $this->device = str_repeat('M', 40);
    $this->otherDevice = str_repeat('N', 40);
    $this->mine = GuestUpload::storedPhoto($this->event, 'approved', GuestDevice::hashId($this->device));
    $this->minePending = GuestUpload::storedPhoto($this->event, 'pending', GuestDevice::hashId($this->device));
    $this->theirs = GuestUpload::storedPhoto($this->event, 'approved', GuestDevice::hashId($this->otherDevice));
});

function mineUrl(string $name, $event, ?Photo $photo = null, ?string $token = null): string
{
    $params = ['slug' => $event->slug, 't' => $token ?? $event->uploadToken()];

    return route($name, $photo ? $params + ['photo' => $photo->uuid] : $params);
}

function asDevice(string $device)
{
    return test()->withCookie(GuestDevice::COOKIE, $device)->withCredentials();
}

it('lists only the photos sent from this device, with status and same-origin URLs', function () {
    $json = asDevice($this->device)->getJson(mineUrl('guest.mine.index', $this->event))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->json('photos');

    expect(collect($json)->pluck('uuid')->sort()->values()->all())
        ->toBe(collect([$this->mine->uuid, $this->minePending->uuid])->sort()->values()->all());
    expect(collect($json)->pluck('status')->sort()->values()->all())->toBe(['approved', 'pending']);
    expect($json[0]['downloadUrl'])->toStartWith('/e/')->and($json[0])->not->toHaveKey('device_hash');
});

it('returns an empty list without a device cookie', function () {
    $this->getJson(mineUrl('guest.mine.index', $this->event))->assertOk()->assertJsonPath('photos', []);
});

it('downloads the original as an attachment, identical to the stored file', function () {
    $response = asDevice($this->device)->get(mineUrl('guest.mine.download', $this->event, $this->mine))->assertOk();

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment;')
        ->and($response->headers->get('Content-Type'))->toBe('image/jpeg')
        ->and(file_get_contents($response->baseResponse->getFile()->getPathname()))
        ->toBe(Storage::disk('local')->get($this->mine->original_path));
});

it('gives trial accounts\' guests the original without the demo band', function () {
    $trial = Account::factory()->trialInProgress()->create();
    $event = GuestUpload::liveEvent($trial);
    $photo = GuestUpload::storedPhoto($event, 'approved', GuestDevice::hashId($this->device));

    $response = asDevice($this->device)->get(mineUrl('guest.mine.download', $event, $photo))->assertOk();
    $bytes = file_get_contents($response->baseResponse->getFile()->getPathname());

    [$w, $h] = getimagesizefromstring($bytes);
    expect([$w, $h])->toBe([1080, 1350])
        ->and($bytes)->toBe(Storage::disk('local')->get($photo->original_path));
    // Nenhuma prévia com faixa foi gerada para isso.
    Storage::disk('local')->assertMissing($photo->previewPath());
});

it('serves the thumbnail inline for the own photo', function () {
    $response = asDevice($this->device)->get(mineUrl('guest.mine.thumb', $this->event, $this->mine))->assertOk();
    expect($response->headers->get('Content-Disposition'))->toStartWith('inline;');
});

it('returns 404 for a photo of another device, event or account', function (string $route) {
    // Outro aparelho.
    asDevice($this->device)->get(mineUrl($route, $this->event, $this->theirs))->assertNotFound();

    // Outro evento da mesma conta, mesmo aparelho: token do evento A com foto do evento B.
    $eventB = GuestUpload::liveEvent($this->account);
    $photoB = GuestUpload::storedPhoto($eventB, 'approved', GuestDevice::hashId($this->device));
    asDevice($this->device)->get(mineUrl($route, $this->event, $photoB))->assertNotFound();
    asDevice($this->device)->get(route($route, ['slug' => $this->event->slug, 'photo' => $this->mine->uuid, 't' => $eventB->uploadToken()]))->assertNotFound();

    // Outra conta.
    $other = GuestUpload::liveEvent(Account::factory()->active()->create());
    $photoC = GuestUpload::storedPhoto($other, 'approved', GuestDevice::hashId($this->device));
    asDevice($this->device)->get(mineUrl($route, $this->event, $photoC))->assertNotFound();

    // Token errado.
    asDevice($this->device)->get(mineUrl($route, $this->event, $this->mine, str_repeat('x', 64)))->assertNotFound();
})->with(['guest.mine.download', 'guest.mine.thumb']);

it('does not list photos of the same device in another event', function () {
    $eventB = GuestUpload::liveEvent($this->account);
    GuestUpload::storedPhoto($eventB, 'approved', GuestDevice::hashId($this->device));

    $json = asDevice($this->device)->getJson(mineUrl('guest.mine.index', $this->event))->json('photos');
    expect($json)->toHaveCount(2);
});

it('is unavailable (404) when the event is not live', function (string $state) {
    match ($state) {
        'ended' => $this->event->forceFill(['closed_at' => now()->subMinute()])->save(),
        'scheduled' => $this->event->forceFill(['starts_at' => now()->addHour(), 'ends_at' => now()->addHours(3)])->save(),
    };

    asDevice($this->device)->getJson(mineUrl('guest.mine.index', $this->event))->assertNotFound();
    asDevice($this->device)->get(mineUrl('guest.mine.download', $this->event, $this->mine))->assertNotFound();
    asDevice($this->device)->get(mineUrl('guest.mine.thumb', $this->event, $this->mine))->assertNotFound();
    asDevice($this->device)->deleteJson(mineUrl('guest.photos.destroy', $this->event, $this->mine))->assertNotFound();
})->with(['ended', 'scheduled']);

it('deletes an approved photo: files removed and the screen gets it in "removed"', function () {
    $first = $this->getJson(route('screen.photos', ['token' => $this->event->screenToken()]))->assertOk();
    expect(collect($first->json('photos'))->pluck('uuid'))->toContain($this->mine->uuid);
    $cursor = $first->json('cursor');

    asDevice($this->device)->deleteJson(mineUrl('guest.photos.destroy', $this->event, $this->mine))->assertNoContent();

    Storage::disk('local')->assertMissing([$this->mine->original_path, $this->mine->thumb_path]);

    $next = $this->getJson(route('screen.photos', ['token' => $this->event->screenToken(), 'after' => $cursor]))->assertOk();
    expect($next->json('removed'))->toContain($this->mine->uuid);

    $fresh = $this->getJson(route('screen.photos', ['token' => $this->event->screenToken()]))->json('photos');
    expect(collect($fresh)->pluck('uuid'))->not->toContain($this->mine->uuid);

    // Some da lista e não pode ser baixada nem excluída de novo.
    $list = asDevice($this->device)->getJson(mineUrl('guest.mine.index', $this->event))->json('photos');
    expect(collect($list)->pluck('uuid'))->not->toContain($this->mine->uuid);
    asDevice($this->device)->get(mineUrl('guest.mine.download', $this->event, $this->mine))->assertNotFound();
    asDevice($this->device)->deleteJson(mineUrl('guest.photos.destroy', $this->event, $this->mine))->assertNotFound();
});

it('does not let another device delete the photo', function () {
    asDevice($this->otherDevice)->deleteJson(mineUrl('guest.photos.destroy', $this->event, $this->mine))->assertNotFound();
    Storage::disk('local')->assertExists($this->mine->original_path);
});

it('does not count a guest-deleted photo against the event limit', function () {
    $event = GuestUpload::liveEvent(Account::factory()->trialInProgress()->create(), ['is_trial' => true]);
    $hash = GuestDevice::hashId($this->device);
    $photos = collect(range(1, config('photoparty.trial.max_photos')))->map(fn () => GuestUpload::storedPhoto($event, 'approved', $hash));

    $this->postJson(GuestUpload::storeUrl($event), GuestUpload::payload())->assertStatus(429);

    asDevice($this->device)->deleteJson(mineUrl('guest.photos.destroy', $event, $photos->first()))->assertNoContent();

    $this->withCookie(GuestDevice::COOKIE, $this->otherDevice)->postJson(GuestUpload::storeUrl($event), GuestUpload::payload())->assertCreated();
});

it('rate limits the "my photos" endpoints per device', function () {
    $url = mineUrl('guest.mine.index', $this->event);

    for ($i = 0; $i < 150; $i++) {
        asDevice($this->device)->getJson($url)->assertOk();
    }

    asDevice($this->device)->getJson($url)->assertStatus(429);
});
