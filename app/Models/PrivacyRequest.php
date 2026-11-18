<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Solicitação de titular (convidado) feita pelo formulário público. Lida só pelo admin.
 *
 * Não usa BelongsToAccount: o formulário é público (sem usuário) e a lista é da plataforma
 * (operador), que encaminha ao organizador (controlador) quando o evento é identificado.
 * Nenhum campo é mass-assignable a partir do request além dos do formulário.
 */
class PrivacyRequest extends Model
{
    use HasPublicUuid;

    public const TYPES = ['deletion', 'access', 'correction', 'other'];

    public const STATUSES = ['open', 'done'];

    protected $fillable = ['type', 'event_link', 'email', 'details', 'locale'];

    protected $hidden = ['id', 'account_id', 'event_id'];

    protected $attributes = [
        'status' => 'open',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withoutGlobalScopes();
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Prazo de resposta: LGPD (15 dias) ou GDPR (30 dias), contados do recebimento.
     */
    public function dueAt(string $law): CarbonInterface
    {
        return $this->created_at->copy()->addDays((int) config("photoparty.privacy.response_days.{$law}"));
    }

    public function isOverdue(string $law): bool
    {
        return $this->isOpen() && $this->dueAt($law)->isPast();
    }
}
