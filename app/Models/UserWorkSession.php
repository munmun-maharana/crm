<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWorkSession extends Model
{
    protected $fillable = [
        'user_id',
        'user_role',
        'started_at',
        'ended_at',
        'status',
        'break_started_at',
        'total_break_seconds',
        'total_waste_seconds',
        'total_seconds',
        'desk_seconds',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'break_started_at' => 'datetime',
            'total_break_seconds' => 'integer',
            'total_waste_seconds' => 'integer',
            'total_seconds' => 'integer',
            'desk_seconds' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function computedTotalSeconds(): int
    {
        if (! $this->started_at) {
            return (int) $this->total_seconds;
        }
        $end = $this->ended_at ?? now();
        $diff = max(0, $end->getTimestamp() - $this->started_at->getTimestamp());

        return max((int) $this->total_seconds, $diff);
    }

    public function computedBreakSeconds(): int
    {
        $base = (int) $this->total_break_seconds;
        if ($this->status === 'on_break' && $this->break_started_at) {
            $base += max(0, now()->getTimestamp() - $this->break_started_at->getTimestamp());
        }

        return $base;
    }

    public function computedDeskSeconds(): int
    {
        return max(0, $this->computedTotalSeconds() - $this->computedBreakSeconds() - (int) $this->total_waste_seconds);
    }
}
