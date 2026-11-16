<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('renders the login page', function () {
    $this->get(route('login'))->assertOk();
});

it('logs in with valid credentials', function () {
    $user = User::factory()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'Password!123'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials', function () {
    $user = User::factory()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'errada'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('throttles login attempts per email and IP', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < config('photoparty.rate_limits.login_attempts'); $i++) {
        $this->post(route('login'), ['email' => $user->email, 'password' => 'errada']);
    }

    // Mesmo com a senha certa, fica bloqueado até o prazo passar.
    $this->post(route('login'), ['email' => $user->email, 'password' => 'Password!123'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));

    $this->assertGuest();
});

it('sends the password reset link without revealing whether the email exists', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status', __('passwords.sent'));
    Notification::assertSentTo($user, ResetPassword::class);

    $this->post(route('password.email'), ['email' => 'ninguem@example.com'])->assertSessionHas('status', __('passwords.sent'));
});

it('resets the password with a valid token', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->get(route('password.reset', ['token' => $notification->token, 'email' => $user->email]))->assertOk();

        $this->post(route('password.store'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'Nova-Senha-2026',
            'password_confirmation' => 'Nova-Senha-2026',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        return true;
    });

    expect(Hash::check('Nova-Senha-2026', $user->fresh()->password))->toBeTrue();
});
