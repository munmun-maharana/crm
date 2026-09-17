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
        'pipeline',
        'source_customer_id',
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

    public function operationCopy(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(self::class, 'source_customer_id');
    }

    public function sourceCustomer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_customer_id');
    }

    public function isOperations(): bool
    {
        return $this->pipeline === 'operations';
    }

    public function scopeInPipeline($query, string $pipeline)
    {
        return $query->where('pipeline', $pipeline);
    }

    public function scopeVisibleTo($query, User $user)
    {
        if ($user->canSeeAllRecords()) {
            return $query;
        }

        if ($user->isOperation()) {
            return $query->where('pipeline', 'operations');
        }

        return $query->where('assigned_to', $user->id);
    }

    public function accessibleBy(User $user): bool
    {
        if ($user->canSeeAllRecords()) {
            return true;
        }

        if ($user->isOperation()) {
            return $this->isOperations();
        }

        return ! $this->isOperations() && (int) $this->assigned_to === (int) $user->id;
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

    public function mappedOperationAttributes(): array
    {
        $core = [
            'pipeline' => 'operations',
            'source_customer_id' => $this->id,
        ];
        $meta = [];

        $opFields = CrmField::inPipeline('operations')->active()->ordered()->get();
        foreach ($opFields as $field) {
            $from = trim((string) $field->maps_from);
            if ($from === '') {
                continue;
            }

            $value = $this->fieldValue($from);
            if ($field->isCore()) {
                $core[$field->key] = $value;
            } else {
                $meta[$field->key] = $value;
            }
        }

        if (empty($core['name'])) {
            $core['name'] = $this->name ?: 'Untitled';
        }
        $core['status'] = 'new';
        if (array_key_exists('assigned_to', $core)) {
            $core['assigned_to'] = $core['assigned_to'] ?: null;
        }
        foreach (['email', 'phone', 'company', 'city', 'source', 'notes'] as $key) {
            if (array_key_exists($key, $core) && $core[$key] === '') {
                $core[$key] = null;
            }
        }

        $core['meta'] = $meta ?: null;

        return $core;
    }
}
