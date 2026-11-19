<?php

use App\Models\Frame;
use App\Models\Plan;
use Database\Seeders\FrameSeeder;
use Database\Seeders\PlanSeeder;

it('shows plan names in the language of the page', function () {
    $this->seed(PlanSeeder::class);
    $pro = Plan::where('slug', 'pro')->firstOrFail();

    app()->setLocale('pt_BR');
    expect($pro->displayName())->toBe('Profissional');

    app()->setLocale('en');
    expect($pro->displayName())->toBe('Professional');

    $pro->name_en = null;
    expect($pro->displayName())->toBe('Profissional');
});

it('lists English plan names on the English home page', function () {
    $this->seed(PlanSeeder::class);
    $this->seed(\Database\Seeders\HomeSeeder::class);

    $this->get('/locale/en');
    $this->get('/')->assertOk()->assertSee('Professional')->assertDontSee('Profissional');
});

it('shows built-in frame names in the language of the page', function () {
    $this->seed(FrameSeeder::class);
    $frame = Frame::withoutGlobalScopes()->where('template_key', 'wanted_poster')->firstOrFail();

    app()->setLocale('en');
    $en = $frame->displayName();
    app()->setLocale('pt_BR');
    $pt = $frame->displayName();

    expect($en)->not->toBe($pt);
});

it('saves the English plan name from the admin form', function () {
    $admin = \App\Models\User::factory()->admin()->create();
    $this->seed(PlanSeeder::class);
    $plan = Plan::where('slug', 'event')->firstOrFail();

    $this->actingAs($admin)->put(route('admin.plans.update', $plan), array_merge($plan->only([
        'name', 'slug', 'price', 'currency', 'max_events_per_month', 'max_photos_per_event', 'retention_days', 'sort_order',
    ]), ['name_en' => 'One-off event', 'is_public' => '1']))->assertRedirect();

    expect($plan->fresh()->name_en)->toBe('One-off event');
});
