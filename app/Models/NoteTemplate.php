<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoteTemplate extends Model
{
    protected $fillable = [
        'title',
        'body',
        'status',
        'sort_order',
        'is_active',
        'show_in_crm',
        'show_in_call_crm',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'show_in_crm' => 'boolean',
            'show_in_call_crm' => 'boolean',
        ];
    }

    public function scopeForPipeline($query, ?string $pipeline)
    {
        if ($pipeline === 'call_crm') {
            return $query->where('show_in_call_crm', true);
        }
        if ($pipeline === 'crm') {
            return $query->where('show_in_crm', true);
        }

        return $query;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }
}
