<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Organizador (tenant).
 *
 * status e plan_id não são mass-assignable: mudam só por ação do sistema/admin.
 */
class Account extends Model
{
    use HasFactory, HasPublicUuid;

    protected $fillable = [
        'name',
        'branding',
    ];

    protected $attributes = [
        'status' => 'trial',
    ];

    protected function casts(): array
    {
        return [
            'status' => AccountStatus::class,
            'trial_used_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'branding' => 'array',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function events(): HasMany
    {
        // Relação explícita por account_id; o global scope continua valendo.
        return $this->hasMany(Event::class);
    }

    public function isActive(): bool
    {
        return $this->status === AccountStatus::Active;
    }

    public function isOnTrial(): bool
    {
        return $this->status === AccountStatus::Trial;
    }

    /**
     * Regra única de mídia: só conta com plano pago (active) baixa originais e ZIP e vê
     * miniaturas normais. Teste, teste encerrado, past_due e cancelled: só prévia mínima.
     */
    public function canDownloadPhotos(): bool
    {
        return $this->isActive();
    }

    /**
     * Teste ainda não usado: conta em trial cujo evento de teste não foi publicado.
     */
    public function hasTrialAvailable(): bool
    {
        return $this->isOnTrial() && $this->trial_used_at === null;
    }

    /**
     * Janela de 3 h aberta: trial já iniciado (evento de teste publicado) e ainda dentro do prazo.
     */
    public function trialWindowOpen(): bool
    {
        return $this->isOnTrial()
            && $this->trial_used_at !== null
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    /**
     * Pode criar e publicar eventos? (SPEC 7: só trial com teste disponível ou active.)
     */
    public function canManageEvents(): bool
    {
        return $this->isActive() || $this->hasTrialAvailable();
    }

    /**
     * Inicia o período de teste da conta (3 h a partir da publicação do evento de teste).
     */
    public function startTrialWindow(): void
    {
        $now = now();

        $this->forceFill([
            'trial_used_at' => $now,
            'trial_ends_at' => $now->copy()->addHours((int) config('photoparty.trial.account_window_hours')),
        ])->save();
    }

    public function retentionDays(): int
    {
        if ($this->isActive()) {
            return $this->plan?->retention_days ?? (int) config('photoparty.default_retention_days');
        }

        return (int) config('photoparty.trial.retention_days');
    }

    public function storagePath(): string
    {
        return 'tenants/'.$this->uuid;
    }
}
