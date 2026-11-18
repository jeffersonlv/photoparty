<?php

use App\Models\Account;
use App\Models\Photo;
use App\Services\GuestDevice;
use App\Support\GuestFilters;
use Illuminate\Support\Facades\Storage;
use Tests\Support\GuestUpload;

/*
| Lista canônica de filtros do convidado (config photoparty.filters) e alias 'sepia' => 'vintage'.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->account = Account::factory()->active()->create();
    $this->event = GuestUpload::liveEvent($this->account);
    $this->device = str_repeat('F', 40);
});

function uploadWithFilter($test, string $filter)
{
    return $test->withCookie(GuestDevice::COOKIE, $test->device)
        ->post(GuestUpload::storeUrl($test->event), GuestUpload::payload(['filter' => $filter]), ['Accept' => 'application/json']);
}

it('has the canonical list in a single place and sepia is only an alias', function () {
    expect(config('photoparty.filters'))->toBe(['none', 'bw', 'vintage', 'vivid', 'cool', 'warm'])
        ->and(config('photoparty.filters'))->not->toContain('sepia')
        ->and(config('photoparty.filter_aliases'))->toBe(['sepia' => 'vintage'])
        ->and(GuestFilters::accepted())->toContain('sepia', 'vintage');
});

it('accepts every canonical filter on upload', function (string $filter) {
    $response = uploadWithFilter($this, $filter)->assertCreated();

    expect(Photo::withoutGlobalScopes()->where('uuid', $response->json('uuid'))->value('filter'))->toBe($filter);
})->with(['none', 'bw', 'vintage', 'vivid', 'cool', 'warm']);

it('normalizes sepia from old clients to vintage', function () {
    $response = uploadWithFilter($this, 'sepia')->assertCreated();

    expect(Photo::withoutGlobalScopes()->where('uuid', $response->json('uuid'))->value('filter'))->toBe('vintage');
});

it('rejects unknown filters and defaults to none when missing', function () {
    uploadWithFilter($this, 'hack')->assertUnprocessable()->assertJsonValidationErrors('filter');

    $payload = GuestUpload::payload();
    unset($payload['filter']);
    $response = $this->withCookie(GuestDevice::COOKIE, $this->device)
        ->post(GuestUpload::storeUrl($this->event), $payload, ['Accept' => 'application/json'])->assertCreated();

    expect(Photo::withoutGlobalScopes()->where('uuid', $response->json('uuid'))->value('filter'))->toBe('none');
});

it('puts the active filters of the event in the guest page payload, normalized', function () {
    $this->event->update(['enabled_filters' => ['sepia', 'bw', 'hack']]);

    $html = $this->get(GuestUpload::pageUrl($this->event))->assertOk()->getContent();

    expect($html)->toContain('"filters":["none","bw","vintage"]');
});

it('offers all filters when the event has no stored list, and only none when empty', function () {
    $this->event->forceFill(['enabled_filters' => null])->save();
    expect(GuestFilters::enabledFor($this->event->fresh()))->toBe(config('photoparty.filters'));

    $this->event->forceFill(['enabled_filters' => []])->save();
    expect(GuestFilters::enabledFor($this->event->fresh()))->toBe(['none']);
});

it('normalizes the organizer form: sepia becomes vintage, order is canonical', function () {
    $owner = ownerFor($this->account);

    $this->actingAs($owner)->post(route('events.store'), eventPayload(['enabled_filters' => ['warm', 'sepia', 'none']]))
        ->assertSessionHasNoErrors();

    $event = $this->account->events()->latest('id')->first();

    expect($event->enabled_filters)->toBe(['none', 'vintage', 'warm']);
});

it('lists the canonical filters in the event form', function () {
    $owner = ownerFor($this->account);

    $html = $this->actingAs($owner)->get(route('events.create'))->assertOk()->getContent();

    foreach (config('photoparty.filters') as $key) {
        expect($html)->toContain('value="'.$key.'"');
    }

    expect($html)->not->toContain('value="sepia"');
});
