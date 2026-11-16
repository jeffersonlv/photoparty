<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Chave pública UUID v4 aleatória (coluna "uuid"), usada nas URLs.
 *
 * Não usamos HasUuids do Laravel porque no Laravel 11 ele gera UUIDs
 * ordenados por tempo; a SPEC pede v4 aleatório (Str::uuid()).
 * O id sequencial continua sendo a chave primária interna e nunca aparece em URL.
 */
trait HasPublicUuid
{
    public static function bootHasPublicUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
