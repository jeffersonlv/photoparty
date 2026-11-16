<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeItem extends Model
{
    protected $fillable = ['data'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'sort_order' => 'integer',
            'data' => 'array',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(HomeSection::class, 'section_id');
    }
}
