<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\QueuedResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * account_id e role não são mass-assignable: vêm sempre do servidor.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $attributes = [
        'role' => 'owner',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Redefinição de senha pela fila (mesmo tempo de resposta exista o e-mail ou não).
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        // Idioma de quem pediu (a fila roda depois, sem a sessão do usuário).
        $this->notify((new QueuedResetPassword($token))->locale(app()->getLocale()));
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Página inicial depois do login: admin vai para /admin (não tem conta de
     * organizador, então /dashboard responderia 404); os demais, para o painel.
     */
    public function homeRoute(): string
    {
        return $this->isAdmin() ? route('admin.dashboard', absolute: false) : route('dashboard', absolute: false);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::Owner;
    }

    public function isModerator(): bool
    {
        return $this->role === UserRole::Moderator;
    }

    public function belongsToAccountId(?int $accountId): bool
    {
        return $accountId !== null && $this->account_id !== null && (int) $this->account_id === (int) $accountId;
    }
}
