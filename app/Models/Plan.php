<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plano: fonte única de preço e limites (a página inicial lê daqui).
 */
class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'price',
        'currency',
        'max_events_per_month',
        'max_photos_per_event',
        'retention_days',
        'allows_custom_frames',
        'allows_geofence',
        'white_label',
        'is_public',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'max_events_per_month' => 'integer',
            'max_photos_per_event' => 'integer',
            'retention_days' => 'integer',
            'allows_custom_frames' => 'boolean',
            'allows_geofence' => 'boolean',
            'white_label' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }
}
