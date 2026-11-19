<?php

use App\Enums\AccountStatus;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('lists all accounts with text search', function () {
    $a = Account::factory()->create(['name' => 'Estudio Alfa']);
    $b = Account::factory()->active()->create(['name' => 'Cabine Beta']);
    User::factory()->for($b)->create(['email' => 'beta-owner@example.test']);

    $this->actingAs($this->admin)->get('/admin/accounts')
        ->assertOk()->assertSee('Estudio Alfa')->assertSee('Cabine Beta');

    $this->actingAs($this->admin)->get('/admin/accounts?q=Alfa')
        ->assertOk()->assertSee('Estudio Alfa')->assertDontSee('Cabine Beta');

    $this->actingAs($this->admin)->get('/admin/accounts?q=beta-owner')
        ->assertOk()->assertSee('Cabine Beta')->assertDontSee('Estudio Alfa');

});

it('shows account usage across all tenants', function () {
    $account = Account::factory()->create(['name' => 'Conta Uso']);
    $event = Event::factory()->create(['account_id' => $account->id]);
    DB::table('photos')->insert([
        ['uuid' => (string) str()->uuid(), 'account_id' => $account->id, 'event_id' => $event->id, 'device_hash' => str_repeat('a', 64), 'bytes' => 2097152, 'created_at' => now(), 'updated_at' => now()],
        ['uuid' => (string) str()->uuid(), 'account_id' => $account->id, 'event_id' => $event->id, 'device_hash' => str_repeat('b', 64), 'bytes' => 1048576, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->actingAs($this->admin)->get("/admin/accounts/{$account->uuid}")
        ->assertOk()
        ->assertSee('Conta Uso')
        ->assertSee('3,0 MB')
        ->assertSee($event->name);
});

it('changes the plan and records the audit log', function () {
    $account = Account::factory()->create();
    $plan = Plan::factory()->create();

    $this->actingAs($this->admin)->put("/admin/accounts/{$account->uuid}/plan", ['plan_id' => $plan->id])
        ->assertRedirect();

    expect($account->fresh()->plan_id)->toBe($plan->id);

    $log = AuditLog::query()->where('action', 'account.plan_changed')->first();
    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($this->admin->id)
        ->and($log->account_id)->toBe($account->id)
        ->and($log->meta['to'])->toBe($plan->id);

    $this->actingAs($this->admin)->put("/admin/accounts/{$account->uuid}/plan", ['plan_id' => 9999])
        ->assertSessionHasErrors('plan_id');
});

it('activates and suspends accounts', function () {
    $account = Account::factory()->create();

    $this->actingAs($this->admin)->put("/admin/accounts/{$account->uuid}/status", ['status' => 'active'])->assertRedirect();
    expect($account->fresh()->status)->toBe(AccountStatus::Active);

    $this->actingAs($this->admin)->put("/admin/accounts/{$account->uuid}/status", ['status' => 'past_due'])->assertRedirect();
    expect($account->fresh()->status)->toBe(AccountStatus::PastDue);

    expect(AuditLog::query()->where('action', 'account.status_changed')->count())->toBe(2);

    $this->actingAs($this->admin)->put("/admin/accounts/{$account->uuid}/status", ['status' => 'hacked'])
        ->assertSessionHasErrors('status');
    expect($account->fresh()->status)->toBe(AccountStatus::PastDue);
});

it('extends a running trial by N hours and reopens an ended one', function () {
    $running = Account::factory()->trialInProgress()->create();
    $before = $running->trial_ends_at->copy();

    $this->actingAs($this->admin)->post("/admin/accounts/{$running->uuid}/extend-trial", ['hours' => 5])->assertRedirect();
    expect($running->fresh()->trial_ends_at->equalTo($before->addHours(5)))->toBeTrue();

    $ended = Account::factory()->trialEnded()->create();
    $this->actingAs($this->admin)->post("/admin/accounts/{$ended->uuid}/extend-trial", ['hours' => 2])->assertRedirect();

    $ended->refresh();
    expect($ended->status)->toBe(AccountStatus::Trial)
        ->and($ended->trial_ends_at->isFuture())->toBeTrue();

    expect(AuditLog::query()->where('action', 'account.trial_extended')->count())->toBe(2);
});

it('validates the trial extension', function () {
    $account = Account::factory()->trialInProgress()->create();

    foreach ([0, -3, 'abc', 100000, null] as $hours) {
        $this->actingAs($this->admin)->post("/admin/accounts/{$account->uuid}/extend-trial", ['hours' => $hours])
            ->assertSessionHasErrors('hours');
    }

    $notStarted = Account::factory()->trial()->create();
    $this->actingAs($this->admin)->post("/admin/accounts/{$notStarted->uuid}/extend-trial", ['hours' => 5])
        ->assertSessionHasErrors('hours');
    expect($notStarted->fresh()->trial_ends_at)->toBeNull();
});
