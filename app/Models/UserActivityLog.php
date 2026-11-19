<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'user_role',
        'session_id',
        'event_type',
        'page_path',
        'page_title',
        'target_label',
        'target_value',
        'method',
        'duration_seconds',
        'waste_seconds',
        'ip_address',
        'user_agent',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'waste_seconds' => 'integer',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function formatDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0s';
        }
        $hours = intdiv($seconds, 3600);
        $mins = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return "{$hours}h {$mins}m {$secs}s";
        }
        if ($mins > 0) {
            return "{$mins}m {$secs}s";
        }

        return "{$secs}s";
    }
}
