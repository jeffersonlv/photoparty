<?php

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

function adminPlanPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Premium',
        'slug' => 'premium',
        'price' => '99.90',
        'currency' => 'eur',
        'max_events_per_month' => 12,
        'max_photos_per_event' => 3000,
        'retention_days' => 90,
        'sort_order' => 4,
        'allows_custom_frames' => '1',
        'is_public' => '1',
    ], $overrides);
}

it('creates, lists, updates and deletes plans', function () {
    $this->actingAs($this->admin)->post('/admin/plans', adminPlanPayload())->assertRedirect(route('admin.plans.index'));

    $plan = Plan::query()->where('slug', 'premium')->firstOrFail();
    expect($plan->currency)->toBe('EUR')
        ->and($plan->allows_custom_frames)->toBeTrue()
        ->and($plan->white_label)->toBeFalse()
        ->and($plan->is_public)->toBeTrue();

    $this->actingAs($this->admin)->get('/admin/plans')->assertOk()->assertSee('Premium');
    $this->actingAs($this->admin)->get("/admin/plans/{$plan->id}/edit")->assertOk()->assertSee('premium');

    $this->actingAs($this->admin)->put("/admin/plans/{$plan->id}", adminPlanPayload(['name' => 'Premium Plus', 'is_public' => null, 'max_events_per_month' => null]))
        ->assertRedirect();
    $plan->refresh();
    expect($plan->name)->toBe('Premium Plus')
        ->and($plan->is_public)->toBeFalse()
        ->and($plan->max_events_per_month)->toBeNull();

    $this->actingAs($this->admin)->delete("/admin/plans/{$plan->id}")->assertRedirect();
    expect(Plan::query()->find($plan->id))->toBeNull();

    expect(AuditLog::query()->pluck('action')->all())->toEqual(['plan.created', 'plan.updated', 'plan.deleted']);
});

it('validates the plan form', function () {
    Plan::factory()->create(['slug' => 'taken']);

    $bad = [
        ['name' => ''],
        ['slug' => 'with space'],
        ['slug' => 'taken'],
        ['price' => '-1'],
        ['price' => 'abc'],
        ['currency' => 'EURO'],
        ['max_photos_per_event' => 0],
        ['retention_days' => ''],
        ['max_events_per_month' => 0],
    ];

    foreach ($bad as $override) {
        $this->actingAs($this->admin)->post('/admin/plans', adminPlanPayload($override))
            ->assertSessionHasErrors(array_key_first($override));
    }

    expect(Plan::query()->count())->toBe(1);
});

it('allows keeping the same slug on update', function () {
    $plan = Plan::factory()->create(['slug' => 'mine']);

    $this->actingAs($this->admin)->put("/admin/plans/{$plan->id}", adminPlanPayload(['slug' => 'mine']))->assertSessionHasNoErrors();
});

it('refuses to delete a plan that has accounts', function () {
    $plan = Plan::factory()->create();
    Account::factory()->active($plan)->create();

    $this->actingAs($this->admin)->delete("/admin/plans/{$plan->id}")->assertSessionHas('error');

    expect(Plan::query()->find($plan->id))->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'plan.deleted')->exists())->toBeFalse();
});
