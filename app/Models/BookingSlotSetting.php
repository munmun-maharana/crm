<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingSlotSetting extends Model
{
    protected $fillable = [
        'status',
        'title',
        'body',
    ];

    public static function current(): self
    {
        return static::query()->first() ?? static::create([
            'status' => 'confirmed',
            'title' => 'Booking Slot Confirmed',
            'body' => 'Booking slot confirmed for {date} at {time}.',
        ]);
    }
}

