<?php

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Frame;
use App\Models\User;
use App\Services\Frames\CustomFrameImage;
use App\Services\Frames\FrameGuide;
use Database\Seeders\FrameSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FrameFixtures;
use Tests\Support\GuestUpload;

/*
| Módulo 8: molduras personalizadas, somente plano pago.
*/

beforeEach(function () {
    Storage::fake('local');
    (new FrameSeeder())->run();
});

function uploadFrame(User $user, array $files, string $name = 'Minha marca')
{
    return test()->actingAs($user)->post(route('frames.store'), array_merge(['name' => $name], $files));
}

// ------------------------------------------------------------------ plano

it('centralizes the paid-plan rule in Account::canUseCustomFrames', function () {
    expect(Account::factory()->trial()->create()->canUseCustomFrames())->toBeFalse()
        ->and(Account::factory()->trialEnded()->create()->canUseCustomFrames())->toBeFalse()
        ->and(FrameFixtures::paidAccount(false)->canUseCustomFrames())->toBeFalse()
        ->and(FrameFixtures::paidAccount()->canUseCustomFrames())->toBeTrue();
});

it('shows the templates and the friendly paid-plan notice to a trial account, without upload form', function () {
    $owner = ownerFor(Account::factory()->trial()->create());

    $this->actingAs($owner)->get(route('frames.index'))
        ->assertOk()
        ->assertSee('Molduras personalizadas com a sua marca estão disponíveis nos planos pagos.')
        ->assertSee('Painel de LED')
        ->assertDontSee('name="portrait"', false)
        ->assertDontSee('multipart/form-data', false);
});

it('refuses uploads from a trial account with 403 and the same message', function () {
    $owner = ownerFor(Account::factory()->trial()->create());

    uploadFrame($owner, ['portrait' => FrameFixtures::upload(FrameFixtures::portraitPng())])
        ->assertForbidden()
        ->assertSee('Molduras personalizadas com a sua marca estão disponíveis nos planos pagos.');

    expect(Frame::withoutGlobalScopes()->where('type', 'custom')->count())->toBe(0);
});

it('refuses uploads when the plan does not allow custom frames', function () {
    $owner = ownerFor(FrameFixtures::paidAccount(false));

    $this->actingAs($owner)->get(route('frames.index'))
        ->assertOk()
        ->assertSee('Molduras personalizadas com a sua marca estão disponíveis nos planos pagos.');

    uploadFrame($owner, ['portrait' => FrameFixtures::upload(FrameFixtures::portraitPng())])->assertForbidden();
    expect(Frame::withoutGlobalScopes()->where('type', 'custom')->count())->toBe(0);
});

// ----------------------------------------------------------------- upload

it('accepts valid PNGs from a paid account, stores them privately and generates the config', function () {
    $account = FrameFixtures::paidAccount();
    $owner = ownerFor($account);

    $this->actingAs($owner)->get(route('frames.index'))->assertOk()->assertSee('name="portrait"', false);

    uploadFrame($owner, [
        'portrait' => FrameFixtures::upload(FrameFixtures::portraitPng()),
        'landscape' => FrameFixtures::upload(FrameFixtures::landscapePng()),
    ])->assertRedirect(route('frames.index'))->assertSessionHasNoErrors();

    $frame = Frame::withoutGlobalScopes()->where('type', 'custom')->sole();
    $dir = 'tenants/'.$account->uuid.'/frames/'.$frame->uuid;

    expect($frame->account_id)->toBe($account->id)
        ->and($frame->portrait_path)->toBe("{$dir}/portrait.png")
        ->and($frame->landscape_path)->toBe("{$dir}/landscape.png")
        ->and($frame->config['orientations']['portrait'])->toBe([
            'width' => 1080, 'height' => 1350, 'background' => null,
            'window' => FrameFixtures::PORTRAIT_WINDOW,
            'overlay' => 'portrait.png', 'texts' => [], 'effect' => null,
        ])
        ->and($frame->config['orientations']['landscape']['window'])->toBe(FrameFixtures::LANDSCAPE_WINDOW)
        ->and($frame->config['key'])->toBe($frame->uuid);

    Storage::disk('local')->assertExists(["{$dir}/portrait.png", "{$dir}/landscape.png", "{$dir}/config.json"]);
    expect(is_dir(public_path('frames/'.$frame->uuid)))->toBeFalse();

    expect(AuditLog::query()->where('action', 'frame.uploaded')->where('subject_id', $frame->id)->exists())->toBeTrue();
});

it('accepts a single orientation and exposes only it', function () {
    $owner = ownerFor(FrameFixtures::paidAccount());

    uploadFrame($owner, ['landscape' => FrameFixtures::upload(FrameFixtures::landscapePng())])->assertSessionHasNoErrors();

    $frame = Frame::withoutGlobalScopes()->where('type', 'custom')->sole();
    expect($frame->orientations())->toBe(['landscape'])
        ->and($frame->portrait_path)->toBeNull();
});

it('requires at least one file and a name', function () {
    $owner = ownerFor(FrameFixtures::paidAccount());

    uploadFrame($owner, [])->assertSessionHasErrors('portrait');
    uploadFrame($owner, ['portrait' => FrameFixtures::upload(FrameFixtures::portraitPng())], '')->assertSessionHasErrors('name');
});

it('rejects invalid files', function (Closure $file, string $field) {
    $owner = ownerFor(FrameFixtures::paidAccount());

    uploadFrame($owner, [$field => $file()])->assertSessionHasErrors($field);

    expect(Frame::withoutGlobalScopes()->where('type', 'custom')->count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'JPEG renomeado para .png' => [fn () => FrameFixtures::upload(GuestUpload::image(1080, 1350), 'frame.png'), 'portrait'],
    'PHP com cabeçalho PNG' => [fn () => FrameFixtures::upload("\x89PNG\r\n\x1a\n<?php system(\$_GET['c']); ?>", 'frame.png'), 'portrait'],
    'dimensões erradas (retrato)' => [fn () => FrameFixtures::upload(FrameFixtures::png(1080, 1080, ['x' => 100, 'y' => 100, 'w' => 800, 'h' => 800])), 'portrait'],
    'dimensões trocadas (paisagem no campo retrato)' => [fn () => FrameFixtures::upload(FrameFixtures::landscapePng()), 'portrait'],
    'sem canal alfa' => [fn () => FrameFixtures::upload(FrameFixtures::png(1080, 1350, null, false)), 'portrait'],
    'com alfa mas sem área transparente' => [fn () => FrameFixtures::upload(FrameFixtures::portraitPng(null)), 'portrait'],
    'janela menor que 30%' => [fn () => FrameFixtures::upload(FrameFixtures::portraitPng(['x' => 100, 'y' => 100, 'w' => 300, 'h' => 300])), 'portrait'],
    'maior que 2 MB' => [fn () => UploadedFile::fake()->create('frame.png', 2100, 'image/png'), 'landscape'],
]);

it('re-encodes the PNG from scratch, dropping extra chunks and trailing data', function () {
    $owner = ownerFor(FrameFixtures::paidAccount());
    $dirty = FrameFixtures::withChunk(FrameFixtures::portraitPng(), 'tEXt', "Comment\0<?php echo 'pwned'; ?>").'<?php evil(); ?>';

    expect($dirty)->toContain('tEXt')->toContain('pwned');

    uploadFrame($owner, ['portrait' => FrameFixtures::upload($dirty)])->assertSessionHasNoErrors();

    $frame = Frame::withoutGlobalScopes()->where('type', 'custom')->sole();
    $stored = Storage::disk('local')->get($frame->portrait_path);

    expect($stored)->not->toContain('tEXt')
        ->not->toContain('pwned')
        ->not->toContain('<?php')
        ->and(str_ends_with($stored, "IEND\xAE\x42\x60\x82"))->toBeTrue()
        ->and(getimagesizefromstring($stored)[0])->toBe(1080);

    // alfa preservado: a janela continua transparente e a borda opaca
    $img = imagecreatefromstring($stored);
    expect((imagecolorat($img, 500, 600) >> 24) & 0x7F)->toBe(127)
        ->and((imagecolorat($img, 5, 1300) >> 24) & 0x7F)->toBe(0);
});

it('detects the bounding box of the transparent area', function (array $window) {
    $service = new CustomFrameImage();
    $result = $service->process(FrameFixtures::upload(FrameFixtures::portraitPng($window)), 'portrait');

    expect($result['window'])->toBe($window);
})->with([
    'janela central' => [['x' => 100, 'y' => 150, 'w' => 800, 'h' => 900]],
    'encostada no canto' => [['x' => 0, 'y' => 0, 'w' => 700, 'h' => 900]],
    'até a borda direita/inferior' => [['x' => 280, 'y' => 450, 'w' => 800, 'h' => 900]],
]);

it('limits custom frames to 20 per account', function () {
    $account = FrameFixtures::paidAccount();
    $owner = ownerFor($account);

    foreach (range(1, Frame::MAX_CUSTOM_PER_ACCOUNT) as $i) {
        FrameFixtures::customFrame($account, "Moldura {$i}");
    }

    uploadFrame($owner, ['portrait' => FrameFixtures::upload(FrameFixtures::portraitPng())])->assertSessionHasErrors('name');

    expect(Frame::withoutGlobalScopes()->where('account_id', $account->id)->where('type', 'custom')->count())
        ->toBe(Frame::MAX_CUSTOM_PER_ACCOUNT);
});

// ----------------------------------------------------- gerenciar e excluir

it('activates a custom frame per event, ignoring events from other accounts', function () {
    $account = FrameFixtures::paidAccount();
    $other = Event::factory()->live()->create();
    $mine = Event::factory()->for($account)->live()->create();
    $mine2 = Event::factory()->for($account)->live()->create();
    $owner = ownerFor($account);

    uploadFrame($owner, ['portrait' => FrameFixtures::upload(FrameFixtures::portraitPng())]);
    $frame = Frame::withoutGlobalScopes()->where('type', 'custom')->sole();

    $this->actingAs($owner)->put(route('frames.events.update', $frame), ['events' => [$mine->uuid, $other->uuid]])
        ->assertRedirect(route('frames.index'));

    expect($mine->activeFrames()->pluck('uuid'))->toContain($frame->uuid)
        ->and($mine2->activeFrames()->pluck('uuid'))->not->toContain($frame->uuid)
        ->and($other->frames()->withoutGlobalScopes()->pluck('frames.uuid'))->not->toContain($frame->uuid);

    // desmarcar desativa
    $this->actingAs($owner)->put(route('frames.events.update', $frame), ['events' => [$mine2->uuid]]);
    expect($mine->fresh()->activeFrames()->pluck('uuid'))->not->toContain($frame->uuid)
        ->and($mine2->fresh()->activeFrames()->pluck('uuid'))->toContain($frame->uuid);
});

it('deletes a custom frame, its files and records the audit log', function () {
    $owner = ownerFor(FrameFixtures::paidAccount());
    uploadFrame($owner, ['portrait' => FrameFixtures::upload(FrameFixtures::portraitPng())]);
    $frame = Frame::withoutGlobalScopes()->where('type', 'custom')->sole();
    $dir = $frame->storageDirectory();

    $this->actingAs($owner)->delete(route('frames.destroy', $frame))->assertRedirect(route('frames.index'));

    expect(Frame::withoutGlobalScopes()->whereKey($frame->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing($dir.'/portrait.png');
    expect(Storage::disk('local')->allFiles($dir))->toBe([])
        ->and(AuditLog::query()->where('action', 'frame.deleted')->count())->toBe(1);
});

it('lets a moderator only view frames', function () {
    $account = FrameFixtures::paidAccount();
    $frame = FrameFixtures::customFrame($account);
    $moderator = User::factory()->for($account)->moderator()->create();

    $this->actingAs($moderator)->get(route('frames.index'))->assertOk()->assertSee($frame->name)->assertDontSee('name="portrait"', false);
    uploadFrame($moderator, ['portrait' => FrameFixtures::upload(FrameFixtures::portraitPng())])->assertForbidden();
    $this->actingAs($moderator)->delete(route('frames.destroy', $frame))->assertForbidden();
    $this->actingAs($moderator)->put(route('frames.events.update', $frame), ['events' => []])->assertForbidden();
});

it('does not let custom-frame routes touch system templates', function () {
    $owner = ownerFor(FrameFixtures::paidAccount());
    $template = Frame::withoutGlobalScopes()->where('template_key', 'floral')->sole();

    $this->actingAs($owner)->delete(route('frames.destroy', $template))->assertNotFound();
    $this->actingAs($owner)->put(route('frames.events.update', $template), ['events' => []])->assertNotFound();
    $this->actingAs($owner)->get(route('frames.image', ['frame' => $template, 'orientation' => 'portrait']))->assertNotFound();
});

// ---------------------------------------------------------- isolamento

it('isolates custom frames between accounts (404)', function () {
    $accountB = FrameFixtures::paidAccount();
    $frameB = FrameFixtures::customFrame($accountB, 'Marca da conta B');
    Storage::disk('local')->put($frameB->portrait_path, FrameFixtures::portraitPng());
    $ownerA = ownerFor(FrameFixtures::paidAccount());

    $this->actingAs($ownerA)->get(route('frames.index'))->assertOk()->assertDontSee('Marca da conta B');
    $this->actingAs($ownerA)->get(route('frames.image', ['frame' => $frameB->uuid, 'orientation' => 'portrait']))->assertNotFound();
    $this->actingAs($ownerA)->put(route('frames.events.update', $frameB->uuid), ['events' => []])->assertNotFound();
    $this->actingAs($ownerA)->delete(route('frames.destroy', $frameB->uuid))->assertNotFound();

    expect(Frame::withoutGlobalScopes()->whereKey($frameB->id)->exists())->toBeTrue();
    Storage::disk('local')->assertExists($frameB->portrait_path);

    // o dono vê a própria imagem
    $ownerB = ownerFor($accountB);
    $this->actingAs($ownerB)->get(route('frames.image', ['frame' => $frameB->uuid, 'orientation' => 'portrait']))
        ->assertOk()->assertHeader('Content-Type', 'image/png');
});

// ------------------------------------------------------------ convidado

it('gives guests the active custom frames in the payload, with loadable config and images', function () {
    $account = FrameFixtures::paidAccount();
    $owner = ownerFor($account);
    $event = GuestUpload::liveEvent($account);

    uploadFrame($owner, ['portrait' => FrameFixtures::upload(FrameFixtures::portraitPng())]);
    $frame = Frame::withoutGlobalScopes()->where('type', 'custom')->sole();
    $this->actingAs($owner)->put(route('frames.events.update', $frame), ['events' => [$event->uuid]]);
    auth()->logout();

    $payload = $this->get(GuestUpload::pageUrl($event))->assertOk()->viewData('event');
    $custom = collect($payload['frames'])->firstWhere('key', $frame->uuid);
    $token = $event->uploadToken();

    expect($custom)->not->toBeNull()
        ->and($custom['orientations'])->toBe(['portrait'])
        ->and($custom['configUrl'])->toBe("/e/{$event->slug}/frames/{$frame->uuid}/config.json?t={$token}")
        ->and(collect($payload['frames'])->firstWhere('key', 'floral')['orientations'])->toBe(['portrait', 'landscape']);

    $this->get($custom['configUrl'])->assertOk()->assertJsonPath('orientations.portrait.overlay', 'portrait.png');
    $this->get("/e/{$event->slug}/frames/{$frame->uuid}/portrait.png?t={$token}")
        ->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->get("/e/{$event->slug}/frames/{$frame->uuid}/landscape.png?t={$token}")->assertNotFound();
    $this->get("/e/{$event->slug}/frames/{$frame->uuid}/portrait.png")->assertNotFound();
    $this->get("/e/{$event->slug}/frames/{$frame->uuid}/portrait.png?t=".str_repeat('a', 64))->assertNotFound();

    // o convidado pode enviar foto com a moldura custom
    $this->post(GuestUpload::storeUrl($event), GuestUpload::payload(['frame_key' => $frame->uuid]))->assertCreated();
});

it('does not open another account custom frame through an event token', function () {
    $accountB = FrameFixtures::paidAccount();
    $eventB = GuestUpload::liveEvent($accountB);
    $frameB = FrameFixtures::customFrame($accountB);
    Storage::disk('local')->put($frameB->portrait_path, FrameFixtures::portraitPng());
    $eventB->frames()->attach($frameB->id, ['sort_order' => 100]);

    $eventA = GuestUpload::liveEvent(FrameFixtures::paidAccount());
    // mesmo forçando o vínculo no banco, frame de outra conta não aparece
    $eventA->frames()->attach($frameB->id, ['sort_order' => 100]);

    $tokenA = $eventA->uploadToken();
    $this->get("/e/{$eventA->slug}/frames/{$frameB->uuid}/config.json?t={$tokenA}")->assertNotFound();
    $this->get("/e/{$eventA->slug}/frames/{$frameB->uuid}/portrait.png?t={$tokenA}")->assertNotFound();
    // token do evento A com o slug do evento B
    $this->get("/e/{$eventB->slug}/frames/{$frameB->uuid}/portrait.png?t={$tokenA}")->assertNotFound();
    expect(collect($this->get(GuestUpload::pageUrl($eventA))->viewData('event')['frames'])->pluck('key'))
        ->not->toContain($frameB->uuid);

    // no evento B (dono) abre
    $this->get("/e/{$eventB->slug}/frames/{$frameB->uuid}/portrait.png?t={$eventB->uploadToken()}")->assertOk();
});

it('hides custom frames from guests when not active in the event or the plan is downgraded', function () {
    $account = FrameFixtures::paidAccount();
    $event = GuestUpload::liveEvent($account);
    $frame = FrameFixtures::customFrame($account);
    Storage::disk('local')->put($frame->portrait_path, FrameFixtures::portraitPng());
    $url = "/e/{$event->slug}/frames/{$frame->uuid}/config.json?t={$event->uploadToken()}";

    $this->get($url)->assertNotFound(); // não ativada no evento

    $event->frames()->attach($frame->id, ['sort_order' => 100]);
    $this->get($url)->assertOk();

    $account->plan->forceFill(['allows_custom_frames' => false])->save();
    $this->get($url)->assertNotFound();
    expect(collect($this->get(GuestUpload::pageUrl($event))->viewData('event')['frames'])->pluck('key'))
        ->not->toContain($frame->uuid);
    $this->post(GuestUpload::storeUrl($event), GuestUpload::payload(['frame_key' => $frame->uuid]))
        ->assertSessionHasErrors('frame_key');
});

// ------------------------------------------------------------- gabarito

it('serves generated guide PNGs that pass the frame validation with the suggested window', function (string $orientation) {
    $owner = ownerFor(Account::factory()->trial()->create());

    $response = $this->actingAs($owner)->get(route('frames.guide', ['orientation' => $orientation]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    $png = $response->getContent();
    [$w, $h] = CustomFrameImage::SIZES[$orientation];
    expect(getimagesizefromstring($png)[0])->toBe($w)->and(getimagesizefromstring($png)[1])->toBe($h);

    $result = (new CustomFrameImage())->process(FrameFixtures::upload($png), $orientation);
    expect($result['window'])->toBe(FrameGuide::WINDOWS[$orientation]);
})->with(['portrait', 'landscape']);
