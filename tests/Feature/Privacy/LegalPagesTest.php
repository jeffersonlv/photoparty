<?php

use App\Models\Account;
use App\Models\Event;
use App\Models\User;

/*
| Páginas legais (LGPD + GDPR) e declaração do organizador ao criar evento.
*/

it('serves complete privacy and terms pages in both languages', function (string $path, string $lang, array $see) {
    $response = $this->get($path.'?lang='.$lang)->assertOk();
    foreach ($see as $text) {
        $response->assertSee($text);
    }
    $response->assertSee(config('photoparty.privacy.legal_version'));
})->with([
    ['/privacidade', 'pt_BR', ['Política de privacidade', 'controlador', 'operador', 'ANPD', 'Data Protection Commission', 'art. 18', 'pp_device', 'Hostinger', 'menores', 'Cookies']],
    ['/privacidade', 'en', ['Privacy policy', 'controller', 'processor', 'ANPD', 'Data Protection Commission', 'arts. 15 to 22', 'pp_device', 'Hostinger', 'minors', 'Cookies']],
    ['/termos', 'pt_BR', ['Termos de uso', 'acordo de tratamento de dados', 'suboperador', 'Sigilo', 'Incidentes']],
    ['/termos', 'en', ['Terms of use', 'data processing agreement', 'Sub-processors', 'Confidentiality', 'Incidents']],
]);

it('shows "[a preencher]" for empty operator fields and never invents them', function () {
    config(['photoparty.privacy_contact_email' => '']);

    $this->get('/privacidade')->assertOk()->assertSee('[a preencher]')->assertDontSee('CNPJ: 00');
    $this->get('/privacidade?lang=en')->assertOk()->assertSee('[to be completed]');
});

it('uses the configured privacy contact e-mail', function () {
    config(['photoparty.privacy_contact_email' => 'privacy@example.org']);

    $this->get('/privacidade')->assertSee('privacy@example.org');
});

it('shows the lawyer-review banner prominently only to the admin and a discreet note to everyone', function () {
    $this->get('/privacidade')->assertOk()
        ->assertDontSee('deve ser revisado por um advogado')
        ->assertSee('revisão jurídica');

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get('/privacidade')->assertOk()
        ->assertSee('Este texto é um modelo e deve ser revisado por um advogado antes do uso comercial.')
        ->assertSee('Campos de privacidade a preencher');

    config(['photoparty.privacy.legal_reviewed' => true]);
    auth()->logout();
    $this->get('/privacidade')->assertDontSee('revisão jurídica');
});

it('requires the image authorization declaration when creating an event and records declared_at', function () {
    $owner = ownerFor(Account::factory()->active()->create());

    $this->actingAs($owner)->post(route('events.store'), eventPayload(['image_authorization' => null]))
        ->assertSessionHasErrors('image_authorization');
    expect(Event::query()->count())->toBe(0);

    $this->actingAs($owner)->post(route('events.store'), eventPayload())->assertSessionHasNoErrors();
    expect(Event::query()->sole()->declared_at)->not->toBeNull();

    $this->actingAs($owner)->get(route('events.create'))->assertSee('Declaro que tenho autorização para coletar imagens dos participantes, inclusive de menores');
});
