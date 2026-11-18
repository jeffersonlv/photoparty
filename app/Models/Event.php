<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Models\Concerns\BelongsToAccount;
use App\Models\Concerns\HasPublicUuid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Evento de um organizador.
 *
 * Tokens (upload e telão): gerados com Str::random(64). Guardamos
 *  - o hash SHA-256 (coluna *_token_hash) para localizar o evento pelo token sem
 *    guardar o valor em texto puro; e
 *  - uma cópia criptografada com a APP_KEY (coluna *_token_encrypted, cast
 *    "encrypted") para que o organizador possa baixar/reimprimir o QR a qualquer
 *    momento. Ver README, "Decisões".
 */
class Event extends Model
{
    use BelongsToAccount, HasFactory, HasPublicUuid;

    protected $fillable = [
        'name',
        'starts_at',
        'ends_at',
        'moderation',
        'welcome_message',
        'ask_guest_name',
        'max_photos_per_guest',
        'enabled_filters',
        'screen_settings',
        'language',
    ];

    protected $hidden = [
        'id',
        'account_id',
        'upload_token_hash',
        'upload_token_encrypted',
        'screen_token_hash',
        'screen_token_encrypted',
    ];

    protected $attributes = [
        'status' => 'draft',
        'moderation' => 'pre',
        'language' => 'pt_BR',
        'geofence_enabled' => false,
        'geofence_radius_m' => 100,
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
            'expires_at' => 'datetime',
            'declared_at' => 'datetime',
            'purged_at' => 'datetime',
            'status' => EventStatus::class,
            'is_trial' => 'boolean',
            'is_demo' => 'boolean',
            'geofence_enabled' => 'boolean',
            'geofence_lat' => 'float',
            'geofence_lng' => 'float',
            'geofence_radius_m' => 'integer',
            'ask_guest_name' => 'boolean',
            'max_photos_per_guest' => 'integer',
            'enabled_filters' => 'array',
            'screen_settings' => 'array',
            'upload_token_encrypted' => 'encrypted',
            'screen_token_encrypted' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            if (empty($event->slug)) {
                $event->slug = static::uniqueSlug($event->name ?? 'evento');
            }

            if ($event->upload_token_hash === null) {
                $event->issueUploadToken();
            }

            if ($event->screen_token_hash === null) {
                $event->issueScreenToken();
            }
        });

        // Por padrão, todo evento novo nasce com os modelos prontos do sistema ativos.
        static::created(function (Event $event) {
            $event->attachDefaultFrames();
        });
    }

    /**
     * Ativa no evento as molduras-modelo do sistema (account_id nulo, type template),
     * na ordem de FrameSeeder::TEMPLATE_KEYS. Sem modelos cadastrados, não faz nada.
     */
    public function attachDefaultFrames(): void
    {
        $order = array_flip(\Database\Seeders\FrameSeeder::TEMPLATE_KEYS);

        $frames = Frame::withoutGlobalScopes()
            ->whereNull('account_id')
            ->where('type', 'template')
            ->where('is_active', true)
            ->get(['id', 'template_key'])
            ->sortBy(fn (Frame $frame) => $order[$frame->template_key] ?? PHP_INT_MAX)
            ->values();

        if ($frames->isEmpty()) {
            return;
        }

        $this->frames()->syncWithoutDetaching(
            $frames->mapWithKeys(fn (Frame $frame, int $i) => [$frame->id => ['sort_order' => $i]])->all(),
        );
    }

    /**
     * Geofence em vigor: ligada no evento, com local definido e a conta ainda com o recurso.
     * Conta que perdeu o plano => false (o evento volta ao comportamento normal, sem apagar a configuração).
     */
    public function geofenceActive(): bool
    {
        if (! $this->geofence_enabled || $this->geofence_lat === null || $this->geofence_lng === null) {
            return false;
        }

        return (bool) Account::query()->find($this->account_id)?->canUseGeofence();
    }

    public function hasGeofenceLocation(): bool
    {
        return $this->geofence_lat !== null && $this->geofence_lng !== null;
    }

    /** "lat, lng" com 6 casas (local do evento definido pelo organizador). */
    public function geofenceCoordinates(): ?string
    {
        return $this->hasGeofenceLocation()
            ? sprintf('%.6f, %.6f', $this->geofence_lat, $this->geofence_lng)
            : null;
    }

    /** Link simples "ver no mapa" (sem embed nem chamada do servidor). */
    public function geofenceMapUrl(): ?string
    {
        return $this->hasGeofenceLocation()
            ? 'https://www.google.com/maps?q='.sprintf('%.6f,%.6f', $this->geofence_lat, $this->geofence_lng)
            : null;
    }

    /**
     * Molduras ativas do evento, ignorando o global scope da conta (rotas por token).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Frame>
     */
    public function activeFrames(): \Illuminate\Database\Eloquent\Collection
    {
        // Moldura desativada pelo admin some para o convidado, mesmo se já ligada ao evento.
        $frames = $this->frames()->withoutGlobalScopes()->where('frames.is_active', true)->get();

        // Moldura personalizada (módulo 8): só da própria conta do evento e só enquanto a
        // conta puder usá-las (plano pago com allows_custom_frames). Rebaixou o plano => some.
        $customAllowed = null;

        return $frames->filter(function (Frame $frame) use (&$customAllowed) {
            if ($frame->type !== Frame::TYPE_CUSTOM) {
                return true;
            }

            if ((int) $frame->account_id !== (int) $this->account_id) {
                return false;
            }

            $customAllowed ??= (bool) Account::query()->find($this->account_id)?->canUseCustomFrames();

            return $customAllowed;
        })->values();
    }


    // ---------------------------------------------------------------- relações

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function exports(): HasMany
    {
        return $this->hasMany(Export::class);
    }

    public function frames(): BelongsToMany
    {
        return $this->belongsToMany(Frame::class)->withPivot('sort_order')->orderByPivot('sort_order');
    }

    // ----------------------------------------------------------------- tokens

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Gera novo token de upload (QR do convidado). Retorna o valor em texto puro.
     * Não salva: chame save() depois (ou deixe o creating salvar).
     */
    public function issueUploadToken(): string
    {
        $token = Str::random(64);
        $this->upload_token_hash = static::hashToken($token);
        $this->upload_token_encrypted = $token;

        return $token;
    }

    /**
     * Gera novo token do telão. O token anterior deixa de funcionar.
     */
    public function issueScreenToken(): string
    {
        $token = Str::random(64);
        $this->screen_token_hash = static::hashToken($token);
        $this->screen_token_encrypted = $token;

        return $token;
    }

    public function uploadToken(): ?string
    {
        return $this->upload_token_encrypted;
    }

    public function screenToken(): ?string
    {
        return $this->screen_token_encrypted;
    }

    public function scopeWhereUploadToken(Builder $query, string $token): Builder
    {
        return $query->where('upload_token_hash', static::hashToken($token));
    }

    public function scopeWhereScreenToken(Builder $query, string $token): Builder
    {
        return $query->where('screen_token_hash', static::hashToken($token));
    }

    public function guestUrl(): string
    {
        return config('photoparty.public_url').'/e/'.rawurlencode($this->slug).'?t='.rawurlencode((string) $this->uploadToken());
    }

    public function screenUrl(): string
    {
        return config('photoparty.public_url').'/screen/'.rawurlencode((string) $this->screenToken());
    }

    // ----------------------------------------------------------------- estado

    /**
     * Estado calculado pelos horários + ações do organizador (SPEC 6).
     */
    public function computeStatus(?CarbonInterface $now = null): EventStatus
    {
        $now ??= now();

        if ($this->published_at === null) {
            return EventStatus::Draft;
        }

        if ($this->expires_at !== null && $now->greaterThanOrEqualTo($this->expires_at)) {
            return EventStatus::Expired;
        }

        if ($this->closed_at !== null || $now->greaterThanOrEqualTo($this->ends_at)) {
            return EventStatus::Ended;
        }

        if ($now->greaterThanOrEqualTo($this->starts_at)) {
            return EventStatus::Live;
        }

        return EventStatus::Scheduled;
    }

    /**
     * Recalcula status (e expires_at) e informa se mudou. Não salva.
     */
    public function refreshStatus(?CarbonInterface $now = null): bool
    {
        $this->status = $this->computeStatus($now);

        return $this->isDirty('status');
    }

    public function currentStatus(): EventStatus
    {
        return $this->computeStatus();
    }

    public function isDraft(): bool
    {
        return $this->published_at === null;
    }

    public function isLive(): bool
    {
        return $this->currentStatus() === EventStatus::Live;
    }

    /**
     * Data de expiração: fim efetivo + retenção (plano ou teste).
     */
    public function calculateExpiresAt(int $retentionDays): CarbonInterface
    {
        $end = $this->closed_at !== null && $this->closed_at->lessThan($this->ends_at)
            ? $this->closed_at
            : $this->ends_at;

        return $end->copy()->addDays($retentionDays);
    }

    /**
     * Limite de fotos do evento (o upload, módulo 4, deve consultar este método).
     */
    public function photoLimit(): int
    {
        if ($this->is_trial) {
            return (int) config('photoparty.trial.max_photos');
        }

        $account = $this->account()->withoutGlobalScopes()->first();

        return $account?->plan?->max_photos_per_event ?? (int) config('photoparty.default_max_photos_per_event');
    }

    public function hasReachedPhotoLimit(): bool
    {
        return $this->photos()->count() >= $this->photoLimit();
    }

    // ------------------------------------------------------------------ slug

    public static function uniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name), 40, '') ?: 'evento';

        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while (static::withoutGlobalScopes()->where('slug', $slug)->exists());

        return $slug;
    }
}
