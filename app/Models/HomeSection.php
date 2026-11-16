<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Seção da página inicial. enabled/sort_order/updated_by só mudam por ação do admin.
 */
class HomeSection extends Model
{
    protected $fillable = ['content'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'sort_order' => 'integer',
            'content' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    public function items(): HasMany
    {
        return $this->hasMany(HomeItem::class, 'section_id')->orderBy('sort_order')->orderBy('id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Schema (campos editáveis) desta seção em config/home_sections.php.
     *
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return config('home_sections.sections.'.$this->key, []);
    }
}
