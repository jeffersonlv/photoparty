<?php

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\GuestUpload;
use Tests\Support\PhotoFixtures;

/*
| Módulo 6: moderação ao vivo (routes/photos.php).
*/

beforeEach(function () {
    Storage::fake('local');

    $this->accountA = Account::factory()->active()->create();
    $this->accountB = Account::factory()->active()->create();
    $this->eventA = GuestUpload::liveEvent($this->accountA);
    $this->eventB = GuestUpload::liveEvent($this->accountB);

    $this->pendingA = PhotoFixtures::make($this->eventA, 'pending');
    $this->approvedA = PhotoFixtures::make($this->eventA, 'approved');
    $this->rejectedA = PhotoFixtures::make($this->eventA, 'rejected');
    $this->pendingB = PhotoFixtures::make($this->eventB, 'pending');

    // Usuários por último: com usuário autenticado o scope barra gravar em outra conta.
    $this->ownerA = ownerFor($this->accountA);
    $this->moderatorA = User::factory()->for($this->accountA)->moderator()->create();
});

function modUrl(Event $event, string $suffix = ''): string
{
    return '/events/'.$event->uuid.'/moderation'.$suffix;
}

function status(Photo $photo): string
{
    return Photo::withoutGlobalScopes()->findOrFail($photo->id)->status;
}

// ------------------------------------------------------------------ isolamento

it('hides the moderation of another account (404 everywhere)', function () {
    $this->actingAs($this->ownerA);

    $this->get(modUrl($this->eventB))->assertNotFound();
    $this->getJson(modUrl($this->eventB, '/photos'))->assertNotFound();
    $this->postJson(modUrl($this->eventB, '/batch'), ['action' => 'approve', 'photos' => [$this->pendingB->uuid]])->assertNotFound();
    $this->postJson(modUrl($this->eventB, '/photos/'.$this->pendingB->uuid.'/approve'))->assertNotFound();

    expect(status($this->pendingB))->toBe('pending');
});

it('does not let a photo of another event be moderated through my event', function () {
    $this->actingAs($this->ownerA);

    // Foto do evento B na rota do evento A (scopeBindings + global scope).
    $this->postJson(modUrl($this->eventA, '/photos/'.$this->pendingB->uuid.'/approve'))->assertNotFound();

    // Foto de outro evento da MESMA conta também não vale.
    $sibling = GuestUpload::liveEvent($this->accountA);
    $siblingPhoto = PhotoFixtures::make($sibling, 'pending');
    $this->postJson(modUrl($this->eventA, '/photos/'.$siblingPhoto->uuid.'/approve'))->assertNotFound();

    expect(status($this->pendingB))->toBe('pending')->and(status($siblingPhoto))->toBe('pending');
});

it('requires login and an account', function () {
    $this->get(modUrl($this->eventA))->assertRedirect(route('login'));
    $this->getJson(modUrl($this->eventA, '/photos'))->assertUnauthorized();

    $this->actingAs(User::factory()->admin()->create())->get(modUrl($this->eventA))->assertNotFound();
});

it('ignores uuids of other events in a batch and only changes the own ones', function () {
    $sibling = PhotoFixtures::make(GuestUpload::liveEvent($this->accountA), 'pending');

    $this->actingAs($this->ownerA)
        ->postJson(modUrl($this->eventA, '/batch'), [
            'action' => 'approve',
            'photos' => [$this->pendingA->uuid, $this->pendingB->uuid, $sibling->uuid, (string) Str::uuid()],
        ])
        ->assertOk()
        ->assertJson(['changed' => 1, 'ignored' => 3]);

    expect(status($this->pendingA))->toBe('approved')
        ->and(status($this->pendingB))->toBe('pending')
        ->and(status($sibling))->toBe('pending');

    expect(AuditLog::where('action', 'photo.approved')->count())->toBe(1);
});

// ------------------------------------------------------------------- permissão

it('lets owner and moderator open the screen and list photos', function (string $who) {
    $this->actingAs($this->{$who})->get(modUrl($this->eventA))
        ->assertOk()
        ->assertSee('id="pp-moderation"', false)
        ->assertSee('"defaultTab":"pending"', false);

    $json = $this->getJson(modUrl($this->eventA, '/photos?status=pending'))
        ->assertOk()
        ->assertJsonPath('counts', ['pending' => 1, 'approved' => 1, 'rejected' => 1])
        ->assertJsonCount(1, 'photos')
        ->assertJsonPath('photos.0.uuid', $this->pendingA->uuid)
        ->assertJsonPath('photos.0.thumb', '/media/'.$this->pendingA->uuid.'/thumb')
        ->json();

    expect(json_encode($json))->not->toContain($this->pendingB->uuid)->not->toContain('tenants/');

    // A miniatura abre para quem modera (media.show + policy).
    GuestUpload::storedPhoto($this->eventA, 'pending');
    $this->get($json['photos'][0]['thumb'])->assertStatus(404); // sem arquivo no disco fake: 404, mas não 403
})->with(['ownerA', 'moderatorA']);

it('lists each tab and refuses an unknown status', function () {
    $this->actingAs($this->moderatorA);

    $this->getJson(modUrl($this->eventA, '/photos?status=approved'))->assertJsonPath('photos.0.uuid', $this->approvedA->uuid);
    $this->getJson(modUrl($this->eventA, '/photos?status=rejected'))->assertJsonPath('photos.0.uuid', $this->rejectedA->uuid);
    $this->getJson(modUrl($this->eventA, '/photos?status=all'))->assertStatus(422);
});

it('opens on the approved tab for post-moderation events', function () {
    $post = GuestUpload::liveEvent($this->accountA, ['moderation' => 'post']);

    $this->actingAs($this->ownerA)->get(modUrl($post))->assertSee('"defaultTab":"approved"', false);
});

it('blocks actions on expired events', function () {
    $expired = GuestUpload::liveEvent($this->accountA, ['expires_at' => now()->subDay()]);
    $photo = PhotoFixtures::make($expired, 'pending');

    $this->actingAs($this->ownerA)
        ->postJson(modUrl($expired, '/batch'), ['action' => 'approve', 'photos' => [$photo->uuid]])
        ->assertForbidden();

    expect(status($photo))->toBe('pending');
});

// ------------------------------------------------------------------- transições

it('approves and rejects a single photo and audits it', function () {
    $this->actingAs($this->moderatorA);

    $this->postJson(modUrl($this->eventA, '/photos/'.$this->pendingA->uuid.'/approve'))->assertOk()->assertJson(['changed' => 1]);

    $approved = PhotoFixtures::raw($this->pendingA);
    expect($approved->status)->toBe('approved')->and($approved->approved_at)->not->toBeNull();

    $other = PhotoFixtures::make($this->eventA, 'pending');
    $this->postJson(modUrl($this->eventA, '/photos/'.$other->uuid.'/reject'))->assertOk();
    expect(status($other))->toBe('rejected');

    $log = AuditLog::where('action', 'photo.approved')->latest('id')->first();
    expect($log->user_id)->toBe($this->moderatorA->id)
        ->and($log->account_id)->toBe($this->accountA->id)
        ->and($log->subject_id)->toBe($this->pendingA->id)
        ->and($log->meta['event'])->toBe($this->eventA->uuid)
        ->and(AuditLog::where('action', 'photo.rejected')->count())->toBe(1);
});

it('removes an approved photo from the screen and brings it back', function () {
    $this->actingAs($this->ownerA);
    $screen = route('screen.photos', ['token' => $this->eventA->screenToken()]);

    $first = $this->getJson($screen)->json();
    expect(array_column($first['photos'], 'uuid'))->toBe([$this->approvedA->uuid]);

    $this->postJson(modUrl($this->eventA, '/photos/'.$this->approvedA->uuid.'/reject'))->assertOk();

    expect(status($this->approvedA))->toBe('rejected');
    $afterRemove = $this->getJson($screen.'?after='.$first['cursor'])->json();
    expect($afterRemove['removed'])->toContain($this->approvedA->uuid);
    expect(array_column($this->getJson($screen)->json('photos'), 'uuid'))->toBe([]);

    $this->postJson(modUrl($this->eventA, '/photos/'.$this->approvedA->uuid.'/approve'))->assertOk();

    $afterRestore = $this->getJson($screen.'?after='.$first['cursor'])->json();
    expect(array_column($afterRestore['photos'], 'uuid'))->toBe([$this->approvedA->uuid])
        ->and($afterRestore['removed'])->not->toContain($this->approvedA->uuid);

    expect(AuditLog::where('action', 'photo.removed_from_screen')->count())->toBe(1)
        ->and(AuditLog::where('action', 'photo.restored_to_screen')->count())->toBe(1);
});

it('approves and rejects in batch, skipping photos already in the target state', function () {
    $extra = PhotoFixtures::make($this->eventA, 'pending');

    $this->actingAs($this->ownerA)
        ->postJson(modUrl($this->eventA, '/batch'), [
            'action' => 'approve',
            'photos' => [$this->pendingA->uuid, $extra->uuid, $this->approvedA->uuid, $this->rejectedA->uuid],
        ])
        ->assertOk()
        ->assertJson(['changed' => 3, 'ignored' => 1]);

    expect(status($this->pendingA))->toBe('approved')->and(status($extra))->toBe('approved')->and(status($this->rejectedA))->toBe('approved');
    expect(AuditLog::whereIn('action', ['photo.approved', 'photo.restored_to_screen'])->count())->toBe(3);

    $this->postJson(modUrl($this->eventA, '/batch'), ['action' => 'reject', 'photos' => [$this->pendingA->uuid, $extra->uuid]])
        ->assertOk()->assertJson(['changed' => 2]);

    expect(status($this->pendingA))->toBe('rejected');
    expect(AuditLog::where('action', 'photo.removed_from_screen')->count())->toBe(2);
});

it('validates the batch payload', function () {
    $this->actingAs($this->ownerA);

    $this->postJson(modUrl($this->eventA, '/batch'), ['action' => 'delete', 'photos' => [$this->pendingA->uuid]])->assertStatus(422);
    $this->postJson(modUrl($this->eventA, '/batch'), ['action' => 'approve', 'photos' => []])->assertStatus(422);
    $this->postJson(modUrl($this->eventA, '/batch'), ['action' => 'approve', 'photos' => ['1', '2']])->assertStatus(422);
    $this->postJson(modUrl($this->eventA, '/batch'), ['action' => 'approve'])->assertStatus(422);
    $this->postJson(modUrl($this->eventA, '/photos/'.$this->pendingA->uuid.'/delete'))->assertNotFound();

    expect(status($this->pendingA))->toBe('pending');
});

// ------------------------------------------------------------------ evento / UI

it('shows the moderation and screen links on the event page, the screen link only to owners', function () {
    $screenPath = '/screen/'.$this->eventA->screenToken();

    $this->actingAs($this->ownerA)->get(route('events.show', $this->eventA))
        ->assertOk()
        ->assertSee(modUrl($this->eventA), false)
        ->assertSee('href="'.$screenPath.'" target="_blank"', false);

    $this->actingAs($this->moderatorA)->get(route('events.show', $this->eventA))
        ->assertOk()
        ->assertSee(modUrl($this->eventA), false)
        ->assertDontSee($screenPath, false);
});

it('does not show the screen link for drafts', function () {
    $draft = Event::factory()->for($this->accountA)->create();

    $this->actingAs($this->ownerA)->get(route('events.show', $draft))
        ->assertOk()
        ->assertDontSee('href="/screen/', false);
});
