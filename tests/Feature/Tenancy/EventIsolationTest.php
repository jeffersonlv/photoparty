<?php

use App\Models\Account;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/*
| Isolamento conta A x conta B (CLAUDE.md, regra 1): recurso de outra conta => 404.
*/

beforeEach(function () {
    $this->accountA = Account::factory()->active()->create();
    $this->accountB = Account::factory()->active()->create();

    $this->ownerA = ownerFor($this->accountA);
    $this->ownerB = ownerFor($this->accountB);

    $this->eventA = Event::factory()->for($this->accountA)->create(['name' => 'Evento da Conta A']);
    $this->eventB = Event::factory()->for($this->accountB)->create(['name' => 'Evento da Conta B']);
    $this->liveEventB = Event::factory()->for($this->accountB)->live()->create(['name' => 'Ao vivo da Conta B']);
});

it('lists only the events of the authenticated account', function () {
    $this->actingAs($this->ownerA)
        ->get(route('events.index'))
        ->assertOk()
        ->assertSee('Evento da Conta A')
        ->assertDontSee('Evento da Conta B')
        ->assertDontSee('Ao vivo da Conta B');

    $this->actingAs($this->ownerA)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Evento da Conta B');
});

it('returns 404 when accessing an event of another account', function (string $method, string $routeName, array $extra, string $target) {
    $event = $this->{$target};
    $before = $event->fresh()->getAttributes();

    $this->actingAs($this->ownerA)
        ->call($method, route($routeName, [$event, ...$extra]), eventPayload(['name' => 'Invadido']))
        ->assertNotFound();

    // Nada mudou no evento da conta B.
    expect(Event::withoutGlobalScopes()->find($event->id)?->getAttributes())->toEqual($before);
})->with([
    'view' => ['GET', 'events.show', [], 'eventB'],
    'edit form' => ['GET', 'events.edit', [], 'eventB'],
    'update' => ['PUT', 'events.update', [], 'eventB'],
    'delete' => ['DELETE', 'events.destroy', [], 'eventB'],
    'publish' => ['POST', 'events.publish', [], 'eventB'],
    'close' => ['POST', 'events.close', [], 'liveEventB'],
    'regenerate screen token' => ['POST', 'events.screen-token', [], 'eventB'],
    'download QR png' => ['GET', 'events.qr', ['png'], 'eventB'],
    'download QR svg' => ['GET', 'events.qr', ['svg'], 'eventB'],
]);

it('lets the owner access their own event', function () {
    $this->actingAs($this->ownerA)->get(route('events.show', $this->eventA))->assertOk()->assertSee('Evento da Conta A');
    $this->actingAs($this->ownerA)->get(route('events.qr', [$this->eventA, 'png']))->assertOk();
});

it('denies with 404 at the policy level even if the global scope is bypassed', function (string $ability) {
    $response = Gate::forUser($this->ownerA)->inspect($ability, $this->eventB);

    expect($response->denied())->toBeTrue()
        ->and($response->status())->toBe(404);
})->with(['view', 'update', 'delete', 'publish', 'close', 'manageTokens', 'downloadQr']);

it('applies the account global scope to queries', function () {
    $this->actingAs($this->ownerA);

    expect(Event::count())->toBe(1)
        ->and(Event::find($this->eventB->id))->toBeNull()
        ->and(Event::where('uuid', $this->eventB->uuid)->exists())->toBeFalse()
        ->and(Event::withoutGlobalScopes()->count())->toBe(3);
});

it('fills account_id from the authenticated user and ignores account_id from the request', function () {
    $this->actingAs($this->ownerA)
        ->post(route('events.store'), eventPayload([
            'name' => 'Tentativa de injeção',
            'account_id' => $this->accountB->id,
            'is_trial' => '1',
            'status' => 'live',
        ]))
        ->assertRedirect();

    $created = Event::withoutGlobalScopes()->where('name', 'Tentativa de injeção')->sole();

    expect($created->account_id)->toBe($this->accountA->id)
        ->and($created->is_trial)->toBeFalse()
        ->and($created->isDraft())->toBeTrue();
});

it('refuses to save a record into another account while authenticated', function () {
    $this->actingAs($this->ownerA);

    $event = new Event(eventPayload());
    $event->account_id = $this->accountB->id;
    $event->save();
})->throws(LogicException::class);

it('uses random UUID v4 public keys in URLs', function () {
    expect($this->eventA->uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/')
        ->and(route('events.show', $this->eventA))->toContain($this->eventA->uuid)
        ->and(route('events.show', $this->eventA))->not->toEndWith('/'.$this->eventA->id);
});

it('lets a moderator view but not manage events of their own account', function () {
    $moderator = User::factory()->for($this->accountA)->moderator()->create();

    $this->actingAs($moderator)->get(route('events.show', $this->eventA))->assertOk();
    $this->actingAs($moderator)->post(route('events.screen-token', $this->eventA))->assertForbidden();
    $this->actingAs($moderator)->post(route('events.publish', $this->eventA))->assertForbidden();
    $this->actingAs($moderator)->get(route('events.show', $this->eventB))->assertNotFound();
});

it('keeps admins (without account) out of the organizer area', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('events.index'))->assertNotFound();
});

it('requires authentication and a verified email', function () {
    $this->get(route('events.index'))->assertRedirect(route('login'));

    $unverified = User::factory()->for($this->accountA)->unverified()->create();

    $this->actingAs($unverified)->get(route('events.index'))->assertRedirect(route('verification.notice'));
});
