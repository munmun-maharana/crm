<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CrmField extends Model
{
    protected $fillable = [
        'pipeline',
        'key',
        'maps_from',
        'label',
        'type',
        'options',
        'required',
        'active',
        'in_form',
        'in_table',
        'in_excel',
        'is_system',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'required' => 'boolean',
            'active' => 'boolean',
            'in_form' => 'boolean',
            'in_table' => 'boolean',
            'in_excel' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public const CORE_KEYS = [
        'name', 'email', 'phone', 'company', 'city', 'source', 'status', 'notes', 'assigned_to',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('id');
    }

    public function scopeInPipeline(Builder $q, string $pipeline): Builder
    {
        return $q->where('pipeline', $pipeline === 'operations' ? 'operations' : 'crm');
    }

    public function isCore(): bool
    {
        return in_array($this->key, self::CORE_KEYS, true);
    }

    public static function makeKey(string $label, string $pipeline = 'crm'): string
    {
        $pipeline = $pipeline === 'operations' ? 'operations' : 'crm';
        $base = Str::snake(Str::slug($label, '_'));
        $base = preg_replace('/[^a-z0-9_]/', '', $base) ?: 'field';
        $key = $base;
        $i = 2;
        while (static::where('pipeline', $pipeline)->where('key', $key)->exists()) {
            $key = $base.'_'.$i;
            $i++;
        }

        return $key;
    }
}
