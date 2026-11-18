<?php

use App\Models\Account;
use App\Models\PrivacyRequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\Support\GuestUpload;

/*
| Formulário público do titular (/privacidade/solicitacao) e tela do admin.
*/

function privacyPayload(array $overrides = []): array
{
    return array_merge([
        'type' => 'deletion',
        'event_link' => 'https://photo.uailabs.net/e/festa-abc123?t=xyz',
        'email' => 'Convidada@Example.com',
        'details' => 'Foto com vestido azul, por volta das 22h.',
        'confirm' => '1',
    ], $overrides);
}

it('shows the form in both languages with the response deadlines', function () {
    $this->get('/privacidade/solicitacao')->assertOk()->assertSee('Solicitação de privacidade')->assertSee('15 dias')->assertSee('30 dias');
    $this->get('/privacidade/solicitacao?lang=en')->assertOk()->assertSee('Privacy request')->assertSee('15 days');
});

it('creates an open request, links it to the event by slug and stores no IP', function () {
    $event = GuestUpload::liveEvent();

    $this->post(route('privacy.request.store'), privacyPayload(['event_link' => $event->guestUrl()]))
        ->assertRedirect(route('privacy.request.create'))
        ->assertSessionHas('privacy_request_sent', true);

    $request = PrivacyRequest::query()->sole();
    expect($request->status)->toBe('open')
        ->and($request->email)->toBe('convidada@example.com')
        ->and($request->event_id)->toBe($event->id)
        ->and($request->account_id)->toBe($event->account_id)
        ->and(array_key_exists('ip', $request->getAttributes()))->toBeFalse();
});

it('validates the form', function () {
    $this->post(route('privacy.request.store'), privacyPayload(['email' => 'nao-e-email', 'confirm' => null, 'type' => 'hack']))
        ->assertSessionHasErrors(['email', 'confirm', 'type']);

    expect(PrivacyRequest::query()->count())->toBe(0);
});

it('silently drops honeypot submissions', function () {
    $this->post(route('privacy.request.store'), privacyPayload(['website' => 'http://spam.example']))
        ->assertRedirect(route('privacy.request.create'));

    expect(PrivacyRequest::query()->count())->toBe(0);
});

it('rate limits submissions per IP', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->post(route('privacy.request.store'), privacyPayload())->assertRedirect();
    }

    $this->post(route('privacy.request.store'), privacyPayload())->assertStatus(429);
    expect(PrivacyRequest::query()->count())->toBe(5);
});

it('does not send e-mail when SMTP is not configured', function () {
    Mail::fake();
    config(['mail.default' => 'log', 'photoparty.privacy_contact_email' => 'dpo@example.com']);

    $this->post(route('privacy.request.store'), privacyPayload())->assertRedirect();

    Mail::assertNothingQueued();
    Mail::assertNothingSent();
});

it('lets only the admin see and complete requests, with LGPD and GDPR deadlines', function () {
    $request = new PrivacyRequest(privacyPayload());
    $request->save();

    $owner = ownerFor(Account::factory()->active()->create());
    $this->actingAs($owner)->get(route('admin.privacy.requests.index'))->assertNotFound();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get(route('admin.privacy.requests.index'))
        ->assertOk()
        ->assertSee('Convidada@Example.com')
        ->assertSee($request->dueAt('lgpd')->format('d/m/Y'))
        ->assertSee($request->dueAt('gdpr')->format('d/m/Y'));

    $this->actingAs($admin)->put(route('admin.privacy.requests.update', $request), ['status' => 'done', 'resolution_note' => 'Foto excluída.'])
        ->assertRedirect();

    expect($request->fresh()->status)->toBe('done')->and($request->fresh()->completed_at)->not->toBeNull();
});

it('lets the admin fill the privacy settings shown in the policy', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put(route('admin.privacy.settings.update'), [
        'privacy_company_name' => 'Empresa Exemplo Ltda',
        'privacy_contact_email' => 'privacidade@example.com',
        'privacy_dpo_name' => 'Maria DPO',
        'privacy_hosting_region' => 'Brasil',
    ])->assertRedirect();

    $this->get('/privacidade')->assertOk()
        ->assertSee('Empresa Exemplo Ltda')
        ->assertSee('privacidade@example.com')
        ->assertSee('Maria DPO');
});
