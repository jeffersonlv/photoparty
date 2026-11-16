<?php

use App\Http\Controllers\Screen\ScreenController;
use App\Models\Account;
use App\Models\Event;
use App\Models\Photo;
use Illuminate\Support\Facades\Storage;
use Tests\Support\GuestUpload;
use Tests\Support\PhotoFixtures;

/*
| Módulo 5: polling do telão (/screen/{token}/photos).
*/

beforeEach(function () {
    Storage::fake('local');
    $this->accountA = Account::factory()->active()->create();
    $this->accountB = Account::factory()->active()->create();
    $this->eventA = GuestUpload::liveEvent($this->accountA);
    $this->eventB = GuestUpload::liveEvent($this->accountB);
});

function pollUrl(Event $event, ?string $after = null, ?string $token = null): string
{
    return route('screen.photos', ['token' => $token ?? $event->screenToken(), 'after' => $after]);
}

it('lists only approved photos of the event of the token', function () {
    $approved = GuestUpload::storedPhoto($this->eventA, 'approved');
    GuestUpload::storedPhoto($this->eventA, 'pending');
    GuestUpload::storedPhoto($this->eventA, 'rejected');
    $foreign = GuestUpload::storedPhoto($this->eventB, 'approved');

    $json = $this->getJson(pollUrl($this->eventA))
        ->assertOk()
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertJsonCount(1, 'photos')
        ->assertJsonPath('photos.0.uuid', $approved->uuid)
        ->assertJsonPath('status', 'live')
        ->assertJsonPath('has_more', false)
        ->assertJsonPath('ttl', 600)
        ->json();

    expect(json_encode($json))->not->toContain($foreign->uuid);

    // O token do evento B só enxerga o evento B.
    $this->getJson(pollUrl($this->eventB))->assertJsonPath('photos.0.uuid', $foreign->uuid)->assertJsonCount(1, 'photos');
});

it('is not affected by a logged-in organizer of another account', function () {
    $photo = GuestUpload::storedPhoto($this->eventA, 'approved');
    $ownerB = ownerFor($this->accountB);

    $this->actingAs($ownerB)->getJson(pollUrl($this->eventA))
        ->assertOk()
        ->assertJsonPath('photos.0.uuid', $photo->uuid);
});

it('returns signed original URLs that actually serve the image', function () {
    $photo = GuestUpload::storedPhoto($this->eventA, 'approved');

    $url = $this->getJson(pollUrl($this->eventA))->json('photos.0.url');

    expect($url)->toContain('/media/signed/'.$photo->uuid.'/original')->toContain('signature=')->toContain('expires=');

    $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');

    // Assinatura adulterada não vale.
    $this->get($url.'x')->assertForbidden();
});

it('falls back to thumbnails when the trial of the account has ended', function () {
    GuestUpload::storedPhoto($this->eventA, 'approved');
    $this->accountA->forceFill(['status' => 'trial_ended'])->save();

    $url = $this->getJson(pollUrl($this->eventA))->json('photos.0.url');

    expect($url)->toContain('/thumb');
    $this->get($url)->assertOk();
});

it('pages with a stable cursor and the limit of 50', function () {
    $photos = PhotoFixtures::approved($this->eventA, 55);

    $first = $this->getJson(pollUrl($this->eventA))->assertOk()->json();

    expect($first['photos'])->toHaveCount(ScreenController::LIMIT)
        ->and($first['has_more'])->toBeTrue()
        ->and($first['photos'][0]['uuid'])->toBe($photos[0]->uuid)
        ->and($first['photos'][49]['uuid'])->toBe($photos[49]->uuid)
        ->and($first['cursor'])->toEndWith('_'.$photos[49]->uuid);

    $second = $this->getJson(pollUrl($this->eventA, $first['cursor']))->assertOk()->json();

    expect($second['photos'])->toHaveCount(5)
        ->and($second['has_more'])->toBeFalse()
        ->and(array_column($second['photos'], 'uuid'))->toBe(array_map(fn ($p) => $p->uuid, array_slice($photos, 50)));

    // Sem nada novo: lista vazia e o cursor não anda.
    $third = $this->getJson(pollUrl($this->eventA, $second['cursor']))->json();

    expect($third['photos'])->toBe([])->and($third['cursor'])->toBe($second['cursor']);

    // Foto nova depois do cursor aparece sozinha.
    $new = PhotoFixtures::make($this->eventA, 'approved', now());
    $fourth = $this->getJson(pollUrl($this->eventA, $third['cursor']))->json();

    expect(array_column($fourth['photos'], 'uuid'))->toBe([$new->uuid]);
});

it('orders photos approved in the same instant by id without losing any', function () {
    $at = now()->subMinute();
    $a = PhotoFixtures::make($this->eventA, 'approved', $at);
    $b = PhotoFixtures::make($this->eventA, 'approved', $at);
    $c = PhotoFixtures::make($this->eventA, 'approved', $at);

    $first = $this->getJson(pollUrl($this->eventA))->json();
    expect(array_column($first['photos'], 'uuid'))->toBe([$a->uuid, $b->uuid, $c->uuid]);

    // Cursor no meio do empate: só o que vem depois.
    $cursor = $at->format('U').$at->format('u').'_'.$a->uuid;
    $rest = $this->getJson(pollUrl($this->eventA, $cursor))->json();
    expect(array_column($rest['photos'], 'uuid'))->toBe([$b->uuid, $c->uuid]);
});

it('reports rejected photos in removed and stops listing them', function () {
    $keep = PhotoFixtures::make($this->eventA, 'approved', now()->subMinute());
    $drop = PhotoFixtures::make($this->eventA, 'approved', now()->subSeconds(30));

    $first = $this->getJson(pollUrl($this->eventA))->json();
    expect($first['photos'])->toHaveCount(2)->and($first['removed'])->toBe([]);

    $drop->forceFill(['status' => 'rejected', 'approved_at' => null])->save();

    $second = $this->getJson(pollUrl($this->eventA, $first['cursor']))->assertOk()->json();

    expect($second['photos'])->toBe([])
        ->and($second['removed'])->toBe([$drop->uuid])
        ->and(json_encode($second))->not->toContain($keep->uuid);

    // E de uma lista nova (sem cursor) ela simplesmente não existe.
    $fresh = $this->getJson(pollUrl($this->eventA))->json();
    expect(array_column($fresh['photos'], 'uuid'))->toBe([$keep->uuid]);
});

it('does not leak removed photos of other events', function () {
    PhotoFixtures::make($this->eventA, 'approved', now()->subMinute());
    $foreign = PhotoFixtures::make($this->eventB, 'rejected');

    $cursor = $this->getJson(pollUrl($this->eventA))->json('cursor');
    $json = $this->getJson(pollUrl($this->eventA, $cursor))->json();

    expect($json['removed'])->toBe([])->and(json_encode($json))->not->toContain($foreign->uuid);
});

it('rejects a malformed cursor', function () {
    $this->getJson(pollUrl($this->eventA, 'abc'))->assertStatus(422);
    $this->getJson(pollUrl($this->eventA, '1_'.str_repeat('z', 36)))->assertStatus(422);
});

it('returns 404 for revoked tokens, drafts and expired events', function () {
    $old = $this->eventA->screenToken();
    $this->eventA->issueScreenToken();
    $this->eventA->save();

    $this->getJson(pollUrl($this->eventA, null, $old))->assertNotFound();
    $this->getJson(pollUrl($this->eventA))->assertOk();

    $draft = Event::factory()->for($this->accountA)->create();
    $this->getJson(pollUrl($draft))->assertNotFound();

    $expired = GuestUpload::liveEvent($this->accountA, ['expires_at' => now()->subDay()]);
    $this->getJson(pollUrl($expired))->assertNotFound();
});

it('answers with an empty list while the event has not started and keeps serving after it ended', function () {
    $scheduled = Event::factory()->for($this->accountA)->scheduled()->create();
    $this->getJson(pollUrl($scheduled))->assertOk()->assertJsonPath('status', 'scheduled')->assertJsonPath('photos', []);

    $ended = GuestUpload::liveEvent($this->accountA, ['starts_at' => now()->subHours(5), 'ends_at' => now()->subHour()]);
    $photo = GuestUpload::storedPhoto($ended, 'approved');

    $this->getJson(pollUrl($ended))->assertOk()->assertJsonPath('status', 'ended')->assertJsonPath('photos.0.uuid', $photo->uuid);
});

it('throttles polling', function () {
    $url = pollUrl($this->eventA);

    for ($i = 0; $i < 120; $i++) {
        $this->getJson($url)->assertOk();
    }

    $this->getJson($url)->assertStatus(429);
});

it('does not expose internal ids or paths', function () {
    $photo = GuestUpload::storedPhoto($this->eventA, 'approved');

    $body = $this->getJson(pollUrl($this->eventA))->getContent();

    expect($body)->not->toContain('original_path')->not->toContain('tenants/')->not->toContain('account_id')
        ->and(Photo::withoutGlobalScopes()->find($photo->id))->not->toBeNull();
});
