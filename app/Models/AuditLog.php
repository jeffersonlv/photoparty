<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Trilha de auditoria (OWASP A09). Nunca grave tokens ou senhas em meta.
 *
 * Não usa BelongsToAccount: é escrita pelo sistema e lida pelo admin (módulo 9).
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public static function record(string $action, ?Model $subject = null, array $meta = [], ?User $user = null): self
    {
        $user ??= Auth::user();
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return static::query()->create([
            'user_id' => $user?->getKey(),
            'account_id' => $subject instanceof Account
                ? $subject->getKey()
                : ($subject?->getAttribute('account_id') ?? $user?->account_id),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'meta' => $meta ?: null,
            'ip' => $request?->ip(),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
