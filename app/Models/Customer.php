<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'customer_product')
            ->withPivot(['quantity', 'price', 'notes'])
            ->withTimestamps();
    }

    public function hasProduct(int $productId): bool
    {
        if ($this->relationLoaded('products')) {
            if ($this->products->contains('id', $productId)) {
                return true;
            }
        }
        $metaIds = (array) data_get($this->meta, 'product_ids', []);
        return in_array($productId, array_map('intval', $metaIds), true);
    }

    public function productBadgesHtml($allActiveProducts = null, bool $compact = false, ?bool $onlyChecked = null): string
    {
        if ($allActiveProducts === null) {
            $allActiveProducts = \App\Models\Product::active()->ordered()->get();
        }
        if ($allActiveProducts->isEmpty()) {
            return '';
        }

        // In Assign Service table / listing, show ONLY checked products
        if ($onlyChecked === null) {
            $onlyChecked = $this->isBooking();
        }

        $html = '';
        if ($compact) {
            $items = '';
            foreach ($allActiveProducts as $prod) {
                $isChecked = $this->hasProduct($prod->id);
                if ($onlyChecked && !$isChecked) {
                    continue;
                }
                $bg = $isChecked ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : 'bg-rose-50 text-rose-700 border-rose-200';
                $icon = $isChecked ? '✓' : '✕';
                $title = htmlspecialchars($prod->name . ' (' . ($isChecked ? 'Checked' : 'Not Checked') . ')');
                $short = htmlspecialchars($prod->short_name);
                $items .= "<span class=\"text-[9px] font-bold px-1 py-0.5 rounded border {$bg}\" title=\"{$title}\">{$icon} {$short}</span>";
            }
            if ($items !== '') {
                $html = "<div class=\"mt-1 flex items-center gap-1 flex-wrap\">{$items}</div>";
            }
        } else {
            $items = '';
            foreach ($allActiveProducts as $prod) {
                $isChecked = $this->hasProduct($prod->id);
                if ($onlyChecked && !$isChecked) {
                    continue;
                }
                $title = htmlspecialchars($prod->name . ' (' . ($isChecked ? 'Checked in Feedback' : 'Not Checked') . ')');
                $short = htmlspecialchars($prod->short_name);
                if ($isChecked) {
                    $items .= "<span class=\"inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300 shadow-2xs\" title=\"{$title}\">"
                        . '<svg class="w-3 h-3 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>'
                        . "<span>{$short}</span></span>";
                } else {
                    $items .= "<span class=\"inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200\" title=\"{$title}\">"
                        . '<svg class="w-3 h-3 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>'
                        . "<span>{$short}</span></span>";
                }
            }
            if ($items !== '') {
                $html = "<div class=\"inline-flex items-center gap-1.5 flex-wrap max-w-[340px]\">{$items}</div>";
            } elseif ($onlyChecked) {
                $html = '<span class="text-slate-400 text-[11.5px]">—</span>';
            }
        }

        return $html;
    }

    public function operationCopy(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(self::class, 'source_customer_id')->where('pipeline', 'operations');
    }

    public function bookingCopy(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(self::class, 'source_customer_id')->where('pipeline', 'booking')->latestOfMany();
    }

    public function bookingCopies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'source_customer_id')->where('pipeline', 'booking');
    }

    /**
     * Return all product IDs already assigned to Assign Service (booking pipeline) for this customer.
     */
    public function assignedBookingProductIds(): array
    {
        if ($this->isBooking()) {
            $ids = $this->products->pluck('id')->map(fn ($id) => (int) $id)->all();
            $metaIds = array_map('intval', (array) data_get($this->meta, 'product_ids', []));
            return array_values(array_unique(array_filter(array_merge($ids, $metaIds))));
        }

        $copies = $this->relationLoaded('bookingCopies')
            ? $this->bookingCopies
            : $this->bookingCopies()->with('products:id')->get();

        $ids = [];
        foreach ($copies as $copy) {
            foreach ($copy->products as $p) {
                $ids[] = (int) $p->id;
            }
            foreach ((array) data_get($copy->meta, 'product_ids', []) as $mid) {
                $ids[] = (int) $mid;
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    public function sourceCustomer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_customer_id');
    }

    public function bookingSlots(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BookingSlot::class, 'customer_id')
            ->orderByDesc('slot_date')
            ->orderByDesc('start_time');
    }

    public function isOperations(): bool
    {
        return $this->pipeline === 'operations';
    }

    public function isBooking(): bool
    {
        return $this->pipeline === 'booking';
    }

    public function isCallCrm(): bool
    {
        return $this->pipeline === 'call_crm';
    }

    public static function normalizePhoneTo10Digits(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }

        return strlen($digits) >= 10 ? substr($digits, -10) : $digits;
    }

    public static function phoneExistsInPipeline(string $phone, string $pipeline = 'call_crm', ?int $excludeCustomerId = null): bool
    {
        $raw = trim($phone);
        if ($raw === '') {
            return false;
        }

        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '') {
            return false;
        }

        $last10 = strlen($digits) >= 10 ? substr($digits, -10) : $digits;

        $query = self::query()->where('pipeline', $pipeline);
        if ($excludeCustomerId) {
            $query->where('id', '!=', $excludeCustomerId);
        }

        return $query->where(function ($q) use ($raw, $digits, $last10) {
            $q->where('phone', $raw)
              ->orWhere('phone', $digits);

            if (strlen($last10) >= 7) {
                $q->orWhere('phone', 'like', "%{$last10}")
                  ->orWhereRaw("REGEXP_REPLACE(phone, '[^0-9]', '') LIKE ?", ["%{$last10}"])
                  ->orWhere('meta->phone', 'like', "%{$last10}%")
                  ->orWhere('meta->phone_no', 'like', "%{$last10}%")
                  ->orWhere('meta->mobile', 'like', "%{$last10}%")
                  ->orWhere('meta->contact', 'like', "%{$last10}%")
                  ->orWhere('meta->contact_no', 'like', "%{$last10}%");
            }
        })->exists();
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
            return $query->whereIn('pipeline', array_filter([
                'operations',
                $user->canAccessCallCrm() ? 'call_crm' : null,
            ]));
        }

        return $query->where(function ($q) use ($user) {
            $q->where('assigned_to', $user->id)
              ->orWhereNull('assigned_to');
        });
    }

    public function accessibleBy(User $user): bool
    {
        if ($user->canSeeAllRecords()) {
            return true;
        }

        if ($this->isOperations() && $user->canAccessOperations()) {
            return $this->assigned_to === null || (int) $this->assigned_to === (int) $user->id || $user->isOperation();
        }

        if ($this->isBooking() && $user->canAccessBooking()) {
            return $this->assigned_to === null || (int) $this->assigned_to === (int) $user->id || $user->isOperation() || $user->isCoordinator();
        }

        if ($this->isCallCrm() && $user->canAccessCallCrm()) {
            return $this->assigned_to === null || (int) $this->assigned_to === (int) $user->id;
        }

        if (! $this->isOperations() && ! $this->isCallCrm() && $user->canAccessCrm()) {
            return $this->assigned_to === null || (int) $this->assigned_to === (int) $user->id;
        }

        return $this->assigned_to === null || (int) $this->assigned_to === (int) $user->id;
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
        $core['status'] = 'Pending';
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

    public function mappedBookingAttributes(): array
    {
        $core = [
            'pipeline' => 'booking',
            'source_customer_id' => $this->id,
        ];
        $meta = [];

        $bookingFields = CrmField::inPipeline('booking')->active()->ordered()->get();
        if ($bookingFields->isEmpty()) {
            $callFields = CrmField::inPipeline('call_crm')->active()->ordered()->get();
            foreach ($callFields as $field) {
                $val = $this->fieldValue($field->key);
                if ($field->isCore()) {
                    $core[$field->key] = $val;
                } else {
                    $meta[$field->key] = $val;
                }
            }
        } else {
            foreach ($bookingFields as $field) {
                $from = trim((string) ($field->maps_from ?: $field->key));
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
        }

        if (empty($core['name'])) {
            $core['name'] = $this->name ?: 'Untitled';
        }
        if (empty($core['phone'])) {
            $core['phone'] = $this->phone;
        }
        if (empty($core['city'])) {
            $core['city'] = $this->city;
        }
        $core['status'] = 'Pending';
        if (array_key_exists('assigned_to', $core)) {
            $core['assigned_to'] = $core['assigned_to'] ?: null;
        }
        foreach (['email', 'phone', 'company', 'city', 'source', 'notes'] as $key) {
            if (array_key_exists($key, $core) && $core[$key] === '') {
                $core[$key] = null;
            }
        }

        if ($this->relationLoaded('products') && $this->products->isNotEmpty()) {
            $meta['product_ids'] = $this->products->pluck('id')->all();
            $meta['products'] = $this->products->toArray();
        } elseif (! empty($this->meta['product_ids'])) {
            $meta['product_ids'] = (array) $this->meta['product_ids'];
            $meta['products'] = (array) ($this->meta['products'] ?? []);
        }

        $core['meta'] = $meta ?: null;

        return $core;
    }
}
