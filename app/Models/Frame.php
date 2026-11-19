<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Moldura. account_id nulo = modelo do sistema (type template), visível para todas as contas.
 * type custom = moldura enviada pelo organizador (módulo 8): arquivos em
 * storage/app/private/tenants/{account_uuid}/frames/{frame_uuid}/, nunca em public/.
 */
class Frame extends Model
{
    use BelongsToAccount, HasPublicUuid;

    public const TYPE_TEMPLATE = 'template';

    public const TYPE_CUSTOM = 'custom';

    public const ORIENTATIONS = ['portrait', 'landscape'];

    /** Máximo de molduras personalizadas por conta. */
    public const MAX_CUSTOM_PER_ACCOUNT = 20;

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

    public function isCustom(): bool
    {
        return $this->type === self::TYPE_CUSTOM;
    }

    /**
     * Orientações que a moldura oferece (a custom pode ter só uma).
     *
     * @return list<string>
     */
    public function orientations(): array
    {
        return array_values(array_filter(
            self::ORIENTATIONS,
            fn (string $o) => isset($this->config['orientations'][$o]),
        ));
    }

    /**
     * Pasta da moldura custom no disco local (dentro da pasta do tenant).
     */
    public function storageDirectory(): ?string
    {
        if (! $this->isCustom() || $this->account_id === null) {
            return null;
        }

        $account = Account::query()->find($this->account_id);

        return $account ? $account->storagePath().'/frames/'.$this->uuid : null;
    }

    /**
     * Caminho do PNG de uma orientação (custom), ou null se a moldura não a tiver.
     */
    public function imagePath(string $orientation): ?string
    {
        if (! in_array($orientation, self::ORIENTATIONS, true)) {
            return null;
        }

        $path = $orientation === 'portrait' ? $this->portrait_path : $this->landscape_path;

        return $this->isCustom() && is_string($path) && $path !== '' ? $path : null;
    }

    /** Nome no idioma da página: modelos do sistema trazem pt/en no config; os do organizador usam o nome dado por ele. */
    public function displayName(): string
    {
        $lang = str_starts_with(app()->getLocale(), 'en') ? 'en' : 'pt';

        return (string) ($this->config['name'][$lang] ?? $this->name);
    }
}
