<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class InventoryType extends Model
{
    protected $fillable = [
        'name',
        'key',
        'sort_order',
        'is_active',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('name');
    }

    public static function makeKey(string $name): string
    {
        $base = Str::snake(Str::slug($name, '_'));
        $base = preg_replace('/[^a-z0-9_]/', '', $base) ?: 'item';
        $key = $base;
        $i = 2;
        while (static::where('key', $key)->exists()) {
            $key = $base.'_'.$i;
            $i++;
        }

        return $key;
    }
}
