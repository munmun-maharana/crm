<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'city',
        'source',
        'status',
        'notes',
        'meta',
        'assigned_to',
        'last_contact_at',
        'follow_up',
        'follow_up_at',
        'follow_up_has_time',
    ];

    protected function casts(): array
    {
        return [
            'last_contact_at' => 'datetime',
            'follow_up_at' => 'datetime',
            'follow_up' => 'boolean',
            'follow_up_has_time' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function followUpScheduleLabel(string $dateFormat = 'd M Y'): ?string
    {
        if (! $this->follow_up || ! $this->follow_up_at) {
            return null;
        }

        $date = $this->follow_up_at->format($dateFormat);

        return $this->follow_up_has_time
            ? $date.', '.$this->follow_up_at->format('h:i A')
            : $date.' · Anytime';
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function scopeVisibleTo($query, User $user)
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where('assigned_to', $user->id);
    }

    public function accessibleBy(User $user): bool
    {
        return $user->isAdmin() || (int) $this->assigned_to === (int) $user->id;
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CustomerActivity::class)->latest();
    }

    public function fieldValue(string $key): mixed
    {
        if (in_array($key, CrmField::CORE_KEYS, true)) {
            return $this->{$key};
        }

        return data_get($this->meta, $key);
    }
}
