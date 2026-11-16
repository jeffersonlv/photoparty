<?php

use App\Enums\AccountStatus;
use App\Enums\EventStatus;
use App\Models\Account;
use App\Models\Event;

/*
| Regras do teste grátis (SPEC 7), sempre validadas no servidor:
| - evento de teste com no máximo 2 h;
| - período de teste da conta: 3 h a partir da publicação;
| - 20 fotos por evento de teste;
| - criar/publicar só em trial (com teste disponível) ou active.
*/

beforeEach(function () {
    // Precisão de segundo: o banco não guarda microssegundos.
    $this->freezeSecond();
    $this->account = Account::factory()->trial()->create();
    $this->owner = ownerFor($this->account);
});

it('creates a trial event of up to 2 hours', function () {
    $this->actingAs($this->owner)
        ->post(route('events.store'), eventPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $event = Event::withoutGlobalScopes()->sole();

    expect($event->is_trial)->toBeTrue()
        ->and($event->account_id)->toBe($this->account->id)
        ->and($event->status)->toBe(EventStatus::Draft)
        ->and($event->photoLimit())->toBe(20);
});

it('rejects a trial event longer than 2 hours', function () {
    $startsAt = now()->addHour();

    $this->actingAs($this->owner)
        ->post(route('events.store'), eventPayload([
            'starts_at' => $startsAt->format('Y-m-d\TH:i'),
            'ends_at' => $startsAt->copy()->addHours(2)->addMinute()->format('Y-m-d\TH:i'),
        ]))
        ->assertSessionHasErrors('ends_at');

    expect(Event::withoutGlobalScopes()->count())->toBe(0);
});

it('rejects an event that ends before it starts', function () {
    $this->actingAs($this->owner)
        ->post(route('events.store'), eventPayload([
            'starts_at' => now()->addHours(2)->format('Y-m-d\TH:i'),
            'ends_at' => now()->addHour()->format('Y-m-d\TH:i'),
        ]))
        ->assertSessionHasErrors('ends_at');
});

it('allows only one event during the trial', function () {
    Event::factory()->for($this->account)->trial()->create();

    $this->actingAs($this->owner)
        ->post(route('events.store'), eventPayload(['name' => 'Segundo']))
        ->assertRedirect(route('events.index'))
        ->assertSessionHas('error');

    expect(Event::withoutGlobalScopes()->where('name', 'Segundo')->exists())->toBeFalse();
});

it('starts the 3-hour account trial window when the trial event is published', function () {
    $event = Event::factory()->for($this->account)->trial()->create();

    $this->actingAs($this->owner)
        ->post(route('events.publish', $event))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('events.show', $event));

    $event->refresh();
    $this->account->refresh();

    expect($event->published_at)->not->toBeNull()
        ->and($event->status)->toBe(EventStatus::Scheduled)
        ->and($event->expires_at->equalTo($event->ends_at->copy()->addDays(7)))->toBeTrue()
        ->and($this->account->trial_used_at->equalTo(now()))->toBeTrue()
        ->and($this->account->trial_ends_at->equalTo(now()->addHours(3)))->toBeTrue()
        ->and($this->account->hasTrialAvailable())->toBeFalse();
});

it('refuses to publish a trial event that would end after the 3-hour window', function () {
    // Duração de 2 h (válida), mas termina 4 h depois de agora.
    $event = Event::factory()->for($this->account)->create([
        'is_trial' => true,
        'starts_at' => now()->addHours(2),
        'ends_at' => now()->addHours(4),
    ]);

    $this->actingAs($this->owner)
        ->post(route('events.publish', $event))
        ->assertSessionHasErrors('ends_at');

    expect($event->refresh()->isDraft())->toBeTrue()
        ->and($this->account->refresh()->trial_used_at)->toBeNull();
});

it('refuses to publish an event whose end time has passed', function () {
    $event = Event::factory()->for($this->account)->create([
        'is_trial' => true,
        'starts_at' => now()->subHours(2),
        'ends_at' => now()->subHour(),
    ]);

    $this->actingAs($this->owner)
        ->post(route('events.publish', $event))
        ->assertSessionHasErrors('ends_at');

    expect($event->refresh()->isDraft())->toBeTrue();
});

it('blocks new events after the trial has been used', function () {
    $event = Event::factory()->for($this->account)->trial()->create();

    $this->actingAs($this->owner)->post(route('events.publish', $event));

    // fresh(): numa requisição real o usuário e a conta são recarregados.
    $this->actingAs($this->owner->fresh())
        ->get(route('events.create'))
        ->assertRedirect(route('events.index'))
        ->assertSessionHas('error');
});

it('keeps an edited trial event inside the account trial window', function () {
    $event = Event::factory()->for($this->account)->trial()->create();
    $this->actingAs($this->owner)->post(route('events.publish', $event));

    // Janela termina em now()+3h; mover o fim para now()+3h30 é recusado.
    $this->actingAs($this->owner->fresh())
        ->put(route('events.update', $event), eventPayload([
            'starts_at' => now()->addHours(2)->format('Y-m-d\TH:i'),
            'ends_at' => now()->addHours(3)->addMinutes(30)->format('Y-m-d\TH:i'),
        ]))
        ->assertSessionHasErrors('ends_at');
});

it('blocks creating and publishing for accounts outside trial/active', function (AccountStatus $status) {
    $account = Account::factory()->status($status)->create();
    $draft = Event::factory()->for($account)->create();
    $owner = ownerFor($account);

    $this->actingAs($owner)
        ->get(route('events.create'))
        ->assertRedirect(route('events.index'))
        ->assertSessionHas('error');

    $this->actingAs($owner)
        ->post(route('events.store'), eventPayload())
        ->assertRedirect(route('events.index'))
        ->assertSessionHas('error');

    $this->actingAs($owner)
        ->post(route('events.publish', $draft))
        ->assertRedirect(route('events.index'))
        ->assertSessionHas('error');

    expect($draft->refresh()->isDraft())->toBeTrue()
        ->and(Event::withoutGlobalScopes()->where('account_id', $account->id)->count())->toBe(1);
})->with([
    'trial_ended' => AccountStatus::TrialEnded,
    'past_due' => AccountStatus::PastDue,
    'cancelled' => AccountStatus::Cancelled,
]);

it('lets active accounts create longer, non-trial events', function () {
    $account = Account::factory()->active()->create();
    $owner = ownerFor($account);
    $startsAt = now()->addDay();

    $this->actingAs($owner)
        ->post(route('events.store'), eventPayload([
            'starts_at' => $startsAt->format('Y-m-d\TH:i'),
            'ends_at' => $startsAt->copy()->addHours(6)->format('Y-m-d\TH:i'),
        ]))
        ->assertSessionHasNoErrors();

    $event = Event::withoutGlobalScopes()->where('account_id', $account->id)->sole();

    expect($event->is_trial)->toBeFalse()
        ->and($event->photoLimit())->toBe($account->plan->max_photos_per_event);
});

it('ends the account trial after 3 hours via the scheduled command', function () {
    $event = Event::factory()->for($this->account)->trial()->create();
    $this->actingAs($this->owner)->post(route('events.publish', $event));

    $this->travel(2)->hours();
    $this->artisan('accounts:expire-trials')->assertSuccessful();
    expect($this->account->refresh()->status)->toBe(AccountStatus::Trial);

    $this->travel(61)->minutes();
    $this->artisan('accounts:expire-trials')->assertSuccessful();
    expect($this->account->refresh()->status)->toBe(AccountStatus::TrialEnded)
        ->and($this->account->canManageEvents())->toBeFalse();
});
