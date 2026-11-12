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
        'in_update',
        'auto_select',
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
            'in_update' => 'boolean',
            'auto_select' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public const CORE_KEYS = [
        'name', 'email', 'phone', 'company', 'city', 'source', 'status', 'notes', 'assigned_to',
    ];

    public const USER_CORE_KEYS = [
        'name', 'username', 'email', 'password', 'role',
    ];

    public const PINCODE_CORE_KEYS = [
        'pincode', 'area', 'city', 'state', 'zone', 'status', 'assigned_to', 'notes',
    ];

    public const TYPES = [
        'text' => 'Text',
        'email' => 'Email',
        'phone' => 'Phone',
        'number' => 'Number',
        'date' => 'Date',
        'time' => 'Time',
        'textarea' => 'Textarea',
        'select' => 'Select (dropdown)',
    ];

    public static function typeRule(): string
    {
        return 'in:'.implode(',', array_keys(self::TYPES));
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('id');
    }

    public static function normalizePipeline(string $pipeline): string
    {
        return match ($pipeline) {
            'operations' => 'operations',
            'users' => 'users',
            'pincodes' => 'pincodes',
            'call_crm' => 'call_crm',
            default => 'crm',
        };
    }

    public function scopeInPipeline(Builder $q, string $pipeline): Builder
    {
        return $q->where('pipeline', self::normalizePipeline($pipeline));
    }

    public function isCore(): bool
    {
        if ($this->pipeline === 'users') {
            return in_array($this->key, self::USER_CORE_KEYS, true);
        }

        if ($this->pipeline === 'pincodes') {
            return in_array($this->key, self::PINCODE_CORE_KEYS, true);
        }

        return in_array($this->key, self::CORE_KEYS, true);
    }

    public static function makeKey(string $label, string $pipeline = 'crm'): string
    {
        $pipeline = self::normalizePipeline($pipeline);
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
