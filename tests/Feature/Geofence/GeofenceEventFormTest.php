<?php

use App\Models\Account;
use App\Models\Event;
use App\Models\Plan;

/*
| Recurso de plano: configuração de geofence no formulário do evento.
*/

beforeEach(function () {
    $this->freezeSecond();
});

function geoAccount(bool $allows): Account
{
    return Account::factory()->active(Plan::factory()->create(['allows_geofence' => $allows]))->create();
}

it('centralizes the plan rule in Account::canUseGeofence', function () {
    expect(geoAccount(true)->canUseGeofence())->toBeTrue()
        ->and(geoAccount(false)->canUseGeofence())->toBeFalse()
        ->and(Account::factory()->trial()->create()->canUseGeofence())->toBeFalse()
        ->and(Account::factory()->trialEnded()->create(['plan_id' => Plan::factory()->create(['allows_geofence' => true])->id])->canUseGeofence())->toBeFalse();
});

it('ignores geofence input from an account whose plan lacks the feature', function () {
    $owner = ownerFor(geoAccount(false));

    $this->actingAs($owner)->get(route('events.create'))
        ->assertOk()
        ->assertSee(__('app.geofence.unavailable'));

    $this->actingAs($owner)->post(route('events.store'), eventPayload([
        'geofence_enabled' => '1',
        'geofence_radius_m' => 300,
        'geofence_location' => '-23.561414, -46.655881',
    ]))->assertSessionHasNoErrors();

    $event = Event::withoutGlobalScopes()->sole();
    expect($event->geofence_enabled)->toBeFalse()
        ->and($event->geofence_lat)->toBeNull()
        ->and($event->geofence_lng)->toBeNull()
        ->and($event->geofence_radius_m)->toBe(100)
        ->and($event->geofenceActive())->toBeFalse();
});

it('saves the geofence from pasted coordinates for an account with the feature', function () {
    $owner = ownerFor(geoAccount(true));

    $this->actingAs($owner)->get(route('events.create'))
        ->assertOk()
        ->assertDontSee(__('app.geofence.unavailable'))
        ->assertSee(__('app.geofence.use_current'));

    $this->actingAs($owner)->post(route('events.store'), eventPayload([
        'geofence_enabled' => '1',
        'geofence_radius_m' => 250,
        'geofence_location' => 'https://www.google.com/maps/@-23.5614141,-46.6558811,17z',
    ]))->assertSessionHasNoErrors();

    $event = Event::withoutGlobalScopes()->sole();
    expect($event->geofence_enabled)->toBeTrue()
        ->and($event->geofence_lat)->toBe(-23.561414)
        ->and($event->geofence_lng)->toBe(-46.655881)
        ->and($event->geofence_radius_m)->toBe(250)
        ->and($event->geofenceActive())->toBeTrue()
        ->and($event->geofenceMapUrl())->toBe('https://www.google.com/maps?q=-23.561414,-46.655881');

    $this->actingAs($owner)->get(route('events.edit', $event))
        ->assertOk()
        ->assertSee('-23.561414, -46.655881')
        ->assertSee(__('app.geofence.view_map'));
});

it('uses the latitude/longitude from the "use my location" button and defaults the radius to 100', function () {
    $owner = ownerFor(geoAccount(true));

    $this->actingAs($owner)->post(route('events.store'), eventPayload([
        'geofence_enabled' => '1',
        'geofence_lat' => '51.898500',
        'geofence_lng' => '-8.475600',
    ]))->assertSessionHasNoErrors();

    $event = Event::withoutGlobalScopes()->sole();
    expect($event->geofence_lat)->toBe(51.8985)
        ->and($event->geofence_lng)->toBe(-8.4756)
        ->and($event->geofence_radius_m)->toBe(100);
});

it('validates the radius between 50 and 2000 metres', function (int $radius, bool $ok) {
    $owner = ownerFor(geoAccount(true));

    $response = $this->actingAs($owner)->post(route('events.store'), eventPayload([
        'geofence_enabled' => '1',
        'geofence_radius_m' => $radius,
        'geofence_location' => '51.8985, -8.4756',
    ]));

    $ok ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('geofence_radius_m');
})->with([
    [49, false],
    [50, true],
    [2000, true],
    [2001, false],
]);

it('rejects short links, invalid coordinates and an enabled geofence without location', function (array $input, string $message) {
    $owner = ownerFor(geoAccount(true));

    $this->actingAs($owner)->post(route('events.store'), eventPayload(['geofence_enabled' => '1'] + $input))
        ->assertSessionHasErrors(['geofence_location' => __($message)]);

    expect(Event::withoutGlobalScopes()->count())->toBe(0);
})->with([
    'link curto' => [['geofence_location' => 'https://maps.app.goo.gl/abc123'], 'app.geofence.errors.short_link'],
    'inválido' => [['geofence_location' => '123, 456'], 'app.geofence.errors.invalid'],
    'sem local' => [[], 'app.geofence.errors.required'],
]);

it('keeps the saved location when editing without new coordinates', function () {
    $account = geoAccount(true);
    $event = Event::factory()->for($account)->create();
    $event->forceFill(['geofence_enabled' => true, 'geofence_lat' => 51.8985, 'geofence_lng' => -8.4756])->save();
    $owner = ownerFor($account);

    $this->actingAs($owner)->put(route('events.update', $event), eventPayload([
        'geofence_enabled' => '1',
        'geofence_radius_m' => 500,
    ]))->assertSessionHasNoErrors();

    $event->refresh();
    expect($event->geofence_lat)->toBe(51.8985)
        ->and($event->geofence_radius_m)->toBe(500);
});

it('lets the admin toggle the plan feature and shows it on the home pricing', function () {
    $admin = \App\Models\User::factory()->admin()->create();
    $plan = Plan::factory()->create(['allows_geofence' => false, 'is_public' => true]);

    $this->actingAs($admin)->put(route('admin.plans.update', $plan), [
        'name' => $plan->name,
        'slug' => $plan->slug,
        'price' => '49',
        'currency' => 'EUR',
        'max_photos_per_event' => 100,
        'retention_days' => 30,
        'allows_geofence' => '1',
        'is_public' => '1',
    ])->assertSessionHasNoErrors();

    expect($plan->refresh()->allows_geofence)->toBeTrue();

    auth()->logout();
    (new \Database\Seeders\HomeSeeder)->run();
    $this->get('/')->assertOk()->assertSee(__('home.pricing.geofence'));

    $plan->update(['allows_geofence' => false]);
    $this->get('/')->assertDontSee(__('home.pricing.geofence'));
});

it('does not let another account change the geofence of an event (404)', function () {
    $other = Event::factory()->for(geoAccount(true))->create();
    $owner = ownerFor(geoAccount(true));

    $this->actingAs($owner)->put(route('events.update', $other), eventPayload([
        'geofence_enabled' => '1',
        'geofence_location' => '51.8985, -8.4756',
    ]))->assertNotFound();

    expect($other->refresh()->geofence_enabled)->toBeFalse();
});
