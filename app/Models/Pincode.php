<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pincode extends Model
{
    protected $fillable = [
        'pincode',
        'area',
        'city',
        'state',
        'zone',
        'status',
        'assigned_to',
        'notes',
        'meta',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fieldValue(string $key): mixed
    {
        if (in_array($key, CrmField::PINCODE_CORE_KEYS, true)) {
            return $this->{$key};
        }

        return data_get($this->meta, $key);
    }

    public static function statusOptions(): array
    {
        $field = CrmField::inPipeline('pincodes')->where('key', 'status')->first();
        $fromField = collect($field?->options ?? [])
            ->map(fn ($o) => trim((string) $o))
            ->filter()
            ->values()
            ->all();

        if ($fromField !== []) {
            return $fromField;
        }

        return ['Serviceable', 'Non-Serviceable', 'Hold', 'Coming Soon'];
    }
}

