<?php

use App\Enums\EventStatus;
use App\Models\Account;
use App\Models\Event;
use App\Models\Photo;
use App\Services\GuestDevice;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\GuestUpload;

/*
| Módulo 4: POST /e/{slug}/photos e DELETE /e/{slug}/photos/{photo}.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->account = Account::factory()->active()->create();
    $this->event = GuestUpload::liveEvent($this->account);
    $this->device = str_repeat('D', 40);
});

function upload(Event $event, array $payload, ?string $device = null, ?string $token = null)
{
    $test = test();

    if ($device !== null) {
        $test->withCookie(GuestDevice::COOKIE, $device);
    }

    return $test->post(GuestUpload::storeUrl($event, $token), $payload, ['Accept' => 'application/json']);
}

it('stores a photo as pending under the tenant/event folder', function () {
    $response = upload($this->event, GuestUpload::payload(['guest_name' => 'Ana', 'filter' => 'vintage']), $this->device)
        ->assertCreated()
        ->assertJsonStructure(['uuid', 'status'])
        ->assertJson(['status' => 'pending']);

    $photo = Photo::withoutGlobalScopes()->where('uuid', $response->json('uuid'))->sole();
    $base = "tenants/{$this->account->uuid}/events/{$this->event->uuid}";

    expect($photo->account_id)->toBe($this->account->id)
        ->and($photo->event_id)->toBe($this->event->id)
        ->and($photo->status)->toBe('pending')
        ->and($photo->approved_at)->toBeNull()
        ->and($photo->guest_name)->toBe('Ana')
        ->and($photo->filter)->toBe('vintage')
        ->and($photo->device_hash)->toBe(GuestDevice::hashId($this->device))
        ->and($photo->original_path)->toBe("{$base}/original/{$photo->uuid}.jpg")
        ->and($photo->thumb_path)->toBe("{$base}/thumb/{$photo->uuid}.jpg")
        ->and($photo->bytes)->toBeGreaterThan(0);

    Storage::disk('local')->assertExists([$photo->original_path, $photo->thumb_path]);
    expect((new finfo(FILEINFO_MIME_TYPE))->buffer(Storage::disk('local')->get($photo->original_path)))->toBe('image/jpeg');
});

it('approves immediately with post moderation', function () {
    $event = GuestUpload::liveEvent($this->account, ['moderation' => 'post']);

    $response = upload($event, GuestUpload::payload([], 'landscape'))->assertCreated()->assertJson(['status' => 'approved']);

    $photo = Photo::withoutGlobalScopes()->where('uuid', $response->json('uuid'))->sole();
    expect($photo->approved_at)->not->toBeNull()->and($photo->orientation)->toBe('landscape');
});

it('accepts PNG and WebP and re-encodes them to JPEG', function (string $format) {
    $response = upload($this->event, GuestUpload::payload([
        'photo' => GuestUpload::file(GuestUpload::image(1080, 1350, $format), "p.{$format}"),
        'thumb' => GuestUpload::file(GuestUpload::image(320, 400, $format), "t.{$format}"),
    ]))->assertCreated();

    $photo = Photo::withoutGlobalScopes()->where('uuid', $response->json('uuid'))->sole();
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    expect($finfo->buffer(Storage::disk('local')->get($photo->original_path)))->toBe('image/jpeg')
        ->and($finfo->buffer(Storage::disk('local')->get($photo->thumb_path)))->toBe('image/jpeg');
})->with(['png', 'webp']);

it('strips EXIF/GPS metadata by re-encoding', function () {
    $marker = 'GPSLatitude-SECRET-48.8584N';
    $bytes = GuestUpload::jpegWithExif(1080, 1350, $marker);

    expect($bytes)->toContain('Exif')->toContain($marker);
    expect(getimagesizefromstring($bytes))->not->toBeFalse();

    $response = upload($this->event, GuestUpload::payload(['photo' => GuestUpload::file($bytes)]))->assertCreated();

    $stored = Storage::disk('local')->get(Photo::withoutGlobalScopes()->where('uuid', $response->json('uuid'))->value('original_path'));

    expect($stored)->not->toContain('Exif')->not->toContain($marker);
});

it('removes content appended to a valid image (polyglot)', function () {
    $bytes = GuestUpload::image(1080, 1350).'<?php system($_GET["c"]); ?>';

    $response = upload($this->event, GuestUpload::payload(['photo' => GuestUpload::file($bytes)]))->assertCreated();

    $stored = Storage::disk('local')->get(Photo::withoutGlobalScopes()->where('uuid', $response->json('uuid'))->value('original_path'));
    expect($stored)->not->toContain('<?php');
});

it('rejects invalid files', function (Closure $override, string $field) {
    upload($this->event, GuestUpload::payload($override()))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(Photo::withoutGlobalScopes()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'PHP file renamed to .jpg' => [fn () => ['photo' => GuestUpload::file('<?php echo "pwned"; ?>', 'photo.jpg')], 'photo'],
    'text thumb' => [fn () => ['thumb' => GuestUpload::file('hello', 'thumb.jpg')], 'thumb'],
    'GIF not allowed' => [fn () => ['photo' => GuestUpload::file(GuestUpload::image(1080, 1350, 'gif'), 'p.jpg')], 'photo'],
    'wrong photo dimensions' => [fn () => ['photo' => GuestUpload::file(GuestUpload::image(1000, 1350))], 'photo'],
    'landscape photo sent as portrait' => [fn () => ['photo' => GuestUpload::file(GuestUpload::image(1920, 1080))], 'photo'],
    'thumb too large' => [fn () => ['thumb' => GuestUpload::file(GuestUpload::image(480, 600), 'thumb.jpg')], 'thumb'],
    'thumb wrong orientation' => [fn () => ['thumb' => GuestUpload::file(GuestUpload::image(400, 225), 'thumb.jpg')], 'thumb'],
    'photo over 3 MB' => [fn () => ['photo' => UploadedFile::fake()->create('photo.jpg', 3100, 'image/jpeg')], 'photo'],
    'thumb over 300 KB' => [fn () => ['thumb' => UploadedFile::fake()->create('thumb.jpg', 310, 'image/jpeg')], 'thumb'],
    'missing photo' => [fn () => ['photo' => null], 'photo'],
    'bad orientation' => [fn () => ['orientation' => 'square'], 'orientation'],
    'unknown filter' => [fn () => ['filter' => 'evil'], 'filter'],
    'html in guest name' => [fn () => ['guest_name' => '<b>Ana</b>'], 'guest_name'],
    'unknown frame' => [fn () => ['frame_key' => 'not_a_frame'], 'frame_key'],
]);

it('accepts only frames active on the event', function () {
    $this->event->frames()->detach(
        $this->event->activeFrames()->firstWhere('template_key', 'floral')->id,
    );

    upload($this->event, GuestUpload::payload(['frame_key' => 'floral']))
        ->assertUnprocessable()->assertJsonValidationErrors('frame_key');

    $response = upload($this->event, GuestUpload::payload(['frame_key' => 'led_billboard']))->assertCreated();

    $photo = Photo::withoutGlobalScopes()->where('uuid', $response->json('uuid'))->sole();
    expect($photo->frame->template_key)->toBe('led_billboard');
});

it('validates guest texts against the frame config', function (string $texts, bool $ok) {
    $response = upload($this->event, GuestUpload::payload(['frame_key' => 'wanted_poster', 'guest_texts' => $texts]));

    if ($ok) {
        $response->assertCreated();
        expect(Photo::withoutGlobalScopes()->sole()->guest_texts)->toBe(['guest_name' => 'Billy the Kid']);
    } else {
        $response->assertUnprocessable()->assertJsonValidationErrors('guest_texts');
    }
})->with([
    'valid' => ['{"guest_name":"Billy the Kid"}', true],
    'too long' => [json_encode(['guest_name' => str_repeat('a', 41)]), false],
    'html' => ['{"guest_name":"<script>alert(1)</script>"}', false],
    'organizer field' => ['{"reward":"1 milhão"}', false],
    'not an object' => ['["a"]', false],
    'invalid json' => ['{guest', false],
    'nested value' => ['{"guest_name":{"x":"y"}}', false],
]);

it('rejects guest texts when no frame is chosen', function () {
    upload($this->event, GuestUpload::payload(['guest_texts' => '{"guest_name":"Ana"}']))
        ->assertUnprocessable()->assertJsonValidationErrors('guest_texts');
});

it('returns 410 when the event is not live', function (array $attributes) {
    $this->event->forceFill($attributes)->save();

    upload($this->event, GuestUpload::payload())
        ->assertStatus(410)
        ->assertJson(['message' => __('upload.errors.event_not_live')]);

    expect(Photo::withoutGlobalScopes()->count())->toBe(0);
})->with([
    'ended by time' => [['starts_at' => now()->subHours(3), 'ends_at' => now()->subMinute()]],
    'closed by organizer' => [['closed_at' => now()->subMinute()]],
    'scheduled' => [['starts_at' => now()->addHour(), 'ends_at' => now()->addHours(3)]],
]);

it('returns 410 right after the event ends', function () {
    upload($this->event, GuestUpload::payload())->assertCreated();

    $this->travelTo($this->event->ends_at->copy()->addSecond());

    upload($this->event, GuestUpload::payload())->assertStatus(410);
});

it('returns 404 for a draft event or a token from another event', function () {
    $other = GuestUpload::liveEvent();
    $draft = Event::factory()->for($this->account)->create();

    upload($this->event, GuestUpload::payload(), null, $other->uploadToken())->assertNotFound();
    upload($this->event, GuestUpload::payload(), null, str_repeat('a', 64))->assertNotFound();
    upload($draft, GuestUpload::payload())->assertStatus(410);

    expect(Photo::withoutGlobalScopes()->count())->toBe(0);
});

it('enforces the 20-photo limit of trial events', function () {
    $trialAccount = Account::factory()->trialInProgress()->create();
    $event = GuestUpload::liveEvent($trialAccount, ['is_trial' => true]);

    foreach (range(1, 20) as $i) {
        GuestUpload::storedPhoto($event, 'pending', hash('sha256', "device-{$i}"));
    }

    upload($event, GuestUpload::payload(), $this->device)
        ->assertStatus(429)
        ->assertJson(['message' => __('upload.errors.event_limit')]);

    expect(Photo::withoutGlobalScopes()->where('event_id', $event->id)->count())->toBe(20);
});

it('enforces the plan photo limit', function () {
    $this->account->plan->forceFill(['max_photos_per_event' => 2])->save();

    upload($this->event, GuestUpload::payload(), 'A'.str_repeat('1', 39))->assertCreated();
    upload($this->event, GuestUpload::payload(), 'B'.str_repeat('1', 39))->assertCreated();
    upload($this->event, GuestUpload::payload(), 'C'.str_repeat('1', 39))->assertStatus(429);
});

it('enforces max photos per guest by device', function () {
    $this->event->forceFill(['max_photos_per_guest' => 2])->save();

    upload($this->event, GuestUpload::payload(), $this->device)->assertCreated();
    upload($this->event, GuestUpload::payload(), $this->device)->assertCreated();
    upload($this->event, GuestUpload::payload(), $this->device)
        ->assertStatus(429)
        ->assertJson(['message' => __('upload.errors.guest_limit')]);

    // Outro aparelho continua podendo enviar.
    upload($this->event, GuestUpload::payload(), str_repeat('E', 40))->assertCreated();
});

it('rate limits uploads per device', function () {
    foreach (range(1, 6) as $i) {
        upload($this->event, GuestUpload::payload(), $this->device)->assertCreated();
    }

    upload($this->event, GuestUpload::payload(), $this->device)->assertStatus(429);
});

it('returns 507 and leaves no files or records when storage fails', function () {
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('path')->andReturn(sys_get_temp_dir());
    $disk->shouldReceive('put')->andReturn(false);
    $disk->shouldReceive('delete')->once()->andReturn(true);
    Storage::set('local', $disk);

    upload($this->event, GuestUpload::payload())
        ->assertStatus(507)
        ->assertJson(['message' => __('upload.errors.storage')]);

    expect(Photo::withoutGlobalScopes()->count())->toBe(0);
});

it('stores the photo in the event account even when an organizer of another account is logged in', function () {
    $intruder = ownerFor(Account::factory()->active()->create());

    $response = $this->actingAs($intruder)
        ->post(GuestUpload::storeUrl($this->event), GuestUpload::payload(), ['Accept' => 'application/json'])
        ->assertCreated();

    expect(Photo::withoutGlobalScopes()->where('uuid', $response->json('uuid'))->value('account_id'))
        ->toBe($this->account->id);
});

it('lets the guest delete their own pending photo', function () {
    $uuid = upload($this->event, GuestUpload::payload(), $this->device)->assertCreated()->json('uuid');
    $photo = Photo::withoutGlobalScopes()->where('uuid', $uuid)->sole();

    $url = route('guest.photos.destroy', ['slug' => $this->event->slug, 'photo' => $uuid, 't' => $this->event->uploadToken()]);

    // Outro aparelho: 404.
    $this->withCookie(GuestDevice::COOKIE, str_repeat('Z', 40))->withCredentials()->deleteJson($url)->assertNotFound();

    // Token de outro evento: 404.
    $other = GuestUpload::liveEvent();
    $this->withCookie(GuestDevice::COOKIE, $this->device)
        ->withCredentials()->deleteJson(route('guest.photos.destroy', ['slug' => $this->event->slug, 'photo' => $uuid, 't' => $other->uploadToken()]))
        ->assertNotFound();

    $this->withCookie(GuestDevice::COOKIE, $this->device)->withCredentials()->deleteJson($url)->assertNoContent();

    expect(Photo::withoutGlobalScopes()->where('uuid', $uuid)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing([$photo->original_path, $photo->thumb_path]);
});

it('lets the guest delete an approved photo: files go, a tombstone takes it off the screen', function () {
    $photo = GuestUpload::storedPhoto($this->event, 'approved', GuestDevice::hashId($this->device));

    $this->withCookie(GuestDevice::COOKIE, $this->device)
        ->withCredentials()->deleteJson(route('guest.photos.destroy', ['slug' => $this->event->slug, 'photo' => $photo->uuid, 't' => $this->event->uploadToken()]))
        ->assertNoContent();

    $raw = Photo::withoutGlobalScopes()->findOrFail($photo->id);
    expect($raw->status)->toBe(Photo::STATUS_DELETED)
        ->and($raw->original_path)->toBeNull()
        ->and($raw->guest_name)->toBeNull();
    Storage::disk('local')->assertMissing([$photo->original_path, $photo->thumb_path]);
});

it('keeps event state checks on the server for the status column too', function () {
    // Coluna status desatualizada ("live") não engana: vale o cálculo pelos horários.
    $this->event->forceFill(['status' => EventStatus::Live, 'ends_at' => now()->subMinute()])->save();

    upload($this->event, GuestUpload::payload())->assertStatus(410);
});
