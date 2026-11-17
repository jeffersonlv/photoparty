<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/**
 * Foto enviada por convidado. (Módulos 4 e 6 implementam upload e moderação.)
 * account_id e event_id nunca vêm do request: derivam do evento resolvido pelo token.
 */
class Photo extends Model
{
    use BelongsToAccount, HasPublicUuid;

    protected $fillable = [
        'filter',
        'orientation',
        'guest_name',
        'guest_texts',
    ];

    /**
     * Microssegundos: o cursor do telão usa (approved_at, id) como ordem estável.
     */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $hidden = ['id', 'account_id', 'event_id', 'device_hash', 'original_path', 'thumb_path'];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'guest_texts' => 'array',
            'bytes' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function frame(): BelongsTo
    {
        return $this->belongsTo(Frame::class);
    }

    public const VARIANTS = ['thumb', 'original', 'preview'];

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function pathFor(string $variant): ?string
    {
        return match ($variant) {
            'original' => $this->original_path,
            'thumb' => $this->thumb_path,
            'preview' => $this->previewPath(),
            default => null,
        };
    }

    /**
     * Caminho da prévia de baixíssima resolução: .../thumb/{uuid}.jpg => .../preview-v2/{uuid}.jpg.
     */
    public function previewPath(): ?string
    {
        if ($this->thumb_path === null || ! str_contains($this->thumb_path, '/thumb/')) {
            return null;
        }

        return substr_replace($this->thumb_path, '/preview-v2/', strrpos($this->thumb_path, '/thumb/'), strlen('/thumb/'));
    }

    /**
     * URL para o painel (usuário logado; PhotoPolicy decide). Rota media.show.
     */
    public function mediaUrl(string $variant = 'thumb'): string
    {
        return route('media.show', ['photo' => $this->uuid, 'variant' => $variant], false);
    }

    /**
     * URL assinada e temporária (telão, sem login). Relativa: a assinatura não depende
     * do host. Só serve foto aprovada. Rota media.signed.
     */
    public function signedMediaUrl(string $variant = 'thumb', int $minutes = 10): string
    {
        return URL::temporarySignedRoute(
            'media.signed',
            now()->addMinutes($minutes),
            ['photo' => $this->uuid, 'variant' => $variant],
            false,
        );
    }
}
