<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class InventoryItem extends Model
{
    public const STATUSES = [
        'in_stock' => 'In stock',
        'assigned' => 'Assigned',
        'damaged' => 'Damaged',
        'repair' => 'Repair',
    ];

    protected $fillable = [
        'inventory_type_id',
        'name',
        'code',
        'quantity',
        'status',
        'held_by_user_id',
        'assigned_to_name',
        'notes',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'quantity' => 'integer',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(InventoryType::class, 'inventory_type_id');
    }

    public function heldBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'held_by_user_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(InventoryItemHistory::class)->latest();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function recordHistory(string $action, string $title, ?string $body = null, ?array $changes = null): void
    {
        $this->histories()->create([
            'user_id' => Auth::id(),
            'action' => $action,
            'title' => $title,
            'body' => $body,
            'changes' => $changes,
        ]);
    }

    public function displayValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($field) {
            'inventory_type_id' => InventoryType::find($value)?->name ?? (string) $value,
            'status' => self::STATUSES[$value] ?? (string) $value,
            'held_by_user_id' => User::find($value)?->name ?? (string) $value,
            default => (string) $value,
        };
    }

    public function diffChanges(array $before, array $after): array
    {
        $fields = [
            'inventory_type_id' => 'Type',
            'name' => 'Name',
            'code' => 'Code',
            'quantity' => 'Quantity',
            'status' => 'Status',
            'held_by_user_id' => 'Stock with',
            'assigned_to_name' => 'Assigned to',
            'notes' => 'Notes',
        ];

        $changes = [];
        foreach ($fields as $key => $label) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;
            if ((string) ($old ?? '') === (string) ($new ?? '')) {
                continue;
            }
            $changes[] = [
                'field' => $key,
                'label' => $label,
                'from' => $this->displayValue($key, $old),
                'to' => $this->displayValue($key, $new),
            ];
        }

        return $changes;
    }
}
