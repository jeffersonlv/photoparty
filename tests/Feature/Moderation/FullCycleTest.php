<?php

use App\Models\Account;
use App\Models\Photo;
use Illuminate\Support\Facades\Storage;
use Tests\Support\GuestUpload;

/*
| Ciclo principal de ponta a ponta: convidado envia -> organizador modera -> foto aparece no telão.
*/

it('runs the whole cycle with pre-moderation', function () {
    Storage::fake('local');

    $account = Account::factory()->active()->create();
    $event = GuestUpload::liveEvent($account, ['moderation' => 'pre']);
    $owner = ownerFor($account);

    $screen = route('screen.photos', ['token' => $event->screenToken()]);

    // 1. Convidado envia (sem login).
    $uuid = $this->postJson(GuestUpload::storeUrl($event), GuestUpload::payload())
        ->assertCreated()
        ->assertJsonPath('status', 'pending')
        ->json('uuid');

    // 2. Telão ainda não vê.
    $before = $this->getJson($screen)->assertOk()->json();
    expect($before['photos'])->toBe([]);

    // 3. Organizador vê na fila e aprova.
    $this->actingAs($owner);
    $this->getJson("/events/{$event->uuid}/moderation/photos?status=pending")->assertJsonPath('photos.0.uuid', $uuid);
    $this->get("/media/{$uuid}/thumb")->assertOk();
    $this->postJson("/events/{$event->uuid}/moderation/batch", ['action' => 'approve', 'photos' => [$uuid]])->assertOk()->assertJson(['changed' => 1]);

    // 4. Telão recebe a foto com URL assinada que funciona.
    $after = $this->getJson($screen)->assertOk()->json();
    expect($after['photos'])->toHaveCount(1)->and($after['photos'][0]['uuid'])->toBe($uuid);
    $this->get($after['photos'][0]['url'])->assertOk()->assertHeader('Content-Type', 'image/jpeg');

    // 5. Organizador tira do telão: some e vem em "removed".
    $this->postJson("/events/{$event->uuid}/moderation/batch", ['action' => 'reject', 'photos' => [$uuid]])->assertOk();
    $gone = $this->getJson($screen.'?after='.$after['cursor'])->json();
    expect($gone['removed'])->toBe([$uuid]);
    $this->get($after['photos'][0]['url'])->assertNotFound(); // assinada, mas já não está aprovada

    expect(Photo::withoutGlobalScopes()->where('uuid', $uuid)->value('status'))->toBe('rejected');
});

it('shows post-moderation uploads on the screen right away', function () {
    Storage::fake('local');

    $event = GuestUpload::liveEvent(Account::factory()->active()->create(), ['moderation' => 'post']);

    $uuid = $this->postJson(GuestUpload::storeUrl($event), GuestUpload::payload())->assertCreated()->json('uuid');

    $this->getJson(route('screen.photos', ['token' => $event->screenToken()]))->assertJsonPath('photos.0.uuid', $uuid);
});
