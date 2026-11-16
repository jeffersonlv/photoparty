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

    public function isSystem(): bool
    {
        return $this->account_id === null;
    }
}
