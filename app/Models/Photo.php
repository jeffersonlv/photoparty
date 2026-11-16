<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
