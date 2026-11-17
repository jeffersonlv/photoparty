<?php

use App\Jobs\BuildEventExport;
use App\Models\Account;
use App\Models\Export;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\GuestUpload;

/*
| Módulo 7: galeria, prévia de baixíssima resolução, download e ZIP.
*/

beforeEach(function () {
    Storage::fake('local');

    $this->paid = Account::factory()->active()->create();
    $this->trial = Account::factory()->trialInProgress()->create();
    $this->other = Account::factory()->active()->create();

    $this->paidEvent = GuestUpload::liveEvent($this->paid);
    $this->trialEvent = GuestUpload::liveEvent($this->trial);
    $this->otherEvent = GuestUpload::liveEvent($this->other);

    $this->paidPhotos = collect(range(1, 3))->map(fn () => GuestUpload::storedPhoto($this->paidEvent));
    $this->pendingPaid = GuestUpload::storedPhoto($this->paidEvent, 'pending');
    $this->trialPhoto = GuestUpload::storedPhoto($this->trialEvent);
    $this->otherPhoto = GuestUpload::storedPhoto($this->otherEvent);

    $this->paidOwner = ownerFor($this->paid);
    $this->paidModerator = User::factory()->for($this->paid)->moderator()->create();
    $this->trialOwner = ownerFor($this->trial);
    $this->trialModerator = User::factory()->for($this->trial)->moderator()->create();
});

it('serves a tiny preview (max 200 px) instead of the thumb to non-active accounts and caches it', function (string $status) {
    $this->trial->forceFill(['status' => $status])->save();
    $url = route('media.show', ['photo' => $this->trialPhoto->uuid, 'variant' => 'thumb']);

    $response = $this->actingAs($this->trialOwner)->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    [$w, $h] = getimagesizefromstring(file_get_contents($response->getFile()->getPathname()));

    expect(max($w, $h))->toBeLessThanOrEqual(200)->and($w)->toBeGreaterThan(0);
    Storage::disk('local')->assertExists($this->trialPhoto->previewPath());
    expect($this->trialPhoto->previewPath())->toContain('/preview-v2/'.$this->trialPhoto->uuid.'.jpg');

    $this->actingAs($this->trialOwner)->get($url)->assertOk();
})->with(['trial', 'trial_ended', 'past_due', 'cancelled']);

it('returns 404 for the original to non-active accounts', function (string $status) {
    $this->trial->forceFill(['status' => $status])->save();

    $this->actingAs($this->trialOwner)
        ->get(route('media.show', ['photo' => $this->trialPhoto->uuid, 'variant' => 'original']))
        ->assertNotFound();
})->with(['trial', 'trial_ended', 'past_due', 'cancelled']);

it('keeps the normal thumb and the original for active accounts', function () {
    $this->actingAs($this->paidOwner)
        ->get(route('media.show', ['photo' => $this->paidPhotos[0]->uuid, 'variant' => 'thumb']))
        ->assertOk();
    Storage::disk('local')->assertMissing($this->paidPhotos[0]->previewPath());

    $this->actingAs($this->paidOwner)
        ->get(route('media.show', ['photo' => $this->paidPhotos[0]->uuid, 'variant' => 'original']))
        ->assertOk();
});

it('shows the gallery with preview notice and no download buttons to trial accounts', function () {
    $this->actingAs($this->trialOwner)
        ->get(route('events.gallery', $this->trialEvent))
        ->assertOk()
        ->assertSee('No plano de teste você vê apenas uma prévia. Assine um plano para baixar as fotos em alta resolução.')
        ->assertSee('/media/'.$this->trialPhoto->uuid.'/thumb')
        ->assertDontSee('/download')
        ->assertDontSee('Baixar todas');

    $this->actingAs($this->trialOwner)
        ->get(route('events.photos.download', [$this->trialEvent, $this->trialPhoto]))
        ->assertNotFound();

    $this->actingAs($this->trialOwner)->post(route('events.exports.store', $this->trialEvent))->assertNotFound();
    expect(Export::withoutGlobalScopes()->count())->toBe(0);
});

it('lets active accounts see approved photos with download buttons and ZIP', function () {
    $this->actingAs($this->paidOwner)
        ->get(route('events.gallery', $this->paidEvent))
        ->assertOk()
        ->assertSee('Baixar todas (ZIP)')
        ->assertSee(route('events.photos.download', [$this->paidEvent, $this->paidPhotos[1]]))
        ->assertDontSee($this->pendingPaid->uuid)
        ->assertDontSee('No plano de teste');
});

it('downloads the original with a friendly name for active accounts', function () {
    $response = $this->actingAs($this->paidOwner)
        ->get(route('events.photos.download', [$this->paidEvent, $this->paidPhotos[1]]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');

    expect($response->headers->get('Content-Disposition'))
        ->toContain('attachment')->toContain($this->paidEvent->slug.'-0002.jpg')
        ->and(file_get_contents($response->getFile()->getPathname()))
        ->toBe(Storage::disk('local')->get($this->paidPhotos[1]->original_path));

    $this->actingAs($this->paidOwner)
        ->get(route('events.photos.download', [$this->paidEvent, $this->pendingPaid]))
        ->assertNotFound();
});

it('does not let account A reach the gallery, photos or ZIP of account B', function () {
    $export = (function () {
        $e = new Export();
        $e->account_id = $this->other->id;
        $e->event_id = $this->otherEvent->id;
        $e->status = 'ready';
        $e->path = $this->other->storagePath().'/events/'.$this->otherEvent->uuid.'/exports/x.zip';
        $e->expires_at = now()->addDay();
        $e->save();
        Storage::disk('local')->put($e->path, 'zip');

        return $e;
    })();

    $this->actingAs($this->paidOwner);

    $this->get(route('events.gallery', $this->otherEvent))->assertNotFound();
    $this->get(route('events.photos.download', [$this->otherEvent, $this->otherPhoto]))->assertNotFound();
    $this->post(route('events.exports.store', $this->otherEvent))->assertNotFound();
    $this->get(route('events.exports.download', [$this->otherEvent, $export]))->assertNotFound();
    // Foto de outro evento da própria rota (scopeBindings) e ZIP de outro evento.
    $this->get(route('events.photos.download', [$this->paidEvent, $this->otherPhoto]))->assertNotFound();
    $this->get(route('events.exports.download', [$this->paidEvent, $export]))->assertNotFound();
});

it('queues one ZIP per event and does not enqueue duplicates', function () {
    Queue::fake();

    $this->actingAs($this->paidOwner)->post(route('events.exports.store', $this->paidEvent))
        ->assertRedirect(route('events.gallery', $this->paidEvent));
    $this->actingAs($this->paidOwner)->post(route('events.exports.store', $this->paidEvent))
        ->assertRedirect(route('events.gallery', $this->paidEvent));

    Queue::assertPushed(BuildEventExport::class, 1);
    expect(Export::withoutGlobalScopes()->where('event_id', $this->paidEvent->id)->count())->toBe(1);
});

it('builds the ZIP with the approved originals and serves it to the owner event only', function () {
    $this->actingAs($this->paidOwner)->post(route('events.exports.store', $this->paidEvent));

    $export = Export::withoutGlobalScopes()->where('event_id', $this->paidEvent->id)->firstOrFail();
    expect($export->status)->toBe('ready')
        ->and($export->path)->toContain($this->paid->storagePath().'/events/'.$this->paidEvent->uuid.'/exports/'.$export->uuid.'.zip')
        ->and($export->expires_at->isFuture())->toBeTrue();

    $zip = new ZipArchive();
    $zip->open(Storage::disk('local')->path($export->path));
    expect($zip->numFiles)->toBe(3)
        ->and($zip->getNameIndex(0))->toBe($this->paidEvent->slug.'-0001.jpg')
        ->and($zip->statIndex(0)['comp_method'])->toBe(ZipArchive::CM_STORE);
    $zip->close();

    $this->actingAs($this->paidModerator)
        ->get(route('events.gallery', $this->paidEvent))
        ->assertSee(route('events.exports.download', [$this->paidEvent, $export]));

    $this->actingAs($this->paidOwner)
        ->get(route('events.exports.download', [$this->paidEvent, $export]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/zip');

    // Conta que perdeu o plano não baixa mais o ZIP já pronto.
    $this->paid->forceFill(['status' => 'past_due'])->save();
    $this->actingAs($this->paidOwner)
        ->get(route('events.exports.download', [$this->paidEvent, $export]))
        ->assertNotFound();
});

it('marks the export as failed when there is nothing to zip', function () {
    $export = new Export();
    $export->account_id = $this->paid->id;
    $export->event_id = $this->paidEvent->id;
    $export->save();

    $this->paidPhotos->each(fn ($p) => Storage::disk('local')->delete($p->original_path));

    (new BuildEventExport($export->id))->handle();

    expect($export->refresh()->status)->toBe('failed');
});

it('purges expired ZIPs after 7 days', function () {
    Queue::fake();
    $this->actingAs($this->paidOwner)->post(route('events.exports.store', $this->paidEvent));
    $export = Export::withoutGlobalScopes()->firstOrFail();
    (new BuildEventExport($export->id))->handle();
    $export->refresh();
    $path = $export->path;
    Storage::disk('local')->assertExists($path);

    $this->artisan('exports:purge-expired')->assertSuccessful();
    Storage::disk('local')->assertExists($path);

    $this->travel(8)->days();
    $this->artisan('exports:purge-expired')->assertSuccessful();

    Storage::disk('local')->assertMissing($path);
    expect($export->refresh()->status)->toBe('expired')->and($export->path)->toBeNull();

    $this->actingAs($this->paidOwner)
        ->get(route('events.exports.download', [$this->paidEvent, $export]))
        ->assertNotFound();
});

it('lets a moderator see the gallery but follows the account rule for downloads', function () {
    $this->actingAs($this->trialModerator)->get(route('events.gallery', $this->trialEvent))->assertOk();
    $this->actingAs($this->trialModerator)
        ->get(route('events.photos.download', [$this->trialEvent, $this->trialPhoto]))
        ->assertNotFound();

    $this->actingAs($this->paidModerator)
        ->get(route('events.photos.download', [$this->paidEvent, $this->paidPhotos[0]]))
        ->assertOk();
});

it('gives the moderation screen the preview thumb and no original link to non-active accounts', function () {
    $this->trialEvent->forceFill(['status' => 'live'])->save();

    $this->actingAs($this->trialOwner)
        ->getJson(route('events.moderation.photos', [$this->trialEvent, 'status' => 'approved']))
        ->assertOk()
        ->assertJsonPath('photos.0.full', '/media/'.$this->trialPhoto->uuid.'/thumb');

    $this->actingAs($this->paidOwner)
        ->getJson(route('events.moderation.photos', [$this->paidEvent, 'status' => 'approved']))
        ->assertJsonPath('photos.0.full', fn ($v) => str_ends_with($v, '/original'));
});

it('does not introduce inline scripts or handlers in the gallery', function () {
    $html = $this->actingAs($this->paidOwner)->get(route('events.gallery', $this->paidEvent))->getContent();

    expect($html)->not->toMatch('/<script(?![^>]*\bsrc=)/i')
        ->and($html)->not->toMatch('/\son[a-z]+\s*=/i')
        ->and($html)->not->toContain('javascript:');
});

it('embeds the diagonal DEMONSTRAÇÃO band in the cached preview', function () {
    $response = $this->actingAs($this->trialOwner)
        ->get(route('media.show', ['photo' => $this->trialPhoto->uuid, 'variant' => 'thumb']))
        ->assertOk();

    $preview = imagecreatefromstring(file_get_contents($response->getFile()->getPathname()));
    $plain = imagescale(imagecreatefromstring(Storage::disk('local')->get($this->trialPhoto->thumb_path)), imagesx($preview), imagesy($preview));

    $mean = function ($img) {
        $sum = 0;
        $n = 0;
        for ($x = 0; $x < imagesx($img); $x += 2) {
            for ($y = 0; $y < imagesy($img); $y += 2) {
                $sum += (imagecolorat($img, $x, $y) >> 16) & 0xFF;
                $n++;
            }
        }

        return $sum / $n;
    };

    // Faixa escura semitransparente (e texto claro) altera bastante a imagem.
    expect($mean($preview))->toBeLessThan($mean($plain) * 0.93);

    // Idioma do evento muda o texto (DEMO x DEMONSTRAÇÃO).
    $en = GuestUpload::liveEvent($this->trial, ['language' => 'en']);
    $enPhoto = GuestUpload::storedPhoto($en);
    $enBytes = file_get_contents($this->actingAs($this->trialOwner)
        ->get(route('media.show', ['photo' => $enPhoto->uuid, 'variant' => 'thumb']))->getFile()->getPathname());
    expect($enBytes)->not->toBe(file_get_contents($response->getFile()->getPathname()));
});

it('shows a DEMONSTRAÇÃO label over thumbnails for non-active accounts only', function () {
    $this->actingAs($this->trialOwner)->get(route('events.gallery', $this->trialEvent))->assertSee('DEMONSTRAÇÃO');
    $this->actingAs($this->paidOwner)->get(route('events.gallery', $this->paidEvent))->assertDontSee('DEMONSTRAÇÃO');

    $this->actingAs($this->trialOwner)->get(route('events.moderation', $this->trialEvent))->assertSee('"demo":true', false);
    $this->actingAs($this->paidOwner)->get(route('events.moderation', $this->paidEvent))->assertSee('"demo":false', false)->assertDontSee('"demo":true', false);
});
