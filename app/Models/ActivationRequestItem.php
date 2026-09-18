<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivationRequestItem extends Model
{
    protected $fillable = [
        'activation_request_id',
        'inventory_item_id',
        'inventory_type_id',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ActivationRequest::class, 'activation_request_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function inventoryType(): BelongsTo
    {
        return $this->belongsTo(InventoryType::class);
    }

    public function label(): string
    {
        $item = $this->inventoryItem;
        if (! $item) {
            return $this->inventoryType?->name ?? 'Item';
        }

        $parts = array_filter([$item->name, $item->code]);

        return implode(' · ', $parts) ?: ($this->inventoryType?->name ?? 'Item');
    }
}
