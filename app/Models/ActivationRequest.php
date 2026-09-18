<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivationRequest extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'fulfilled' => 'Fulfilled',
    ];

    protected $fillable = [
        'customer_id',
        'inventory_type_id',
        'inventory_item_id',
        'requested_by',
        'status',
        'notes',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function inventoryType(): BelongsTo
    {
        return $this->belongsTo(InventoryType::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ActivationRequestItem::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function itemLabel(): string
    {
        $lines = $this->items
            ->map(function (ActivationRequestItem $row) {
                $type = $row->inventoryType?->name;
                $label = $row->label();

                return $type ? "{$type}: {$label}" : $label;
            })
            ->filter()
            ->values();

        if ($lines->isNotEmpty()) {
            return $lines->implode(', ');
        }

        $item = $this->inventoryItem;
        if ($item) {
            $parts = array_filter([$item->name, $item->code]);

            return implode(' · ', $parts) ?: ($this->inventoryType?->name ?? 'Item');
        }

        return $this->inventoryType?->name ?? 'Item';
    }

    public function itemLines(): array
    {
        if ($this->items->isNotEmpty()) {
            return $this->items->map(fn (ActivationRequestItem $row) => [
                'type' => $row->inventoryType?->name ?? 'Item',
                'label' => $row->label(),
            ])->all();
        }

        if ($this->inventoryItem || $this->inventoryType) {
            return [[
                'type' => $this->inventoryType?->name ?? 'Item',
                'label' => $this->itemLabel(),
            ]];
        }

        return [];
    }
}
