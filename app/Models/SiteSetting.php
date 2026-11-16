<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configurações do site (SEO, contato): chave/valor texto.
 */
class SiteSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    /**
     * @return array<string, string|null>
     */
    public static function allKeyed(): array
    {
        return static::query()->pluck('value', 'key')->all();
    }
}
