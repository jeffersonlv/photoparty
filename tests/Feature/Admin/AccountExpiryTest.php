<?php

use App\Enums\AccountStatus;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\AccountListSettings;
use App\Support\ExpiryLabel;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    Carbon::setTestNow('2026-11-19 12:00:00');
    app()->setLocale('pt_BR');
});

afterEach(fn () => Carbon::setTestNow());

it('computes expiresAt per status', function () {
    $trialNotStarted = Account::factory()->trial()->create();
    $trial = Account::factory()->trialInProgress()->create();
    $active = Account::factory()->active()->create(['paid_until' => now()->addDays(10)]);
    $pastDue = Account::factory()->status(AccountStatus::PastDue)->create(['paid_until' => now()->addDays(10)]);

    expect($trialNotStarted->expiresAt())->toBeNull()
        ->and($trial->expiresAt()->equalTo($trial->trial_ends_at))->toBeTrue()
        ->and($active->expiresAt()->equalTo($active->paid_until))->toBeTrue()
        ->and($pastDue->expiresAt())->toBeNull();
});

it('describes the remaining time in pt_BR', function (Carbon $at, string $text, bool $urgent) {
    $r = ExpiryLabel::remaining($at);
    expect($r['text'])->toBe($text)->and($r['urgent'])->toBe($urgent);
})->with([
    'five days' => [fn () => now()->addDays(5)->addHour(), 'faltam 5 dias', false],
    'one day' => [fn () => now()->addDay()->addMinutes(5), 'falta 1 dia', true],
    'three days' => [fn () => now()->addDays(3), 'faltam 3 dias', true],
    'hours' => [fn () => now()->addHours(3)->addMinutes(10), 'faltam 3 horas', true],
    'one hour' => [fn () => now()->addMinutes(70), 'falta 1 hora', true],
    'today' => [fn () => now()->addMinutes(20), 'vence hoje', true],
    'today but passed' => [fn () => now()->subHours(2), 'vence hoje', true],
    'overdue 2 days' => [fn () => now()->subDays(2)->subHour(), 'venceu há 2 dias', true],
    'overdue 1 day' => [fn () => now()->subDay()->subHour(), 'venceu há 1 dia', true],
]);

it('describes the remaining time in English', function () {
    app()->setLocale('en');

    expect(ExpiryLabel::remaining(now()->addDays(5)->addHour())['text'])->toBe('5 days left')
        ->and(ExpiryLabel::remaining(now()->addDay()->addMinute())['text'])->toBe('1 day left')
        ->and(ExpiryLabel::remaining(now()->addHours(3)->addMinute())['text'])->toBe('3 hours left')
        ->and(ExpiryLabel::remaining(now()->addMinutes(10))['text'])->toBe('due today')
        ->and(ExpiryLabel::remaining(now()->subDays(2)->subHour())['text'])->toBe('expired 2 days ago')
        ->and(ExpiryLabel::format(now()))->toBe('2026-11-19 12:00');

    app()->setLocale('pt_BR');
    expect(ExpiryLabel::format(now()))->toBe('19/11/2026 12:00');
});

it('shows the expires column with date, remaining text and highlight', function () {
    Account::factory()->active()->create(['name' => 'Conta Longe', 'paid_until' => now()->addDays(20)]);
    Account::factory()->active()->create(['name' => 'Conta Perto', 'paid_until' => now()->addDays(2)->addHour()]);
    Account::factory()->trial()->create(['name' => 'Conta Sem Data']);

    $html = $this->actingAs($this->admin)->get('/admin/accounts')->assertOk()
        ->assertSee('Expira em')
        ->assertSee('09/12/2026 12:00')
        ->assertSee('faltam 20 dias')
        ->assertSee('faltam 2 dias')
        ->assertSee('adm-expiry__left--urgent', false)
        ->getContent();

    expect(substr_count($html, 'adm-expiry__left--urgent'))->toBe(1)
        ->and($html)->toContain('—');
});

it('uses trial_ends_at for trial accounts in the table', function () {
    Account::factory()->trialInProgress()->create(['name' => 'Conta Teste']);

    $this->actingAs($this->admin)->get('/admin/accounts')
        ->assertSee('19/11/2026 14:30')
        ->assertSee('faltam 2 horas');
});

it('moves expired active accounts to past_due and leaves the others', function () {
    $expired = Account::factory()->active()->create(['paid_until' => now()->subMinute()]);
    $valid = Account::factory()->active()->create(['paid_until' => now()->addDay()]);
    $noDate = Account::factory()->active()->create(['paid_until' => null]);
    $trial = Account::factory()->trialEnded()->create(['paid_until' => now()->subDay()]);

    $this->artisan('accounts:expire-paid')->assertSuccessful();

    expect($expired->fresh()->status)->toBe(AccountStatus::PastDue)
        ->and($valid->fresh()->status)->toBe(AccountStatus::Active)
        ->and($noDate->fresh()->status)->toBe(AccountStatus::Active)
        ->and($trial->fresh()->status)->toBe(AccountStatus::TrialEnded);

    $log = AuditLog::query()->where('action', 'account.past_due')->first();
    expect(AuditLog::query()->where('action', 'account.past_due')->count())->toBe(1)
        ->and($log->account_id)->toBe($expired->id);
});

it('is scheduled daily', function () {
    $events = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())
        ->filter(fn ($e) => str_contains($e->command, 'accounts:expire-paid'));

    expect($events)->toHaveCount(1)->and($events->first()->expression)->toBe('10 0 * * *');
});

it('sets paid_until with plan change and alone, with audit', function () {
    $account = Account::factory()->active()->create();

    $this->actingAs($this->admin)->put("/admin/accounts/{$account->uuid}/paid-until", ['paid_until' => '2027-01-31'])
        ->assertRedirect();
    expect($account->fresh()->paid_until->format('Y-m-d H:i:s'))->toBe('2027-01-31 23:59:59');

    $log = AuditLog::query()->where('action', 'account.paid_until_changed')->first();
    expect($log->account_id)->toBe($account->id)->and($log->meta['from'])->toBeNull()->and($log->meta['to'])->not->toBeNull();

    // Mesma data: não duplica a auditoria.
    $this->actingAs($this->admin)->put("/admin/accounts/{$account->uuid}/paid-until", ['paid_until' => '2027-01-31']);
    expect(AuditLog::query()->where('action', 'account.paid_until_changed')->count())->toBe(1);

    $plan = App\Models\Plan::factory()->create();
    $this->actingAs($this->admin)->put("/admin/accounts/{$account->uuid}/plan", ['plan_id' => $plan->id, 'paid_until' => '2027-03-01'])->assertRedirect();
    expect($account->fresh()->paid_until->format('Y-m-d'))->toBe('2027-03-01')->and($account->fresh()->plan_id)->toBe($plan->id);

    // Plano sem o campo não mexe no vencimento; vazio limpa.
    $this->actingAs($this->admin)->put("/admin/accounts/{$account->uuid}/plan", ['plan_id' => $plan->id]);
    expect($account->fresh()->paid_until)->not->toBeNull();
    $this->actingAs($this->admin)->put("/admin/accounts/{$account->uuid}/paid-until", ['paid_until' => ''])->assertRedirect();
    expect($account->fresh()->paid_until)->toBeNull();

    $this->actingAs($this->admin)->put("/admin/accounts/{$account->uuid}/paid-until", ['paid_until' => 'amanha'])
        ->assertSessionHasErrors('paid_until');
});

it('renders one section per status with correct counts', function () {
    Account::factory()->count(3)->active()->create();
    Account::factory()->count(2)->trial()->create();
    Account::factory()->status(AccountStatus::Cancelled)->create();

    $html = $this->actingAs($this->admin)->get('/admin/accounts')->assertOk()->getContent();

    foreach (['trial', 'trial_ended', 'active', 'past_due', 'cancelled'] as $status) {
        expect($html)->toContain('id="status-'.$status.'"');
    }

    expect($html)->toMatch('/id="status-active"\s*open\s*>\s*<summary[^>]*>\s*<h2>Ativa<\/h2>\s*<span[^>]*>3<\/span>/')
        ->and($html)->toMatch('/id="status-trial"\s*open\s*>\s*<summary[^>]*>\s*<h2>Teste grátis<\/h2>\s*<span[^>]*>2<\/span>/')
        ->and($html)->toMatch('/id="status-past_due"\s*>\s*<summary/')
        ->and($html)->toContain('Nenhuma conta');
});

it('paginates each status independently with 10 per page by default', function () {
    Account::factory()->count(12)->active()->create(['paid_until' => now()->addDays(30)]);
    Account::factory()->count(12)->trial()->create();

    $html = $this->actingAs($this->admin)->get('/admin/accounts?page_active=2')->assertOk()->getContent();

    $section = fn (string $status) => (function () use ($html, $status) {
        preg_match('/id="status-'.$status.'".*?<\/details>/s', $html, $m);

        return $m[0] ?? '';
    })();

    expect(substr_count($section('active'), '<tr>'))->toBe(2 + 1)   // 2 linhas + cabeçalho
        ->and(substr_count($section('trial'), '<tr>'))->toBe(10 + 1)
        ->and($section('trial'))->toContain('page_active=2')
        ->and($section('trial'))->toContain('page_trial=2');
});

it('orders each table by nearest expiry, nulls last, then name', function () {
    Account::factory()->active()->create(['name' => 'Zeta', 'paid_until' => now()->addDays(1)]);
    Account::factory()->active()->create(['name' => 'Alfa', 'paid_until' => now()->addDays(9)]);
    Account::factory()->active()->create(['name' => 'Beta', 'paid_until' => null]);
    Account::factory()->active()->create(['name' => 'Aaa', 'paid_until' => null]);

    $this->actingAs($this->admin)->get('/admin/accounts')
        ->assertSeeInOrder(['Zeta', 'Alfa', 'Aaa', 'Beta']);
});

it('keeps the text search applied to every table', function () {
    Account::factory()->active()->create(['name' => 'Foto Alfa']);
    Account::factory()->active()->create(['name' => 'Cabine Beta']);
    Account::factory()->trial()->create(['name' => 'Foto Gama']);

    $this->actingAs($this->admin)->get('/admin/accounts?q=Foto')
        ->assertSee('Foto Alfa')->assertSee('Foto Gama')->assertDontSee('Cabine Beta');
});

it('saves order and per-page limits and applies them', function () {
    Account::factory()->count(7)->active()->create();

    $order = ['cancelled', 'active', 'trial', 'trial_ended', 'past_due'];
    $this->actingAs($this->admin)->put('/admin/accounts/settings', [
        'order' => $order,
        'per_page' => ['trial' => 10, 'trial_ended' => 10, 'active' => 5, 'past_due' => 25, 'cancelled' => 100],
    ])->assertRedirect('/admin/accounts');

    expect(SiteSetting::query()->find('admin.accounts.status_order')->value)->toBe(implode(',', $order))
        ->and(SiteSetting::query()->find('admin.accounts.per_page.active')->value)->toBe('5');

    $html = $this->actingAs($this->admin)->get('/admin/accounts')->getContent();
    expect(strpos($html, 'id="status-cancelled"'))->toBeLessThan(strpos($html, 'id="status-active"'))
        ->and(strpos($html, 'id="status-active"'))->toBeLessThan(strpos($html, 'id="status-trial"'));
    preg_match('/id="status-active".*?<\/details>/s', $html, $m);
    expect(substr_count($m[0], '<tr>'))->toBe(5 + 1);

    $log = AuditLog::query()->where('action', 'account.list_settings_changed')->first();
    expect($log->user_id)->toBe($this->admin->id)->and($log->meta['to']['order'])->toBe($order);

    // Sem mudança, sem nova auditoria.
    $this->actingAs($this->admin)->put('/admin/accounts/settings', [
        'order' => $order,
        'per_page' => ['trial' => 10, 'trial_ended' => 10, 'active' => 5, 'past_due' => 25, 'cancelled' => 100],
    ]);
    expect(AuditLog::query()->where('action', 'account.list_settings_changed')->count())->toBe(1);
});

it('moves a status up or down from the customize panel', function () {
    $default = ['trial', 'trial_ended', 'active', 'past_due', 'cancelled'];
    $per = ['trial' => 10, 'trial_ended' => 10, 'active' => 10, 'past_due' => 10, 'cancelled' => 10];

    $this->actingAs($this->admin)->put('/admin/accounts/settings', ['order' => $default, 'per_page' => $per, 'move' => 'active:up'])->assertRedirect();
    expect(app(AccountListSettings::class)->order())->toBe(['trial', 'active', 'trial_ended', 'past_due', 'cancelled']);

    $this->actingAs($this->admin)->put('/admin/accounts/settings', ['order' => app(AccountListSettings::class)->order(), 'per_page' => $per, 'move' => 'trial:up']);
    expect(app(AccountListSettings::class)->order()[0])->toBe('trial');
});

it('rejects invalid settings', function (array $payload) {
    $valid = ['order' => ['trial', 'trial_ended', 'active', 'past_due', 'cancelled'], 'per_page' => ['trial' => 10, 'trial_ended' => 10, 'active' => 10, 'past_due' => 10, 'cancelled' => 10]];

    $this->actingAs($this->admin)->put('/admin/accounts/settings', array_replace_recursive($valid, $payload))
        ->assertSessionHasErrors();

    expect(SiteSetting::query()->where('key', 'like', 'admin.accounts.%')->count())->toBe(0);
})->with([
    'unknown status' => [['order' => [2 => 'bogus']]],
    'duplicate status' => [['order' => [1 => 'trial']]],
    'bad limit' => [['per_page' => ['active' => 7]]],
    'huge limit' => [['per_page' => ['trial' => 100000]]],
    'bad move' => [['move' => 'active:sideways']],
]);

it('rejects incomplete settings', function () {
    $this->actingAs($this->admin)->put('/admin/accounts/settings', ['order' => ['trial', 'active'], 'per_page' => ['trial' => 10]])
        ->assertSessionHasErrors();
    $this->actingAs($this->admin)->put('/admin/accounts/settings', [])->assertSessionHasErrors();
});

it('falls back to defaults when stored settings are corrupted', function () {
    SiteSetting::query()->create(['key' => 'admin.accounts.status_order', 'value' => 'active,bogus,active']);
    SiteSetting::query()->create(['key' => 'admin.accounts.per_page.trial', 'value' => '999']);

    $s = app(AccountListSettings::class);
    expect($s->order())->toBe(['active', 'trial', 'trial_ended', 'past_due', 'cancelled'])
        ->and($s->perPage()['trial'])->toBe(10);
});

it('returns 404 for non-admins on the new routes', function () {
    $owner = User::factory()->for(Account::factory()->create())->create();
    $account = Account::factory()->create();

    $this->actingAs($owner)->get('/admin/accounts')->assertNotFound();
    $this->actingAs($owner)->put('/admin/accounts/settings', [])->assertNotFound();
    $this->actingAs($owner)->put("/admin/accounts/{$account->uuid}/paid-until", ['paid_until' => '2027-01-01'])->assertNotFound();
    expect($account->fresh()->paid_until)->toBeNull();
});
