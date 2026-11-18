<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class MenuSetting extends Model
{
    protected $fillable = [
        'key',
        'label',
        'default_label',
        'icon',
        'route',
        'sort_order',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Return associative array of [key => label]
     */
    public static function allMap(): array
    {
        try {
            return static::query()
                ->pluck('label', 'key')
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Get customized label for a given menu key with default fallback
     */
    public static function getLabel(string $key, ?string $fallback = null): string
    {
        $map = static::allMap();
        if (isset($map[$key]) && trim((string) $map[$key]) !== '') {
            return $map[$key];
        }

        return $fallback ?? ucfirst(str_replace('_', ' ', $key));
    }
}
