<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    protected $fillable = [
        'name',
        'code',
        'price',
        'category',
        'unit',
        'description',
        'status',
        'is_active',
        'sort_order',
        'meta',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'meta' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_product')
            ->withPivot(['quantity', 'price', 'notes'])
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereRaw('LOWER(status) != ?', ['inactive']);
            });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')
            ->orderBy('name');
    }

    public function fieldValue(string $key): mixed
    {
        if (in_array($key, CrmField::PRODUCT_CORE_KEYS, true)) {
            return $this->{$key};
        }

        return data_get($this->meta, $key);
    }

    public function formattedPrice(): string
    {
        return '₹' . number_format((float) ($this->price ?? 0), 2);
    }

    public function getShortNameAttribute(): string
    {
        $code = trim((string) ($this->code ?? ''));
        if ($code !== '') {
            return $code;
        }
        return \Illuminate\Support\Str::limit(trim((string) $this->name), 10, '');
    }
}
