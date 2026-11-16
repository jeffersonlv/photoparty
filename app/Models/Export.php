<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Exportação ZIP de um evento. (Módulo 7.)
 */
class Export extends Model
{
    use BelongsToAccount, HasPublicUuid;

    protected $fillable = [];

    protected $hidden = ['id', 'account_id', 'event_id', 'path'];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'bytes' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
