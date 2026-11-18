<?php

use App\Models\Account;
use App\Models\Photo;
use App\Services\GuestDevice;
use Illuminate\Support\Facades\Storage;
use Tests\Support\GuestUpload;

/*
| LGPD/GDPR: consentimento do convidado exigido e registrado no servidor; cookie do aparelho.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->event = GuestUpload::liveEvent(Account::factory()->active()->create(['name' => 'Fotos da Ana']));
});

it('refuses an upload without consent (422) and stores nothing', function (array $override) {
    $payload = GuestUpload::payload();
    foreach ($override as $key => $value) {
        if ($value === null) {
            unset($payload[$key]);
        } else {
            $payload[$key] = $value;
        }
    }

    $this->postJson(GuestUpload::storeUrl($this->event), $payload)->assertUnprocessable();

    expect(Photo::withoutGlobalScopes()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'sem consent' => [['consent' => null]],
    'consent=0' => [['consent' => '0']],
    'sem versão' => [['consent_version' => null]],
    'versão antiga' => [['consent_version' => '2020-01-01']],
]);

it('records the consent version and timestamp with the photo', function () {
    $this->freezeTime();

    $uuid = $this->postJson(GuestUpload::storeUrl($this->event), GuestUpload::payload())->assertCreated()->json('uuid');
    $photo = Photo::withoutGlobalScopes()->where('uuid', $uuid)->sole();

    expect($photo->consent_version)->toBe(config('photoparty.privacy.consent_version'))
        ->and($photo->consented_at->timestamp)->toBe(now()->timestamp);
});

it('shows the controller, retention and privacy links in the guest page payload', function () {
    $response = $this->get(GuestUpload::pageUrl($this->event))->assertOk();
    preg_match('#<script type="application/json" id="pp-event">(.*?)</script>#s', $response->getContent(), $m);
    $payload = json_decode($m[1], true);

    expect($payload['consentVersion'])->toBe(config('photoparty.privacy.consent_version'))
        ->and($payload['privacy']['controller'])->toBe('Fotos da Ana')
        ->and($payload['privacy']['retentionDays'])->toBeGreaterThan(0)
        ->and($payload['privacy']['policyUrl'])->toStartWith('/privacidade')
        ->and($payload['privacy']['requestUrl'])->toStartWith('/privacidade/solicitacao')
        ->and($payload['myPhotosUrl'])->toStartWith('/e/'.$this->event->slug.'/my-photos?t=');
});

it('sets the device cookie for 30 days, httponly, samesite=lax and secure over https', function () {
    $url = str_replace('http://', 'https://', GuestUpload::pageUrl($this->event));
    $response = $this->get($url)->assertOk();

    $cookie = collect($response->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === GuestDevice::COOKIE);

    expect($cookie)->not->toBeNull()
        ->and($cookie->isSecure())->toBeTrue()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax')
        ->and($cookie->getExpiresTime())->toBeLessThanOrEqual(now()->addDays(30)->addMinute()->timestamp)
        ->and($cookie->getExpiresTime())->toBeGreaterThan(now()->addDays(29)->timestamp);
});

it('sets only strictly necessary cookies on the guest page', function () {
    $response = $this->get(GuestUpload::pageUrl($this->event))->assertOk();
    $names = collect($response->headers->getCookies())->map->getName()->sort()->values()->all();

    expect($names)->toBe(collect([config('session.cookie'), 'XSRF-TOKEN', GuestDevice::COOKIE])->sort()->values()->all());
});

it('has no third-party scripts, styles, fonts or trackers in views, JS or CSS', function () {
    $files = array_merge(
        glob(resource_path('views/**/*.blade.php')) ?: [],
        glob(resource_path('views/*/*/*.blade.php')) ?: [],
        glob(resource_path('js/**/*.js')) ?: [],
        glob(resource_path('css/*.css')) ?: [],
    );

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $src = file_get_contents($file);
        // Únicas URLs absolutas toleradas: namespace SVG e o exemplo de link no formulário de privacidade.
        $src = str_replace(['http://www.w3.org', 'https://photo.uailabs.net/e/'], '', $src);
        expect(preg_match('#<(script|link|img|iframe)[^>]+(src|href)="https?://#i', $src))->toBe(0, $file)
            ->and(preg_match('#(googletagmanager|google-analytics|gtag\(|fbq\(|fonts\.googleapis|cdn\.jsdelivr|unpkg\.com|recaptcha|hotjar)#i', $src))->toBe(0, $file);
    }
});
