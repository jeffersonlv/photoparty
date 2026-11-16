<?php

use App\Http\Middleware\GuardMediaNotFound;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\GuestUpload;

/*
| Módulo 4: entrega de imagens /media (PhotoPolicy + URL assinada).
*/

beforeEach(function () {
    Storage::fake('local');

    $this->accountA = Account::factory()->active()->create();
    $this->accountB = Account::factory()->active()->create();

    $this->eventA = GuestUpload::liveEvent($this->accountA);
    $this->eventB = GuestUpload::liveEvent($this->accountB);

    $this->photoA = GuestUpload::storedPhoto($this->eventA);
    $this->photoB = GuestUpload::storedPhoto($this->eventB);

    // Usuários criados por último (depois de todos os registros de outras contas).
    $this->ownerA = ownerFor($this->accountA);
    $this->moderatorA = User::factory()->for($this->accountA)->moderator()->create();
});

it('serves the photo to users of the same account with safe headers', function (string $user, string $variant) {
    $response = $this->actingAs($this->{$user})
        ->get(route('media.show', ['photo' => $this->photoA->uuid, 'variant' => $variant]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('max-age=600')
        ->and(file_get_contents($response->getFile()->getPathname()))
        ->toBe(Storage::disk('local')->get($this->photoA->pathFor($variant)));
})->with([
    'owner thumb' => ['ownerA', 'thumb'],
    'owner original' => ['ownerA', 'original'],
    'moderator thumb' => ['moderatorA', 'thumb'],
]);

it('returns 404 for a photo of another account', function (string $variant) {
    $this->actingAs($this->ownerA)
        ->get(route('media.show', ['photo' => $this->photoB->uuid, 'variant' => $variant]))
        ->assertNotFound();
})->with(['thumb', 'original']);

it('denies as not found through the policy too', function () {
    expect(fn () => $this->ownerA->can('view', $this->photoB))->not->toThrow(Exception::class)
        ->and($this->ownerA->can('view', $this->photoB))->toBeFalse()
        ->and(Gate::forUser($this->ownerA)->inspect('view', $this->photoB)->status())->toBe(404)
        ->and($this->ownerA->can('view', $this->photoA))->toBeTrue();
});

it('requires login for media.show and validates the variant', function () {
    $this->get(route('media.show', ['photo' => $this->photoA->uuid, 'variant' => 'thumb']))
        ->assertRedirect(route('login'));

    $this->actingAs($this->ownerA)->get('/media/'.$this->photoA->uuid.'/raw')->assertNotFound();
    $this->actingAs($this->ownerA)->get('/media/'.$this->photoA->id.'/thumb')->assertNotFound();
});

it('lets the admin view photos and audits the access', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('media.show', ['photo' => $this->photoB->uuid, 'variant' => 'thumb']))
        ->assertOk();

    expect(AuditLog::where('action', 'media.admin_view')->where('subject_id', $this->photoB->id)->exists())->toBeTrue();
});

it('serves approved photos through a temporary signed URL without login', function () {
    $this->get($this->photoB->signedMediaUrl('thumb'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');

    // Funciona mesmo com organizador de outra conta logado no mesmo navegador.
    $this->actingAs($this->ownerA)->get($this->photoB->signedMediaUrl('original'))->assertOk();
});

it('rejects tampered or expired signed URLs', function () {
    $url = $this->photoA->signedMediaUrl('thumb');

    // Assinatura alterada.
    $this->get(preg_replace('/signature=([0-9a-f])/', 'signature=0$1', $url))->assertForbidden();
    // Troca da foto (assinatura de A usada para B).
    $this->get(str_replace($this->photoA->uuid, $this->photoB->uuid, $url))->assertForbidden();
    // Troca da variante.
    $this->get(str_replace('/thumb?', '/original?', $url))->assertForbidden();
    // Validade estendida à mão.
    $this->get(preg_replace('/expires=\d+/', 'expires='.now()->addYear()->timestamp, $url))->assertForbidden();
    // Sem assinatura.
    $this->get(route('media.signed', ['photo' => $this->photoA->uuid, 'variant' => 'thumb']))->assertForbidden();

    $this->travel(11)->minutes();
    $this->get($url)->assertForbidden();
});

it('does not serve pending photos through signed URLs', function () {
    $pending = GuestUpload::storedPhoto($this->eventA, 'pending');

    $this->get($pending->signedMediaUrl('thumb'))->assertNotFound();
});

it('serves only thumbnails for accounts whose trial ended', function () {
    $this->accountA->forceFill(['status' => 'trial_ended'])->save();

    $this->actingAs($this->ownerA)->get(route('media.show', ['photo' => $this->photoA->uuid, 'variant' => 'thumb']))->assertOk();
    $this->actingAs($this->ownerA)->get(route('media.show', ['photo' => $this->photoA->uuid, 'variant' => 'original']))->assertNotFound();

    auth()->logout();
    $this->get($this->photoA->signedMediaUrl('thumb'))->assertOk();
    $this->get($this->photoA->signedMediaUrl('original'))->assertNotFound();
});

it('returns 404 when the file is missing on disk', function () {
    Storage::disk('local')->delete($this->photoA->thumb_path);

    $this->actingAs($this->ownerA)
        ->get(route('media.show', ['photo' => $this->photoA->uuid, 'variant' => 'thumb']))
        ->assertNotFound();
});

it('logs and throttles bursts of 404 on /media', function () {
    $logger = Mockery::mock();
    $logger->shouldReceive('warning')->once()->withArgs(fn ($message, $context) => str_contains($message, '/media')
        && $context['misses'] === GuardMediaNotFound::MAX_MISSES_PER_MINUTE
        && ! str_contains($context['path'], 'signature'));
    Log::shouldReceive('channel')->with('security')->once()->andReturn($logger);

    $this->actingAs($this->ownerA);

    foreach (range(1, GuardMediaNotFound::MAX_MISSES_PER_MINUTE) as $i) {
        $this->get(route('media.show', ['photo' => (string) Str::uuid(), 'variant' => 'thumb']))->assertNotFound();
    }

    // A partir daqui, até a valid foto própria é recusada por 1 minuto.
    $this->get(route('media.show', ['photo' => $this->photoA->uuid, 'variant' => 'thumb']))->assertStatus(429);

    $this->travel(61)->seconds();
    $this->get(route('media.show', ['photo' => $this->photoA->uuid, 'variant' => 'thumb']))->assertOk();
});
