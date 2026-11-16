<?php

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\User;
use App\Services\AuditLogger;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['email' => 'boss@example.test']);
});

it('logs through the AuditLogger and strips sensitive meta', function () {
    $this->actingAs($this->admin);
    $account = Account::factory()->create();

    app(AuditLogger::class)->log('test.action', $account, ['ok' => 'yes', 'password' => 'x', 'nested' => ['api_token' => 'y', 'fine' => 1]]);

    $log = AuditLog::query()->where('action', 'test.action')->firstOrFail();

    expect($log->user_id)->toBe($this->admin->id)
        ->and($log->account_id)->toBe($account->id)
        ->and($log->subject_type)->toBe(Account::class)
        ->and($log->meta)->toBe(['ok' => 'yes', 'nested' => ['fine' => 1]]);
});

it('lists logs with filters and pagination', function () {
    $this->actingAs($this->admin);
    $account = Account::factory()->create(['name' => 'Conta Audit']);
    $plan = Plan::factory()->create();

    $logger = app(AuditLogger::class);
    $logger->log('account.status_changed', $account, ['to' => 'active']);
    $logger->log('plan.updated', $plan);

    $this->get('/admin/audit')->assertOk()->assertSee('account.status_changed')->assertSee('plan.updated')->assertSee('boss@example.test');
    // O <datalist> de ações lista tudo; o que muda com o filtro são as linhas da tabela (<code>).
    $this->get('/admin/audit?action=plan.')->assertOk()->assertSee('<code>plan.updated</code>', false)->assertDontSee('<code>account.status_changed</code>', false);
    $this->get('/admin/audit?account=Audit')->assertOk()->assertSee('<code>account.status_changed</code>', false)->assertDontSee('<code>plan.updated</code>', false);
    $this->get('/admin/audit?user=boss')->assertOk()->assertSee('plan.updated');
    $this->get('/admin/audit?user=nobody')->assertOk()->assertDontSee('<code>plan.updated</code>', false);
    $this->get('/admin/audit?from='.now()->addDay()->toDateString())->assertOk()->assertDontSee('<code>plan.updated</code>', false);
    $this->get('/admin/audit?from=not-a-date')->assertSessionHasErrors('from');

    foreach (range(1, 35) as $i) {
        $logger->log("bulk.{$i}");
    }

    $this->get('/admin/audit')->assertOk()->assertSee('<code>bulk.35</code>', false)->assertDontSee('<code>account.status_changed</code>', false);
    $this->get('/admin/audit?page=2')->assertOk()->assertSee('<code>account.status_changed</code>', false);
});

it('records an audit log for every admin write action', function () {
    $account = Account::factory()->trialInProgress()->create();

    $this->actingAs($this->admin)->put("/admin/accounts/{$account->uuid}/status", ['status' => 'active']);
    $this->actingAs($this->admin)->post("/admin/accounts/{$account->uuid}/extend-trial", ['hours' => 1]);

    expect(AuditLog::query()->count())->toBe(2)
        ->and(AuditLog::query()->pluck('ip')->filter()->count())->toBe(2);
});
