<?php

use App\Enums\UserRole;
use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates a verified admin without account using a hidden password prompt', function () {
    $this->artisan('photoparty:create-admin', ['email' => 'Boss@Example.com'])
        ->expectsQuestion('Senha do administrador', 'uma-senha-longa-123')
        ->expectsQuestion('Repita a senha', 'uma-senha-longa-123')
        ->assertSuccessful();

    $user = User::where('email', 'boss@example.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::Admin)
        ->and($user->account_id)->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('uma-senha-longa-123', $user->password))->toBeTrue();
});

it('rejects mismatched or short passwords', function () {
    $this->artisan('photoparty:create-admin', ['email' => 'a@example.com'])
        ->expectsQuestion('Senha do administrador', 'uma-senha-longa-123')
        ->expectsQuestion('Repita a senha', 'outra-senha-longa-1')
        ->assertFailed();

    $this->artisan('photoparty:create-admin', ['email' => 'a@example.com'])
        ->expectsQuestion('Senha do administrador', 'curta')
        ->expectsQuestion('Repita a senha', 'curta')
        ->assertFailed();

    expect(User::where('email', 'a@example.com')->exists())->toBeFalse();
});

it('does not turn an organizer into an admin', function () {
    $account = Account::factory()->create();
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $owner->account_id = $account->id;
    $owner->save();

    $this->artisan('photoparty:create-admin', ['email' => 'owner@example.com'])
        ->expectsQuestion('Senha do administrador', 'uma-senha-longa-123')
        ->expectsQuestion('Repita a senha', 'uma-senha-longa-123')
        ->assertFailed();

    expect($owner->fresh()->role)->not->toBe(UserRole::Admin);
});
