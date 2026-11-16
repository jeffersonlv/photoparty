<?php

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\Account;
use App\Models\User;
use App\Rules\NotDisposableEmail;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Maria Fotógrafa',
        'email' => 'maria@example.com',
        'password' => 'Senha-Forte-2026',
        'password_confirmation' => 'Senha-Forte-2026',
        'terms' => '1',
        'website' => '',
    ], $overrides);
}

beforeEach(function () {
    Notification::fake();
});

it('renders the registration page', function () {
    $this->get(route('register'))->assertOk()->assertSee('name="website"', false);
});

it('registers an owner with a trial account and sends the verification email', function () {
    config(['photoparty.require_email_verification' => true]);

    $this->post(route('register'), registrationPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('verification.notice'));

    $user = User::where('email', 'maria@example.com')->sole();

    expect($user->role)->toBe(UserRole::Owner)
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->account->status)->toBe(AccountStatus::Trial)
        ->and($user->account->hasTrialAvailable())->toBeTrue();

    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('ignores role and account_id sent in the request', function () {
    $other = Account::factory()->create();

    $this->post(route('register'), registrationPayload([
        'role' => 'admin',
        'account_id' => $other->id,
    ]))->assertSessionHasNoErrors();

    $user = User::where('email', 'maria@example.com')->sole();

    expect($user->role)->toBe(UserRole::Owner)
        ->and($user->account_id)->not->toBe($other->id);
});

it('rejects the registration when the honeypot is filled', function () {
    $this->post(route('register'), registrationPayload(['website' => 'https://spam.example']))
        ->assertSessionHasErrors('website');

    expect(User::count())->toBe(0)
        ->and(Account::count())->toBe(0);
    $this->assertGuest();
});

it('blocks temporary email domains, including subdomains', function (string $email) {
    $this->post(route('register'), registrationPayload(['email' => $email]))
        ->assertSessionHasErrors('email');

    expect(User::count())->toBe(0);
})->with([
    'mailinator' => 'robo@mailinator.com',
    'uppercase' => 'Robo@YOPMAIL.com',
    'subdomain' => 'robo@inbox.guerrillamail.com',
]);

it('does not flag regular domains as temporary', function () {
    expect(NotDisposableEmail::isDisposable('ana@gmail.com'))->toBeFalse()
        ->and(NotDisposableEmail::isDisposable('ana@notmailinator.com'))->toBeFalse()
        ->and(NotDisposableEmail::isDisposable('ana@mailinator.com'))->toBeTrue();
});

it('requires a strong password', function (string $password) {
    $this->post(route('register'), registrationPayload([
        'password' => $password,
        'password_confirmation' => $password,
    ]))->assertSessionHasErrors('password');
})->with([
    'too short' => 'Ab1!',
    'no symbols' => 'SenhaForte2026',
    'no uppercase' => 'senha-forte-2026',
    'no numbers' => 'Senha-Forte-Sem',
]);

it('requires accepting the terms', function () {
    $this->post(route('register'), registrationPayload(['terms' => null]))
        ->assertSessionHasErrors('terms');
});

it('rejects a duplicated email', function () {
    User::factory()->create(['email' => 'maria@example.com']);

    $this->post(route('register'), registrationPayload(['email' => 'MARIA@example.com']))
        ->assertSessionHasErrors('email');
});

it('rate limits registrations per IP', function () {
    $limit = config('photoparty.rate_limits.register_per_hour');

    // Tentativas inválidas também contam (o throttle roda antes da validação).
    for ($i = 0; $i < $limit; $i++) {
        $this->post(route('register'), registrationPayload(['email' => "pessoa{$i}@example.com", 'terms' => null]))
            ->assertSessionHasErrors('terms');
    }

    $this->post(route('register'), registrationPayload(['email' => 'mais-uma@example.com']))
        ->assertStatus(429);

    expect(User::where('email', 'mais-uma@example.com')->exists())->toBeFalse();
});

it('keeps unverified users out of the dashboard', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    $this->actingAs($user)->get(route('verification.notice'))->assertOk();
});

it('verifies the email through the signed link', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    $this->actingAs($user)->get($url)->assertRedirect(route('dashboard'));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects a tampered verification link', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    $this->actingAs($user)->get($url.'x')->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('resends the verification email', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->post(route('verification.send'))->assertSessionHas('status');

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('skips email confirmation while it is disabled', function () {
    config(['photoparty.require_email_verification' => false]);

    $this->post(route('register'), [
        'name' => 'Sem Confirmação',
        'email' => 'sem-confirmacao@example.com',
        'password' => 'Str0ng!Passw0rd',
        'password_confirmation' => 'Str0ng!Passw0rd',
        'terms' => '1',
    ])->assertRedirect(route('dashboard'));

    $user = \App\Models\User::where('email', 'sem-confirmacao@example.com')->firstOrFail();

    expect($user->hasVerifiedEmail())->toBeTrue();
    Notification::assertNothingSentTo($user);
    $this->get(route('dashboard'))->assertOk();
});
