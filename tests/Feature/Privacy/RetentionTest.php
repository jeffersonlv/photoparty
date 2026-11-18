<?php

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Export;
use App\Models\Photo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\GuestUpload;

/*
| privacy:enforce-retention: apaga no prazo (e não antes), órfãos, tombstones e IPs antigos.
*/

beforeEach(function () {
    Storage::fake('local');
});

function endedEvent(Account $account, int $daysAgo, int $retention, array $attrs = []): Event
{
    $end = now()->subDays($daysAgo);

    return Event::factory()->for($account)->create(array_merge([
        'starts_at' => $end->copy()->subHours(3),
        'ends_at' => $end,
        'published_at' => $end->copy()->subDay(),
        'expires_at' => $end->copy()->addDays($retention),
        'welcome_message' => 'Bem-vindos à festa da Ana',
    ], $attrs));
}

it('deletes photo files, previews, ZIPs and photo records after the plan retention, keeping a minimal event record', function () {
    $account = Account::factory()->active()->create();
    $event = endedEvent($account, 31, 30);
    $photo = GuestUpload::storedPhoto($event, 'approved');
    $preview = $photo->previewPath();
    Storage::disk('local')->put($preview, 'x');
    $zip = $account->storagePath().'/events/'.$event->uuid.'/exports/'.Str::uuid().'.zip';
    Storage::disk('local')->put($zip, 'zip');
    $export = new Export();
    $export->forceFill(['uuid' => (string) Str::uuid(), 'account_id' => $account->id, 'event_id' => $event->id, 'status' => 'ready', 'path' => $zip, 'bytes' => 3])->saveQuietly();

    $this->artisan('privacy:enforce-retention')->assertSuccessful();

    Storage::disk('local')->assertMissing([$photo->original_path, $photo->thumb_path, $preview, $zip]);
    expect(Storage::disk('local')->allFiles($account->storagePath().'/events/'.$event->uuid))->toBe([]);
    expect(Photo::withoutGlobalScopes()->where('event_id', $event->id)->count())->toBe(0);
    expect(Export::withoutGlobalScopes()->find($export->id)->status)->toBe('expired');

    $fresh = Event::withoutGlobalScopes()->find($event->id);
    expect($fresh)->not->toBeNull()
        ->and($fresh->purged_at)->not->toBeNull()
        ->and($fresh->name)->toBe($event->name)
        ->and($fresh->welcome_message)->toBeNull();
    expect(AuditLog::query()->where('action', 'event.retention_purged')->exists())->toBeTrue();
});

it('does not delete anything before the retention deadline', function () {
    $account = Account::factory()->active()->create();
    $event = endedEvent($account, 29, 30);
    $photo = GuestUpload::storedPhoto($event, 'approved');
    $live = GuestUpload::liveEvent($account);
    $livePhoto = GuestUpload::storedPhoto($live, 'pending');

    $this->artisan('privacy:enforce-retention')->assertSuccessful();

    Storage::disk('local')->assertExists([$photo->original_path, $livePhoto->original_path]);
    expect(Event::withoutGlobalScopes()->find($event->id)->purged_at)->toBeNull();
});

it('keeps an ended trial for 7 days after the end of the trial period, then deletes', function () {
    $account = Account::factory()->trialEnded()->create(['trial_ends_at' => now()->subDays(6)]);
    $event = endedEvent($account, 7, 7, ['is_trial' => true]);
    $photo = GuestUpload::storedPhoto($event, 'approved');

    $this->artisan('privacy:enforce-retention')->assertSuccessful();
    Storage::disk('local')->assertExists($photo->original_path);

    $this->travel(2)->days();
    $this->artisan('privacy:enforce-retention')->assertSuccessful();
    Storage::disk('local')->assertMissing($photo->original_path);
});

it('removes guest-deleted tombstones after the configured hours', function () {
    $event = GuestUpload::liveEvent();
    $photo = GuestUpload::storedPhoto($event, 'approved');
    Photo::withoutGlobalScopes()->whereKey($photo->id)->update(['status' => Photo::STATUS_DELETED, 'updated_at' => now()->subHours(2)]);

    $this->artisan('privacy:enforce-retention');
    expect(Photo::withoutGlobalScopes()->whereKey($photo->id)->exists())->toBeTrue();

    Photo::withoutGlobalScopes()->whereKey($photo->id)->update(['updated_at' => now()->subHours(25)]);
    $this->artisan('privacy:enforce-retention');
    expect(Photo::withoutGlobalScopes()->whereKey($photo->id)->exists())->toBeFalse();
});

it('removes orphan tenant, event and frame folders but keeps valid ones', function () {
    $event = GuestUpload::liveEvent();
    $photo = GuestUpload::storedPhoto($event, 'approved');
    $account = Account::query()->find($event->account_id);
    $disk = Storage::disk('local');

    $orphanTenant = 'tenants/'.Str::uuid().'/events/'.Str::uuid().'/original/a.jpg';
    $orphanEvent = $account->storagePath().'/events/'.Str::uuid().'/original/b.jpg';
    $orphanFrame = $account->storagePath().'/frames/'.Str::uuid().'/portrait.png';
    $disk->put($orphanTenant, 'x');
    $disk->put($orphanEvent, 'x');
    $disk->put($orphanFrame, 'x');

    $this->artisan('privacy:enforce-retention')->assertSuccessful();

    $disk->assertMissing([$orphanTenant, $orphanEvent, $orphanFrame]);
    $disk->assertExists($photo->original_path);
});

it('anonymizes audit log IPs after 90 days only', function () {
    $old = AuditLog::query()->create(['action' => 'auth.login', 'ip' => '203.0.113.9']);
    $old->forceFill(['created_at' => now()->subDays(91)])->save();
    $recent = AuditLog::query()->create(['action' => 'auth.login', 'ip' => '203.0.113.10']);

    $this->artisan('privacy:enforce-retention')->assertSuccessful();

    expect($old->fresh()->ip)->toBeNull()->and($recent->fresh()->ip)->toBe('203.0.113.10');
});

it('supports a dry run that changes nothing', function () {
    $account = Account::factory()->active()->create();
    $event = endedEvent($account, 40, 30);
    $photo = GuestUpload::storedPhoto($event, 'approved');

    $this->artisan('privacy:enforce-retention --dry-run')->assertSuccessful();
    Storage::disk('local')->assertExists($photo->original_path);
});

it('is scheduled daily', function () {
    $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
        ->filter(fn ($e) => str_contains($e->command, 'privacy:enforce-retention'));

    expect($events)->toHaveCount(1)->and($events->first()->expression)->toBe('17 3 * * *');
});
