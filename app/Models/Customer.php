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

    public function getPhoneAttribute(?string $value): ?string
    {
        if ($value !== null && $value !== '') {
            return $value;
        }

        return $this->meta['phone_no']
            ?? $this->meta['phone']
            ?? $this->meta['mobile']
            ?? $this->meta['contact']
            ?? $this->meta['contact_no']
            ?? null;
    }

    public function getEmailAttribute(?string $value): ?string
    {
        if ($value !== null && $value !== '') {
            return $value;
        }

        return $this->meta['email']
            ?? $this->meta['email_address']
            ?? $this->meta['mail']
            ?? null;
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

    /**
     * Single combined Date cell HTML for the table:
     * - Initially shows Entry/Created Date
     * - When Follow-up is scheduled, shows Follow-up Date
     * - When Booking is confirmed, shows Booking Confirmation Date
     */
    public function actionDateCellHtml(): string
    {
        if ($this->follow_up && $this->follow_up_at) {
            $timeLine = $this->follow_up_has_time
                ? e($this->follow_up_at->format('h:i A'))
                : 'Anytime';

            return '<span class="inline-flex px-2 py-0.5 rounded bg-amber-50 text-amber-800 font-semibold">'
                .e($this->follow_up_at->format('d M Y')).'</span>'
                .'<div class="text-[10px] text-slate-400 mt-0.5">Follow-up · '.$timeLine.'</div>';
        }

        if ($this->follow_up_at) {
            $slotTime = $this->meta['booking_slot_time'] ?? null;
            $sub = $slotTime ? e($slotTime) : ($this->follow_up_has_time ? e($this->follow_up_at->format('h:i A')) : 'Booked');

            return '<span class="inline-flex px-2 py-0.5 rounded bg-emerald-50 text-emerald-800 font-semibold">'
                .e($this->follow_up_at->format('d M Y')).'</span>'
                .'<div class="text-[10px] text-emerald-600 font-medium mt-0.5">'.$sub.'</div>';
        }

        $entryDate = $this->fieldValue('date') ?: ($this->created_at ? $this->created_at->format('d M Y') : null);
        if ($entryDate) {
            return '<span class="inline-flex px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-semibold">'
                .e((string) $entryDate).'</span>'
                .'<div class="text-[10px] text-slate-400 mt-0.5">Created</div>';
        }

        return '<span class="text-slate-400">—</span>';
    }

    /**
     * Compact HTML for the left-side customer list showing:
     * - Booking Date & Time or Follow-up Date & Time (when present)
     * - Created / Entry Date
     */
    public function listDateSummaryHtml(): string
    {
        $parts = [];

        if ($this->follow_up && $this->follow_up_at) {
            $timeStr = $this->follow_up_has_time
                ? $this->follow_up_at->format('h:i A')
                : 'Anytime';
            $parts[] = '<div class="text-[11px] font-semibold text-amber-800 truncate">'
                .'⏰ FU: '.e($this->follow_up_at->format('d M Y')).' · '.e($timeStr)
                .'</div>';
        } elseif ($this->follow_up_at) {
            $slotTime = $this->meta['booking_slot_time']
                ?? $this->meta['booking_time']
                ?? ($this->follow_up_has_time ? $this->follow_up_at->format('h:i A') : null);
            $timeSuffix = $slotTime ? ' · '.e((string) $slotTime) : '';
            $parts[] = '<div class="text-[11px] font-semibold text-emerald-700 truncate">'
                .'📅 Booked: '.e($this->follow_up_at->format('d M Y')).$timeSuffix
                .'</div>';
        }

        $entryDate = $this->fieldValue('date') ?: ($this->created_at ? $this->created_at->format('d M Y') : null);
        if ($entryDate) {
            $parts[] = '<div class="text-[10.5px] text-slate-400 truncate">'
                .'Created: <span class="font-medium text-slate-600">'.e((string) $entryDate).'</span>'
                .'</div>';
        }

        return implode('', $parts);
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

        // Assigned records in either CRM or Operations (e.g. front office with ops access)
        return (int) $this->assigned_to === (int) $user->id;
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CustomerActivity::class)->latest();
    }

    public function activationRequests(): HasMany
    {
        return $this->hasMany(ActivationRequest::class)->latest();
    }

    public function fieldValue(string $key): mixed
    {
        if (in_array($key, CrmField::CORE_KEYS, true)) {
            return $this->{$key};
        }

        $val = data_get($this->meta, $key);
        if ($val === null || $val === '') {
            if (in_array($key, ['phone_no', 'mobile', 'contact', 'contact_no'], true)) {
                return $this->phone;
            }
            if (in_array($key, ['email_address', 'mail'], true)) {
                return $this->email;
            }
        }

        return $val;
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
