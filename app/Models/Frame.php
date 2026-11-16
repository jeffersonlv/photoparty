<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Moldura. account_id nulo = modelo do sistema, visível para todas as contas.
 * (Módulo 8 implementa a biblioteca e o upload.)
 */
class Frame extends Model
{
    use BelongsToAccount, HasPublicUuid;

    protected static bool $includeSystemRecords = true;

    protected $fillable = [
        'name',
        'type',
        'template_key',
        'config',
    ];

    protected $hidden = ['id', 'account_id', 'portrait_path', 'landscape_path'];

    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class)->withPivot('sort_order');
    }

    /**
     * Chave pública usada pela página do convidado (frame_key do upload):
     * template_key nos modelos prontos, uuid nas molduras do organizador.
     */
    public function publicKey(): string
    {
        return $this->template_key ?? $this->uuid;
    }

    /**
     * Textos que o convidado pode preencher numa orientação: [id => maxChars].
     *
     * @return array<string, int>
     */
    public function guestTextLimits(string $orientation): array
    {
        $limits = [];

        foreach ($this->config['orientations'][$orientation]['texts'] ?? [] as $text) {
            if (($text['filledBy'] ?? null) === 'guest' && is_string($text['id'] ?? null)) {
                $limits[$text['id']] = min((int) ($text['maxChars'] ?? 40), 40);
            }
        }

        return $limits;
    }

    public function isSystem(): bool
    {
        return $this->account_id === null;
    }
}
