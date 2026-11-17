<?php

use App\Enums\EventStatus;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Event;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->freezeSecond();
    $this->account = Account::factory()->active()->create();
    $this->owner = ownerFor($this->account);
});

it('shows the create form and stores an event with defaults', function () {
    $this->actingAs($this->owner)->get(route('events.create'))->assertOk();

    $this->actingAs($this->owner)
        ->post(route('events.store'), eventPayload(['name' => 'Casamento']))
        ->assertSessionHasNoErrors();

    $event = Event::withoutGlobalScopes()->sole();

    expect($event->name)->toBe('Casamento')
        ->and($event->slug)->toStartWith('casamento-')
        ->and($event->ask_guest_name)->toBeTrue()
        ->and($event->enabled_filters)->toBe(['none', 'bw'])
        ->and($event->screen_settings)->toBe(config('photoparty.screen_defaults'))
        ->and(AuditLog::where('action', 'event.created')->exists())->toBeTrue();
});

it('updates an event', function () {
    $event = Event::factory()->for($this->account)->create();

    $this->actingAs($this->owner)
        ->put(route('events.update', $event), eventPayload(['name' => 'Novo nome', 'moderation' => 'post']))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('events.show', $event));

    expect($event->refresh()->name)->toBe('Novo nome')
        ->and($event->moderation)->toBe('post');
});

it('validates the event form', function () {
    $this->actingAs($this->owner)
        ->post(route('events.store'), eventPayload([
            'name' => '',
            'moderation' => 'whatever',
            'language' => 'xx',
            'enabled_filters' => ['hack'],
        ]))
        ->assertSessionHasErrors(['name', 'moderation', 'language', 'enabled_filters.0']);
});

it('stores tokens as SHA-256 hash plus an encrypted copy', function () {
    $event = Event::factory()->for($this->account)->create();
    $raw = DB::table('events')->where('id', $event->id)->first();

    $uploadToken = $event->uploadToken();
    $screenToken = $event->screenToken();

    expect($uploadToken)->toHaveLength(64)
        ->and($screenToken)->toHaveLength(64)
        ->and($uploadToken)->not->toBe($screenToken)
        ->and($raw->upload_token_hash)->toBe(hash('sha256', $uploadToken))
        ->and($raw->screen_token_hash)->toBe(hash('sha256', $screenToken))
        // Nada em texto puro no banco.
        ->and($raw->upload_token_encrypted)->not->toContain($uploadToken)
        ->and($raw->screen_token_encrypted)->not->toContain($screenToken)
        ->and(Event::whereUploadToken($uploadToken)->first()?->is($event))->toBeTrue()
        ->and(Event::whereScreenToken($screenToken)->first()?->is($event))->toBeTrue()
        ->and($event->toArray())->not->toHaveKeys(['upload_token_hash', 'upload_token_encrypted', 'screen_token_hash', 'screen_token_encrypted']);
});

it('builds the guest URL on the public domain', function () {
    $event = Event::factory()->for($this->account)->create();

    expect($event->guestUrl())->toBe('https://photo.uailabs.net/e/'.$event->slug.'?t='.$event->uploadToken());
});

it('regenerates the screen token and invalidates the old one', function () {
    $event = Event::factory()->for($this->account)->create();
    $oldToken = $event->screenToken();
    $uploadHash = $event->upload_token_hash;

    $this->actingAs($this->owner)
        ->post(route('events.screen-token', $event))
        ->assertRedirect(route('events.show', $event));

    $event->refresh();

    expect($event->screenToken())->not->toBe($oldToken)
        ->and(Event::whereScreenToken($oldToken)->exists())->toBeFalse()
        ->and(Event::whereScreenToken($event->screenToken())->exists())->toBeTrue()
        ->and($event->upload_token_hash)->toBe($uploadHash)
        ->and(AuditLog::where('action', 'event.screen_token_regenerated')->exists())->toBeTrue();

    // O token nunca vai para o log de auditoria.
    expect(AuditLog::query()->pluck('meta')->toJson())->not->toContain($event->screenToken());
});

it('downloads the QR code as PNG', function () {
    $event = Event::factory()->for($this->account)->create();

    $response = $this->actingAs($this->owner)->get(route('events.qr', [$event, 'png']));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertDownload('qr-'.$event->slug.'.png');

    expect(substr($response->getContent(), 0, 8))->toBe("\x89PNG\r\n\x1a\n");
});

it('downloads the QR code as SVG', function () {
    $event = Event::factory()->for($this->account)->create();

    $response = $this->actingAs($this->owner)->get(route('events.qr', [$event, 'svg']));

    $response->assertOk()->assertDownload('qr-'.$event->slug.'.svg');

    expect($response->headers->get('Content-Type'))->toStartWith('image/svg+xml')
        ->and($response->getContent())->toContain('<svg');
});

it('rejects unknown QR formats', function () {
    $event = Event::factory()->for($this->account)->create();

    $this->actingAs($this->owner)->get('/events/'.$event->uuid.'/qr.gif')->assertNotFound();
});

it('computes the event status from the schedule', function () {
    $event = Event::factory()->for($this->account)->make([
        'starts_at' => now()->addHour(),
        'ends_at' => now()->addHours(3),
    ]);

    expect($event->computeStatus())->toBe(EventStatus::Draft);

    $event->published_at = now();
    $event->expires_at = $event->calculateExpiresAt(30);

    expect($event->computeStatus())->toBe(EventStatus::Scheduled)
        ->and($event->computeStatus(now()->addHours(2)))->toBe(EventStatus::Live)
        ->and($event->computeStatus(now()->addHours(4)))->toBe(EventStatus::Ended)
        ->and($event->computeStatus(now()->addHours(3)->addDays(30)))->toBe(EventStatus::Expired);
});

it('publishes and closes an event', function () {
    $event = Event::factory()->for($this->account)->create([
        'starts_at' => now()->subMinutes(10),
        'ends_at' => now()->addHours(3),
    ]);

    $this->actingAs($this->owner)->post(route('events.publish', $event))->assertSessionHasNoErrors();
    expect($event->refresh()->status)->toBe(EventStatus::Live);

    $this->actingAs($this->owner)->post(route('events.publish', $event))->assertSessionHasErrors('event');

    $this->actingAs($this->owner)->post(route('events.close', $event))->assertRedirect(route('events.show', $event));

    $event->refresh();
    expect($event->status)->toBe(EventStatus::Ended)
        ->and($event->closed_at)->not->toBeNull()
        ->and($event->expires_at->equalTo(now()->addDays($this->account->plan->retention_days)))->toBeTrue();

    // Encerrado não pode ser editado nem encerrado de novo.
    $this->actingAs($this->owner)->get(route('events.edit', $event))->assertForbidden();
    $this->actingAs($this->owner)->post(route('events.close', $event))->assertForbidden();
});

it('deletes only draft events', function () {
    $draft = Event::factory()->for($this->account)->create();
    $live = Event::factory()->for($this->account)->live()->create();

    $this->actingAs($this->owner)->delete(route('events.destroy', $live))->assertForbidden();
    $this->actingAs($this->owner)->delete(route('events.destroy', $draft))->assertRedirect(route('events.index'));

    expect(Event::withoutGlobalScopes()->whereKey($draft->id)->exists())->toBeFalse()
        ->and(Event::withoutGlobalScopes()->whereKey($live->id)->exists())->toBeTrue();
});

it('refreshes stored statuses every minute via the scheduled command', function () {
    $event = Event::factory()->for($this->account)->scheduled()->create([
        'starts_at' => now()->addMinutes(5),
        'ends_at' => now()->addHours(2),
    ]);
    $event->forceFill(['expires_at' => $event->calculateExpiresAt(30)])->save();

    $this->travel(6)->minutes();
    $this->artisan('events:refresh-statuses')->assertSuccessful();
    expect($event->refresh()->status)->toBe(EventStatus::Live);

    $this->travel(2)->hours();
    $this->artisan('events:refresh-statuses')->assertSuccessful();
    expect($event->refresh()->status)->toBe(EventStatus::Ended);

    $this->travel(31)->days();
    $this->artisan('events:refresh-statuses')->assertSuccessful();
    expect($event->refresh()->status)->toBe(EventStatus::Expired);
});

it('enforces the monthly event limit of the plan', function () {
    $this->account->plan->update(['max_events_per_month' => 1]);
    Event::factory()->for($this->account)->create();

    $this->actingAs($this->owner)
        ->post(route('events.store'), eventPayload(['name' => 'Excedente']))
        ->assertRedirect(route('events.index'))
        ->assertSessionHas('error');

    expect(Event::withoutGlobalScopes()->where('name', 'Excedente')->exists())->toBeFalse();
});

it('registers the schedule for the queue worker and status updates', function () {
    Artisan::call('schedule:list');
    $output = Artisan::output();

    expect($output)->toContain('events:refresh-statuses')
        ->and($output)->toContain('accounts:expire-trials')
        ->and($output)->toContain('queue:work')
        ->and($output)->toContain('--stop-when-empty --max-time=50');
});

it('warns the organizer that a draft event QR code does not work yet', function () {
    $account = \App\Models\Account::factory()->active()->create();
    $owner = \App\Models\User::factory()->for($account)->create();
    $event = \App\Models\Event::factory()->for($account)->create();

    $this->actingAs($owner)->get(route('events.show', $event))
        ->assertOk()->assertSee(__('app.events.draft_notice_title'));

    $event->published_at = now();
    $event->refreshStatus();
    $event->save();

    $this->actingAs($owner)->get(route('events.show', $event))
        ->assertOk()->assertDontSee(__('app.events.draft_notice_title'));
});
