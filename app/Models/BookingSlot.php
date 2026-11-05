<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingSlot extends Model
{
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_BOOKED = 'booked';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_AVAILABLE => 'Available',
        self::STATUS_BOOKED => 'Booked',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    protected $fillable = [
        'slot_date',
        'start_time',
        'end_time',
        'label',
        'status',
        'customer_name',
        'customer_id',
        'booked_by',
        'booked_at',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'slot_date' => 'date',
            'booked_at' => 'datetime',
        ];
    }

    public static function formatTimeLabel(string $start, string $end): string
    {
        try {
            $s = Carbon::createFromFormat('H:i', substr($start, 0, 5))->format('h:i A');
            $e = Carbon::createFromFormat('H:i', substr($end, 0, 5))->format('h:i A');

            return "{$s} to {$e}";
        } catch (\Throwable) {
            return "{$start} to {$end}";
        }
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function booker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by');
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }

    public function isBooked(): bool
    {
        return $this->status === self::STATUS_BOOKED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }
}

