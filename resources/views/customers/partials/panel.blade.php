@php
    $typeColor = [
        'note' => 'bg-slate-100 text-slate-700',
        'feedback' => 'bg-emerald-100 text-emerald-800',
        'chat' => 'bg-violet-100 text-violet-700',
        'call' => 'bg-sky-100 text-sky-700',
        'whatsapp' => 'bg-emerald-100 text-emerald-700',
        'email' => 'bg-amber-100 text-amber-800',
        'sms' => 'bg-indigo-100 text-indigo-700',
        'status' => 'bg-rose-100 text-rose-700',
        'system' => 'bg-mist text-slate-500',
    ];
    $initials = collect(explode(' ', $customer->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
    $statusField = \App\Models\CrmField::inPipeline($customer->pipeline ?? 'crm')->where('key', 'status')->first();
    $statusOptions = $statusOptions ?? (! empty($statusField?->options) ? $statusField->options : ['new', 'interested', 'contacted', 'converted', 'lost', 'request']);
    $updateFields = \App\Models\CrmField::inPipeline($customer->pipeline ?? 'crm')
        ->active()
        ->where('key', '!=', 'status')
        ->ordered()
        ->get()
        ->filter(fn ($f) => (bool) ($f->in_update ?? false))
        ->values();

    if ($customer->isOperations() && $updateFields->isEmpty()) {
        $crmUpdateKeys = \App\Models\CrmField::inPipeline('crm')
            ->active()
            ->where('key', '!=', 'status')
            ->get()
            ->filter(fn ($f) => (bool) ($f->in_update ?? false))
            ->pluck('key')
            ->all();

        $opAll = \App\Models\CrmField::inPipeline('operations')
            ->active()
            ->where('key', '!=', 'status')
            ->ordered()
            ->get();

        $updateFields = $opAll->filter(fn ($f) => in_array($f->maps_from, $crmUpdateKeys, true) || in_array($f->key, $crmUpdateKeys, true))->values();

        if ($updateFields->isEmpty()) {
            $skipKeys = ['name', 'email', 'phone', 'company', 'status', 'assigned_to', 'notes', 'date', 'entry_date', 'created_date', 'created_at'];
            $updateFields = $opAll->reject(fn ($f) => in_array(strtolower($f->key), $skipKeys, true))->values();
        }
    }

    $headerKeys = ['name', 'email', 'phone', 'company', 'city', 'status', 'assigned_to', 'notes', 'date', 'entry_date', 'created_date', 'created_at'];
    $extraFields = collect($formFields ?? [])->reject(fn ($f) => in_array(strtolower($f->key), $headerKeys, true));
    $visibleExtras = $extraFields->filter(function ($field) use ($customer) {
        $v = $customer->fieldValue($field->key);
        return $v !== null && $v !== '';
    });
    $createdDateVal = $customer->fieldValue('date') ?: ($customer->created_at ? $customer->created_at->format('d M Y') : null);
    $actionDateLabel = null;
    $actionDateVal = null;
    if ($customer->follow_up && $customer->follow_up_at) {
        $actionDateLabel = 'Follow-up Date & Time';
        $actionDateVal = $customer->followUpScheduleLabel();
    } elseif ($customer->follow_up_at) {
        $actionDateLabel = 'Booking Date & Time';
        $slotTime = $customer->meta['booking_slot_time']
            ?? $customer->meta['booking_time']
            ?? ($customer->follow_up_has_time ? $customer->follow_up_at->format('h:i A') : null);
        $actionDateVal = $customer->follow_up_at->format('d M Y') . ($slotTime ? ' · ' . $slotTime : '');
    }
    $relatedCustomerIds = [$customer->id];
    if ($customer->source_customer_id) {
        $relatedCustomerIds[] = (int) $customer->source_customer_id;
    }
    if ($customer->isCallCrm()) {
        $bookingCustomerIds = \App\Models\Customer::where('pipeline', 'booking')
            ->where('source_customer_id', $customer->id)
            ->pluck('id')
            ->map(fn($id) => (int)$id)
            ->all();
        $relatedCustomerIds = array_merge($relatedCustomerIds, $bookingCustomerIds);
    }
    $relatedCustomerIds = array_values(array_unique(array_filter($relatedCustomerIds)));

    $activitiesList = \App\Models\CustomerActivity::whereIn('customer_id', $relatedCustomerIds)
        ->with('user')
        ->orderByDesc('created_at')
        ->orderByDesc('id')
        ->get();
    $activitiesCount = $activitiesList->count();
@endphp

@php
    $panelMrp = $customer->fieldValue('mrp') ?? $customer->fieldValue('mrp_price');
    $panelSellingPrice = $customer->fieldValue('business_selling_price')
        ?? $customer->fieldValue('business_sale_price')
        ?? $customer->fieldValue('selling_price')
        ?? $customer->fieldValue('seal_price')
        ?? $customer->fieldValue('sale_price');
    if ($panelSellingPrice === null || $panelSellingPrice === '') {
        foreach ((array) ($customer->meta ?? []) as $mk => $mv) {
            $mkl = strtolower((string) $mk);
            if ((str_contains($mkl, 'selling') || str_contains($mkl, 'seal_price') || str_contains($mkl, 'sale_price')) && $mv !== null && $mv !== '') {
                $panelSellingPrice = $mv;
                break;
            }
        }
    }
    $panelCarModel = $customer->fieldValue('car_model') ?? $customer->fieldValue('model') ?? $customer->fieldValue('vehicle_model') ?? $customer->fieldValue('vehicle_type');
    if (is_array($panelCarModel)) {
        $panelCarModel = implode(', ', $panelCarModel);
    }

    $bookingTargetCid = ($customer->isOperations() && $customer->source_customer_id)
        ? $customer->source_customer_id
        : $customer->id;
    $allCustomerBookings = \App\Models\BookingSlot::query()
        ->where(function ($q) use ($bookingTargetCid, $customer) {
            $q->where('customer_id', $bookingTargetCid);
            $slotMetaId = data_get($customer->meta, 'booking_slot_id');
            if ($slotMetaId) {
                $q->orWhere('id', (int) $slotMetaId);
            }
        })
        ->orderByDesc('slot_date')
        ->orderByDesc('start_time')
        ->get();

    $panelEditPayload = array_merge([
        'id' => $customer->id,
        'name' => $customer->name,
        'email' => $customer->email,
        'phone' => $customer->phone,
        'company' => $customer->company,
        'city' => $customer->city,
        'source' => $customer->source,
        'status' => $customer->status,
        'notes' => $customer->notes,
        'assigned_to' => $customer->assigned_to,
        'meta' => $customer->meta ?? (object) [],
    ], is_array($customer->meta) ? $customer->meta : []);
@endphp
@php
    $isCallCrmPanel = $customer->isCallCrm();
    $isBookingPanel = $customer->isBooking();

    // Find all related booking services for this customer across Call CRM or Booking
    $sourceCustId = $isCallCrmPanel
        ? $customer->id
        : ($customer->source_customer_id ?: null);

    $allRelatedServices = ($isCallCrmPanel || $isBookingPanel)
        ? \App\Models\Customer::query()
            ->where('pipeline', 'booking')
            ->where(function ($q) use ($sourceCustId, $customer) {
                $matched = false;
                if ($sourceCustId) {
                    $q->where('source_customer_id', $sourceCustId);
                    $matched = true;
                }
                if (!empty($customer->phone)) {
                    $cleanPhone = \App\Models\Customer::normalizePhoneTo10Digits($customer->phone);
                    if ($cleanPhone) {
                        if ($matched) {
                            $q->orWhere('phone', 'like', "%{$cleanPhone}%");
                        } else {
                            $q->where('phone', 'like', "%{$cleanPhone}%");
                            $matched = true;
                        }
                    }
                }
                if ($matched) {
                    $q->orWhere('id', $customer->id);
                } else {
                    $q->where('id', $customer->id);
                }
            })
            ->with('products')
            ->latest('id')
            ->get()
        : collect();

    $allCallCrmServices = $allRelatedServices;
    $hasBookingsCol = $allCustomerBookings->isNotEmpty();
    $hasServicesCol = $allRelatedServices->isNotEmpty();
    $hasRightCol = $hasBookingsCol || $hasServicesCol || $isCallCrmPanel || $isBookingPanel;
    $leftColSpan = $hasRightCol ? 'lg:col-span-3' : 'lg:col-span-4';
    $midColSpan = $hasRightCol ? 'lg:col-span-6' : 'lg:col-span-8';
@endphp
<div class="p-2.5 sm:p-3 space-y-2.5 w-full"
     data-panel-edit-payload='@json($panelEditPayload)'
     data-panel-car-model="{{ $panelCarModel !== null ? $panelCarModel : '' }}"
     data-panel-mrp="{{ $panelMrp !== null ? $panelMrp : '' }}"
     data-panel-selling-price="{{ $panelSellingPrice !== null ? $panelSellingPrice : '' }}">
    {{-- Main 3-Column Layout when bookings exist: Left = Compact Customer Details | Mid = Update Form | Right = Compact Booking List --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-2.5 items-stretch">

        {{-- LEFT COLUMN: More Compact Customer Details Card - Soft Sky Blue Theme --}}
        <div class="{{ $leftColSpan }} h-full flex flex-col rounded-xl border border-sky-200/90 bg-sky-50/70 shadow-xs overflow-hidden">
            {{-- Card Header --}}
            <div class="h-8 shrink-0 px-2.5 bg-sky-100/80 border-b border-sky-200/80 flex items-center justify-between gap-1.5">
                <div class="flex items-center gap-1.5 min-w-0">
                    <span class="w-4 h-4 rounded bg-sky-600 text-white inline-flex items-center justify-center shrink-0">
                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </span>
                    <span class="text-[11.5px] font-bold text-sky-950 truncate">Customer Details</span>
                </div>
                <span class="text-[9.5px] font-bold px-1.5 py-0.2 rounded capitalize bg-white/90 text-sky-800 shrink-0">
                    {{ $customer->status }}
                </span>
            </div>

            <div class="p-2 space-y-1.5 flex-1 flex flex-col">
                {{-- Profile Summary --}}
                <div class="flex items-start gap-1.5 rounded-lg bg-white/85 p-1.5">
                    <div class="w-8 h-8 rounded-full bg-brand text-white flex items-center justify-center font-bold text-[11px] shrink-0">
                        {{ strtoupper($initials) }}
                    </div>
                    <div class="min-w-0 flex-1 leading-tight">
                        <div class="flex flex-wrap items-center gap-1">
                            <div class="text-[13px] font-bold text-ink truncate">{{ $customer->name }}</div>
                            @if($customer->isOperations() && $customer->source_customer_id)
                                <span class="text-[9.5px] font-bold px-1 py-0.2 rounded bg-indigo-100 text-indigo-700">
                                    CRM #{{ $customer->source_customer_id }}
                                </span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 text-[11.5px] text-slate-700 font-semibold truncate mt-0.5">
                            <span class="tracking-wide">{{ $customer->phone ?: 'No phone' }}</span>
                            @if($customer->phone)
                                <button type="button"
                                        onclick="copyToClipboard('{{ addslashes($customer->phone) }}', this)"
                                        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-white hover:bg-brand hover:text-white text-slate-600 border border-slate-300 shadow-2xs transition"
                                        title="Copy phone number">
                                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    <span>Copy</span>
                                </button>
                            @endif
                            @if($customer->email)
                                <span class="text-slate-400 font-normal">·</span>
                                <span class="text-[11px] text-slate-500 font-normal truncate">{{ $customer->email }}</span>
                            @endif
                        </div>
                        <div class="text-[10.5px] text-slate-400 truncate">
                            {{ $customer->city ?: '—' }}@unless($customer->isCallCrm()) · Agent: <span class="text-slate-600 font-medium">{{ $customer->assignee?->name ?: 'Unassigned' }}</span>@endunless
                        </div>
                    </div>
                </div>

                {{-- Quick Action Buttons Bar --}}
                <div class="flex items-center gap-1 w-full">
                    <button type="button" title="Call" data-cid="{{ $customer->id }}" onclick="runAutoAction(this.dataset.cid, 'call')"
                            class="flex-1 h-7 inline-flex items-center justify-center rounded-md bg-sky-100/90 text-sky-700 hover:bg-sky-200 transition">
                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    </button>
                    <button type="button" title="WhatsApp" data-cid="{{ $customer->id }}" onclick="runAutoAction(this.dataset.cid, 'whatsapp')"
                            class="flex-1 h-7 inline-flex items-center justify-center rounded-md bg-emerald-100/90 text-emerald-700 hover:bg-emerald-200 transition">
                        <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0012.04 2zm0 1.82c4.46 0 8.09 3.63 8.09 8.09s-3.63 8.09-8.09 8.09c-1.42 0-2.8-.37-4.01-1.06l-.29-.17-3.12.82.83-3.04-.19-.31a8.05 8.05 0 01-1.22-4.33c0-4.46 3.63-8.09 8.09-8.09zm4.57 10.38c-.18-.09-1.06-.52-1.22-.58-.16-.06-.28-.09-.4.09-.12.18-.46.58-.56.7-.1.12-.2.13-.38.04-.18-.09-.76-.28-1.45-.89-.54-.48-.9-1.07-1.01-1.25-.1-.18-.01-.28.08-.37.08-.08.18-.2.27-.3.09-.1.12-.18.18-.3.06-.12.03-.22-.02-.31-.04-.09-.4-.96-.55-1.31-.14-.34-.29-.3-.4-.3h-.34c-.12 0-.31.04-.47.22-.16.18-.62.61-.62 1.48s.64 1.72.73 1.84c.09.12 1.25 1.91 3.03 2.68.42.18.75.29 1.01.37.42.13.81.11 1.11.07.34-.05 1.06-.43 1.21-.85.15-.42.15-.78.1-.85-.04-.07-.16-.12-.34-.21z"/></svg>
                    </button>
                    <button type="button" title="Email" data-cid="{{ $customer->id }}" onclick="runAutoAction(this.dataset.cid, 'email')"
                            class="flex-1 h-7 inline-flex items-center justify-center rounded-md bg-amber-100/90 text-amber-800 hover:bg-amber-200 transition">
                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </button>
                    <button type="button" title="SMS" data-cid="{{ $customer->id }}" onclick="runAutoAction(this.dataset.cid, 'sms')"
                            class="flex-1 h-7 inline-flex items-center justify-center rounded-md bg-indigo-100/90 text-indigo-700 hover:bg-indigo-200 transition">
                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    </button>
                    @if($customer->isCallCrm())
                        @php
                            $custLabel = trim((string) ($customer->fieldValue('label') ?? ''));
                            if ($custLabel === '') $custLabel = 'Open';
                            $isLabelEngaged = strtolower($custLabel) === 'engaged';
                        @endphp
                        <span title="Label: {{ $custLabel }}"
                              class="h-7 inline-flex items-center justify-center gap-1 px-2.5 rounded-md text-[11px] font-bold whitespace-nowrap {{ $isLabelEngaged ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-emerald-100 text-emerald-800 border border-emerald-300' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $isLabelEngaged ? 'bg-amber-500' : 'bg-emerald-500' }}"></span>
                            <span>{{ $custLabel }}</span>
                        </span>
                    @endif
                    @if($customer->isBooking() && $customer->source_customer_id)
                        <span title="Source: Call CRM #{{ $customer->source_customer_id }}"
                              class="h-7 inline-flex items-center justify-center px-2 rounded-md bg-indigo-100 text-indigo-800 text-[10px] font-bold whitespace-nowrap border border-indigo-200">
                            From Call CRM #{{ $customer->source_customer_id }}
                        </span>
                    @endif
                </div>

                {{-- All Customer Fields Grid (Compact, light color backgrounds) --}}
                @if($visibleExtras->isNotEmpty() || $createdDateVal || $actionDateVal)
                    @php
                        $fieldBgPalette = [
                            'bg-white/90 border border-sky-200/70',
                            'bg-indigo-100/60 border border-indigo-200/70',
                            'bg-teal-100/60 border border-teal-200/70',
                            'bg-violet-100/60 border border-violet-200/70',
                            'bg-amber-100/60 border border-amber-200/70',
                            'bg-rose-100/60 border border-rose-200/70',
                        ];
                    @endphp
                    <div class="pt-1 border-t border-sky-200/60 flex flex-col gap-1 text-[10.5px] leading-tight break-words">
                        @if($createdDateVal)
                            <div class="rounded bg-white/90 border border-sky-200/70 px-2 py-1 flex items-center justify-between gap-2">
                                <span class="text-slate-500 text-[9px] font-semibold uppercase tracking-wide shrink-0">Created</span>
                                <span class="font-semibold text-ink text-right">{{ $createdDateVal }}</span>
                            </div>
                        @endif
                        @if($actionDateVal)
                            <div class="rounded {{ $customer->follow_up ? 'bg-amber-100/80 border border-amber-200/80' : 'bg-emerald-100/80 border border-emerald-200/80' }} px-2 py-1 flex items-center justify-between gap-2">
                                <span class="text-slate-600 text-[9px] font-semibold uppercase tracking-wide shrink-0">{{ $actionDateLabel }}</span>
                                <span class="font-bold {{ $customer->follow_up ? 'text-amber-900' : 'text-emerald-900' }} text-right">{{ $actionDateVal }}</span>
                            </div>
                        @endif
                        @foreach($visibleExtras as $field)
                            @php
                                $v = $customer->fieldValue($field->key);
                                $strVal = trim(is_array($v) ? implode(', ', $v) : (string) $v);
                                $isMapField = str_contains(strtolower($field->key), 'map') || str_contains(strtolower($field->label), 'map');
                                $isUrlVal = (bool) filter_var($strVal, FILTER_VALIDATE_URL) || (bool) preg_match('~^https?://~i', $strVal);
                                $tileBg = $fieldBgPalette[$loop->index % count($fieldBgPalette)];
                            @endphp
                            <div class="rounded {{ $tileBg }} px-2 py-1 flex items-center justify-between gap-2">
                                <span class="text-slate-500 text-[9px] font-semibold uppercase tracking-wide shrink-0">{{ $field->label }}</span>
                                @if($isMapField || $isUrlVal)
                                    @php
                                        $mapHref = $isUrlVal
                                            ? $strVal
                                            : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($strVal);
                                    @endphp
                                    <a href="{{ $mapHref }}" target="_blank" rel="noopener noreferrer"
                                       class="inline-flex items-center gap-1 rounded bg-brand-soft hover:bg-brand hover:text-white text-brand px-1.5 py-0.5 text-[10px] font-bold transition shrink-0">
                                        <svg class="w-2.5 h-2.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        <span>{{ $isMapField ? 'Open Map URL' : 'Open URL' }}</span>
                                    </a>
                                @else
                                    <span class="font-semibold text-ink text-right">{{ $strVal }}</span>
                                @endif
                            </div>
                        @endforeach
                        @if(($panelMrp !== null && $panelMrp !== '' && $visibleExtras->where('key', 'mrp')->isEmpty()))
                            <div class="rounded bg-amber-100/70 border border-amber-200/80 px-2 py-1 flex items-center justify-between gap-2">
                                <span class="text-amber-900 text-[9px] font-bold uppercase tracking-wide shrink-0">MRP</span>
                                <span class="font-bold text-ink text-right">₹{{ number_format((float) $panelMrp, 2) }}</span>
                            </div>
                        @endif
                        @if(($customer->isCallCrm() || $customer->isBooking()) && $customer->products->isNotEmpty())
                            <div class="rounded-md bg-cyan-100/70 border border-cyan-300/80 px-2 py-1">
                                <div class="text-cyan-900 text-[9px] font-bold uppercase tracking-wide flex items-center justify-between">
                                    <span class="flex items-center gap-1">
                                        <span>📦</span>
                                        <span>Products ({{ $customer->products->count() }})</span>
                                    </span>
                                    <span class="font-extrabold text-emerald-800">₹{{ number_format($customer->products->sum('price'), 2) }}</span>
                                </div>
                                <div class="mt-0.5 flex flex-wrap gap-1">
                                    @foreach($customer->products as $cp)
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded bg-white text-cyan-950 text-[10px] font-semibold border border-cyan-300 shadow-2xs">
                                            <span>{{ $cp->name }}</span>
                                            @if($cp->price > 0)
                                                <span class="text-emerald-700 font-bold">₹{{ number_format($cp->price, 0) }}</span>
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- MIDDLE COLUMN: Update Form Card --}}
        <div class="{{ $midColSpan }} flex flex-col">
            @if($customer->isOperations())
                @php
                    $curOpStatus = strtolower(trim((string) $customer->status));
                    $activeWashState = match (true) {
                        in_array($curOpStatus, ['success', 'wash success', 'completed', 'converted'], true) => 'success',
                        in_array($curOpStatus, ['cancel', 'cancelled', 'canceled', 'lost'], true) => 'cancelled',
                        default => 'pending',
                    };
                @endphp
                <form action="{{ route('customers.activities', $customer) }}"
                      enctype="multipart/form-data"
                      x-data="{ washState: '{{ $activeWashState }}' }"
                      data-customer-name="{{ $customer->name }}"
                      data-initial-status="{{ $customer->status }}"
                      data-initial-fu="{{ $customer->follow_up ? '1' : '0' }}"
                      data-initial-fu-date="{{ $customer->follow_up_at?->format('Y-m-d') }}"
                      data-initial-fu-time="{{ $customer->follow_up_has_time ? $customer->follow_up_at?->format('H:i') : '' }}"
                      class="h-full flex flex-col rounded-xl border border-indigo-200/90 bg-indigo-50/55 shadow-xs overflow-hidden"
                      onsubmit="event.preventDefault(); Alpine.$data(document.body).openPostUpdateConfirm(this)">
                    @csrf
                    <input type="hidden" name="type" value="status">
                    <input type="hidden" name="wash_action" value="1">
                    <input type="hidden" name="status_change" id="opsWashStatus-{{ $customer->id }}" value="{{ ucfirst($activeWashState) }}">

                    <div class="h-9 shrink-0 px-3 bg-indigo-100/80 border-b border-indigo-200/80 flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5">
                            <span class="w-5 h-5 rounded bg-indigo-600 text-white inline-flex items-center justify-center shrink-0">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </span>
                            <div>
                                <span class="text-[12px] font-bold text-indigo-950">Wash Status & Feedback</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 flex flex-col p-3 space-y-2.5">
                        <div class="rounded-lg bg-violet-50/60 p-2.5 space-y-2.5">
                        {{-- 3 Wash Status Buttons --}}
                        <div class="grid grid-cols-3 gap-2">
                            <button type="button"
                                    @click="washState = 'pending'; const f = $el.closest('form'); f.querySelector('[name=status_change]').value = 'Pending'; Alpine.$data(document.body).postActivity(f);"
                                    :class="washState === 'pending'
                                        ? 'border-amber-500 bg-amber-500 text-white ring-2 ring-amber-500/25'
                                        : 'border-amber-200 bg-amber-50/70 text-amber-800 hover:bg-amber-100'"
                                    class="inline-flex items-center justify-center gap-1.5 rounded-lg border px-3 py-2 text-[12px] font-bold transition shadow-2xs">
                                <span>⏳</span>
                                <span>Pending</span>
                            </button>

                            <button type="button"
                                    @click="washState = 'success'; const f = $el.closest('form'); f.querySelector('[name=status_change]').value = 'Success'; Alpine.$data(document.body).postActivity(f);"
                                    :class="washState === 'success'
                                        ? 'border-emerald-600 bg-emerald-600 text-white ring-2 ring-emerald-600/25'
                                        : 'border-emerald-200 bg-emerald-50/70 text-emerald-800 hover:bg-emerald-100'"
                                    class="inline-flex items-center justify-center gap-1.5 rounded-lg border px-3 py-2 text-[12px] font-bold transition shadow-2xs">
                                <span>✓</span>
                                <span>Wash Success</span>
                            </button>

                            <button type="button"
                                    @click="washState = 'cancelled'; const f = $el.closest('form'); f.querySelector('[name=status_change]').value = 'Cancelled'; Alpine.$data(document.body).postActivity(f);"
                                    :class="washState === 'cancelled'
                                        ? 'border-rose-600 bg-rose-600 text-white ring-2 ring-rose-600/25'
                                        : 'border-rose-200 bg-rose-50/70 text-rose-800 hover:bg-rose-100'"
                                    class="inline-flex items-center justify-center gap-1.5 rounded-lg border px-3 py-2 text-[12px] font-bold transition shadow-2xs">
                                <span>✕</span>
                                <span>Cancel</span>
                            </button>
                        </div>

                        <textarea name="body" id="activityBody-{{ $customer->id }}" rows="2" placeholder="Write wash feedback / what happened…"
                                  @keydown.enter.exact="if(!$event.shiftKey) { $event.preventDefault(); $el.closest('form').requestSubmit(); }"
                                  class="w-full rounded-lg border border-violet-200/80 bg-white px-3 py-2 text-[12px] leading-snug outline-none resize-y min-h-[60px]
                                         focus:bg-white focus:border-brand focus:ring-2 focus:ring-brand/20 transition"></textarea>

                        <div class="flex flex-wrap items-center gap-2">
                            <label class="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-violet-300 bg-white/90 hover:bg-brand-soft/50 hover:border-brand px-3 py-1.5 text-[11px] font-semibold text-slate-600 hover:text-brand cursor-pointer transition">
                                <svg class="w-3.5 h-3.5 shrink-0 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span>Upload Image (Optional)</span>
                                <input type="file" name="images[]" accept="image/*" multiple class="hidden"
                                       onchange="updateImageFileLabel(this)">
                            </label>
                            <span data-img-info class="text-[11px] text-slate-400 truncate max-w-[260px]">No image selected</span>
                        </div>
                        </div>

                        @if($updateFields->isNotEmpty())
                            <div class="rounded-lg bg-emerald-50/50 p-2.5 grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                @foreach($updateFields as $uf)
                                    @php
                                        $rawInitVal = $customer->fieldValue($uf->key);
                                        if (is_array($rawInitVal)) $rawInitVal = implode(', ', $rawInitVal);
                                        $ufVal = $rawInitVal;
                                        if (($ufVal === null || trim((string) $ufVal) === '') && $uf->type === 'select' && ($uf->auto_select ?? false) && !empty($uf->options)) {
                                            $ufVal = $uf->options[0];
                                        }
                                        if (($ufVal === null || trim((string) $ufVal) === '') && $uf->type === 'date') {
                                            $ufVal = now()->toDateString();
                                        }
                                    @endphp
                                    <div>
                                        <label class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-1">{{ $uf->label }}</label>
                                        @if($uf->type === 'select')
                                            <div class="relative">
                                                <select name="update_fields[{{ $uf->key }}]"
                                                        data-field-label="{{ $uf->label }}"
                                                        data-initial-value="{{ (string) $rawInitVal }}"
                                                        class="w-full appearance-none rounded-lg border border-line bg-mist/50 hover:bg-white focus:bg-white focus:border-brand focus:ring-2 focus:ring-brand/20 pl-2.5 pr-7 py-1.5 text-[12px] font-medium text-ink outline-none transition">
                                                    @unless(($uf->auto_select ?? false) && !empty($uf->options))
                                                        <option value="">Select {{ $uf->label }}</option>
                                                    @endunless
                                                    @foreach($uf->options ?? [] as $opt)
                                                        <option value="{{ $opt }}" @selected(strtolower(trim((string) $ufVal)) === strtolower(trim((string) $opt)))>{{ $opt }}</option>
                                                    @endforeach
                                                </select>
                                                <svg class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                            </div>
                                        @elseif($uf->type === 'phone')
                                            <input name="update_fields[{{ $uf->key }}]" value="{{ $ufVal }}"
                                                   data-field-label="{{ $uf->label }}"
                                                   data-initial-value="{{ (string) $rawInitVal }}"
                                                   type="tel" inputmode="tel" pattern="[0-9+\-\s()]*"
                                                   oninput="this.value = this.value.replace(/[^0-9+\-\s()]/g, '')"
                                                   placeholder="{{ $uf->label }}"
                                                   class="w-full rounded-lg border border-line bg-white px-2.5 py-1.5 text-[12px] outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">
                                        @elseif($uf->key === 'pincode' || $uf->key === 'pin')
                                            <div class="relative" x-data="pincodeAutocomplete({
                                                initialPin: '{{ addslashes($ufVal ?? '') }}',
                                                cid: {{ $customer->id }}
                                            })" @click.outside="open = false">
                                                <input name="update_fields[{{ $uf->key }}]"
                                                       type="text"
                                                       inputmode="numeric"
                                                       x-model="pin"
                                                       @input="onInput($event.target.value)"
                                                       @focus="open = true"
                                                       @keydown.arrow-down.prevent="selectNext()"
                                                       @keydown.arrow-up.prevent="selectPrev()"
                                                       @keydown.enter.prevent="selectActive()"
                                                       placeholder="PIN (e.g. 751015)"
                                                       autocomplete="off"
                                                       data-field-label="{{ $uf->label }}"
                                                       data-initial-value="{{ (string) $rawInitVal }}"
                                                       data-pincode-input="{{ $customer->id }}"
                                                       class="w-full rounded-lg border border-line bg-white px-2.5 py-1.5 text-[12px] font-medium text-ink outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">

                                                {{-- Suggestions Dropdown (PIN + Area) --}}
                                                <div x-show="open && filteredList.length > 0" x-cloak
                                                     class="absolute left-0 top-full mt-1 w-full min-w-[240px] max-w-[340px] max-h-52 overflow-y-auto crm-scroll rounded-xl border border-line bg-white shadow-xl z-50 divide-y divide-slate-100">
                                                    <template x-for="(item, idx) in filteredList" :key="idx">
                                                        <button type="button"
                                                                @click="choose(item)"
                                                                :class="activeIndex === idx ? 'bg-brand-soft text-brand' : 'hover:bg-slate-50 text-slate-700'"
                                                                class="w-full text-left px-3 py-2 flex items-center justify-between gap-2 text-[12px] transition">
                                                            <div class="min-w-0">
                                                                <span class="font-extrabold text-ink" x-text="item.pincode"></span>
                                                                <span class="text-slate-400 mx-1">·</span>
                                                                <span class="font-semibold text-slate-800" x-text="item.area || '—'"></span>
                                                                <template x-if="item.city">
                                                                    <span class="text-[10.5px] text-slate-400 ml-1" x-text="'(' + item.city + ')'"></span>
                                                                </template>
                                                            </div>
                                                            <span class="text-[10px] font-bold text-brand shrink-0">Select ↵</span>
                                                        </button>
                                                    </template>
                                                </div>
                                            </div>
                                        @elseif($uf->key === 'area' || $uf->key === 'ariya')
                                            <div class="relative" x-data="areaAutocomplete({
                                                initialArea: '{{ addslashes($ufVal ?? '') }}',
                                                cid: {{ $customer->id }}
                                            })" @click.outside="open = false">
                                                <input name="update_fields[{{ $uf->key }}]"
                                                       type="text"
                                                       x-model="area"
                                                       @input="onInput($event.target.value)"
                                                       @focus="open = true"
                                                       @keydown.arrow-down.prevent="selectNext()"
                                                       @keydown.arrow-up.prevent="selectPrev()"
                                                       @keydown.enter.prevent="selectActive()"
                                                       placeholder="Area name…"
                                                       autocomplete="off"
                                                       data-field-label="{{ $uf->label }}"
                                                       data-initial-value="{{ (string) $rawInitVal }}"
                                                       data-area-input="{{ $customer->id }}"
                                                       class="w-full rounded-lg border border-line bg-white px-2.5 py-1.5 text-[12px] font-medium text-ink outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">

                                                {{-- Suggestions Dropdown (Area + PIN) --}}
                                                <div x-show="open && filteredList.length > 0" x-cloak
                                                     class="absolute left-0 top-full mt-1 w-full min-w-[240px] max-w-[340px] max-h-52 overflow-y-auto crm-scroll rounded-xl border border-line bg-white shadow-xl z-50 divide-y divide-slate-100">
                                                    <template x-for="(item, idx) in filteredList" :key="idx">
                                                        <button type="button"
                                                                @click="choose(item)"
                                                                :class="activeIndex === idx ? 'bg-brand-soft text-brand' : 'hover:bg-slate-50 text-slate-700'"
                                                                class="w-full text-left px-3 py-2 flex items-center justify-between gap-2 text-[12px] transition">
                                                            <div class="min-w-0">
                                                                <span class="font-extrabold text-ink" x-text="item.area || '—'"></span>
                                                                <span class="text-slate-400 mx-1">·</span>
                                                                <span class="font-semibold text-slate-600" x-text="item.pincode"></span>
                                                                <template x-if="item.city">
                                                                    <span class="text-[10.5px] text-slate-400 ml-1" x-text="'(' + item.city + ')'"></span>
                                                                </template>
                                                            </div>
                                                            <span class="text-[10px] font-bold text-brand shrink-0">Select ↵</span>
                                                        </button>
                                                    </template>
                                                </div>
                                            </div>
                                        @elseif($uf->type === 'number')
                                            <input name="update_fields[{{ $uf->key }}]" value="{{ $ufVal }}"
                                                   data-field-label="{{ $uf->label }}"
                                                   data-initial-value="{{ (string) $rawInitVal }}"
                                                   type="number" step="any" inputmode="decimal"
                                                   oninput="this.value = this.value.replace(/[^0-9.\-]/g, '')"
                                                   placeholder="{{ $uf->label }}"
                                                   class="w-full rounded-lg border border-line bg-white px-2.5 py-1.5 text-[12px] outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">
                                        @elseif($uf->type === 'date')
                                            <div class="flex items-center gap-1">
                                                <button type="button"
                                                        onclick="shiftInputDate(this.nextElementSibling, -1)"
                                                        title="Previous date"
                                                        class="h-8 px-2.5 inline-flex items-center justify-center rounded-lg border border-line bg-mist/60 hover:bg-brand-soft hover:text-brand text-slate-700 font-bold text-xs transition shrink-0">‹</button>
                                                <input name="update_fields[{{ $uf->key }}]" value="{{ $ufVal }}"
                                                       data-field-label="{{ $uf->label }}"
                                                       data-initial-value="{{ (string) $rawInitVal }}"
                                                       type="date"
                                                       class="flex-1 min-w-0 rounded-lg border border-line bg-white px-2.5 py-1.5 text-[12px] outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">
                                                <button type="button"
                                                        onclick="shiftInputDate(this.previousElementSibling, 1)"
                                                        title="Next date"
                                                        class="h-8 px-2.5 inline-flex items-center justify-center rounded-lg border border-line bg-mist/60 hover:bg-brand-soft hover:text-brand text-slate-700 font-bold text-xs transition shrink-0">›</button>
                                            </div>
                                        @elseif($uf->type === 'time')
                                            <div class="flex items-center gap-1">
                                                <button type="button"
                                                        onclick="shiftInputTime(this.nextElementSibling, -30)"
                                                        title="Previous 30 min"
                                                        class="h-8 px-2.5 inline-flex items-center justify-center rounded-lg border border-line bg-mist/60 hover:bg-brand-soft hover:text-brand text-slate-700 font-bold text-xs transition shrink-0">‹</button>
                                                <input name="update_fields[{{ $uf->key }}]" value="{{ $ufVal }}"
                                                       data-field-label="{{ $uf->label }}"
                                                       data-initial-value="{{ (string) $rawInitVal }}"
                                                       type="time"
                                                       class="flex-1 min-w-0 rounded-lg border border-line bg-white px-2.5 py-1.5 text-[12px] outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">
                                                <button type="button"
                                                        onclick="shiftInputTime(this.previousElementSibling, 30)"
                                                        title="Next 30 min"
                                                        class="h-8 px-2.5 inline-flex items-center justify-center rounded-lg border border-line bg-mist/60 hover:bg-brand-soft hover:text-brand text-slate-700 font-bold text-xs transition shrink-0">›</button>
                                            </div>
                                        @else
                                            <input name="update_fields[{{ $uf->key }}]" value="{{ $ufVal }}"
                                                   data-field-label="{{ $uf->label }}"
                                                   data-initial-value="{{ (string) $rawInitVal }}"
                                                   type="{{ $uf->type === 'email' ? 'email' : 'text' }}"
                                                   placeholder="{{ $uf->label }}"
                                                   class="w-full rounded-lg border border-line bg-white px-2.5 py-1.5 text-[12px] outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="mt-auto rounded-lg bg-amber-50/60 p-2 flex justify-end">
                            <button type="submit"
                                    class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[12.5px] font-semibold px-4 py-2 shadow-xs transition">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                Post Update
                            </button>
                        </div>
                    </div>
                </form>
            @elseif($customer->isBooking())
                {{-- ASSIGN SERVICE COMPACT ACTION FORM: 3 Buttons with Inline Forms --}}
                <form action="{{ route('customers.activities', $customer) }}"
                      enctype="multipart/form-data"
                      data-customer-name="{{ $customer->name }}"
                      data-pipeline="booking"
                      data-is-call-crm="0"
                      class="h-full flex flex-col rounded-xl border border-indigo-200/90 bg-indigo-50/55 shadow-xs overflow-hidden"
                      x-data="{
                          curStatus: '{{ strtolower(trim((string) $customer->status)) }}',
                          activeAction: null,
                          successMrp: '{{ $panelMrp !== null && $panelMrp !== '' ? $panelMrp : '' }}',
                          successNote: '',
                          screenshotPreview: null,
                          cancelReason: '',
                          rescheduleDate: '{{ $customer->follow_up_at ? $customer->follow_up_at->format('Y-m-d') : now()->addDay()->format('Y-m-d') }}',
                          rescheduleTime: '{{ ($customer->follow_up_has_time && $customer->follow_up_at) ? $customer->follow_up_at->format('H:i') : '10:00' }}',
                          rescheduleNote: '',
                          submitting: false,

                          toggleAction(action) {
                              if (this.activeAction === action) {
                                  this.activeAction = null;
                              } else {
                                  this.activeAction = action;
                              }
                          },

                          cancelForm() {
                              this.activeAction = null;
                              this.screenshotPreview = null;
                          },

                          submitSuccess() {
                              if (this.submitting) return;
                              this.submitting = true;
                              const f = $el.closest('form');
                              f.querySelector('[name=status_change]').value = 'Success';
                              f.querySelector('[name=type]').value = 'status';
                              f.querySelector('[name=follow_up]').value = '0';
                              f.querySelector('[name=body]').value = this.successNote.trim() || 'Service completed successfully.';
                              const mrpEl = f.querySelector('[name=mrp]');
                              if (mrpEl) mrpEl.value = this.successMrp;
                              Alpine.$data(document.body).postActivity(f)
                                  .then(() => { this.activeAction = null; })
                                  .finally(() => { this.submitting = false; });
                          },

                          submitCancel() {
                              if (!this.cancelReason.trim()) {
                                  alert('Please enter why this service is being cancelled.');
                                  return;
                              }
                              if (this.submitting) return;
                              this.submitting = true;
                              const f = $el.closest('form');
                              f.querySelector('[name=status_change]').value = 'Cancelled';
                              f.querySelector('[name=type]').value = 'status';
                              f.querySelector('[name=follow_up]').value = '0';
                              f.querySelector('[name=body]').value = this.cancelReason.trim();
                              Alpine.$data(document.body).postActivity(f)
                                  .then(() => { this.activeAction = null; })
                                  .finally(() => { this.submitting = false; });
                          },

                          submitReschedule() {
                              if (!this.rescheduleDate) {
                                  alert('Please select a follow-up date.');
                                  return;
                              }
                              if (this.submitting) return;
                              this.submitting = true;
                              const f = $el.closest('form');
                              f.querySelector('[name=status_change]').value = 'Follow Up';
                              f.querySelector('[name=type]').value = 'status';
                              f.querySelector('[name=follow_up]').value = '1';
                              f.querySelector('[name=follow_up_date]').value = this.rescheduleDate;
                              f.querySelector('[name=follow_up_time_on]').value = this.rescheduleTime ? '1' : '0';
                              f.querySelector('[name=follow_up_time]').value = this.rescheduleTime || '';
                              const bodyVal = this.rescheduleNote.trim() !== '' ? this.rescheduleNote.trim() : ('Rescheduled follow-up to ' + this.rescheduleDate + (this.rescheduleTime ? ' at ' + this.rescheduleTime : ''));
                              f.querySelector('[name=body]').value = bodyVal;
                              Alpine.$data(document.body).postActivity(f)
                                  .then(() => { this.activeAction = null; })
                                  .finally(() => { this.submitting = false; });
                          }
                      }">
                    @csrf
                    <input type="hidden" name="type" value="status">
                    <input type="hidden" name="status_change" value="">
                    <input type="hidden" name="body" value="">
                    <input type="hidden" name="mrp" value="">
                    <input type="hidden" name="follow_up" value="0">
                    <input type="hidden" name="follow_up_date" value="">
                    <input type="hidden" name="follow_up_time_on" value="0">
                    <input type="hidden" name="follow_up_time" value="">

                    {{-- Card Header --}}
                    <div class="h-8 shrink-0 px-2.5 flex items-center justify-between gap-1.5 bg-indigo-100/80 border-b border-indigo-200/80">
                        <div class="flex items-center gap-1.5 min-w-0">
                            <span class="w-4 h-4 rounded bg-indigo-600 text-white inline-flex items-center justify-center shrink-0">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </span>
                            <span class="text-[11.5px] font-bold text-indigo-950">Update Form</span>
                        </div>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full capitalize"
                              :class="{
                                  'bg-emerald-100 text-emerald-800 border border-emerald-200': curStatus === 'success',
                                  'bg-rose-100 text-rose-800 border border-rose-200': curStatus === 'cancelled' || curStatus === 'cancel',
                                  'bg-amber-100 text-amber-800 border border-amber-200': curStatus === 'follow up' || curStatus === 'follow-up' || curStatus === 'rescheduled',
                                  'bg-purple-100 text-purple-800 border border-purple-200': curStatus === 'engaged' || curStatus === 'booked',
                                  'bg-sky-100 text-sky-800 border border-sky-200': curStatus !== 'success' && curStatus !== 'cancelled' && curStatus !== 'follow up' && curStatus !== 'follow-up' && curStatus !== 'engaged' && curStatus !== 'booked'
                              }"
                              x-text="'Status: ' + ('{{ $customer->status }}' || 'New')">
                        </span>
                    </div>

                    <div class="flex-1 flex flex-col p-3 space-y-3">
                        {{-- 3 Buttons in 1 Row --}}
                        <div class="grid grid-cols-3 gap-2">
                            {{-- 1. Success Button --}}
                            <button type="button"
                                    @click="toggleAction('success')"
                                    :disabled="submitting"
                                    :class="activeAction === 'success' || (activeAction === null && curStatus === 'success')
                                        ? 'bg-emerald-600 text-white ring-2 ring-emerald-500/40 shadow-sm'
                                        : 'bg-emerald-50 hover:bg-emerald-600 hover:text-white text-emerald-800 border border-emerald-300 hover:border-emerald-600'"
                                    class="h-12 flex flex-col items-center justify-center rounded-xl font-bold text-[12.5px] transition shadow-2xs group cursor-pointer">
                                <span class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-emerald-600 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    <span>Success</span>
                                </span>
                                <span class="text-[9.5px] opacity-75 font-normal">Complete service</span>
                            </button>

                            {{-- 2. Cancel Button --}}
                            <button type="button"
                                    @click="toggleAction('cancel')"
                                    :disabled="submitting"
                                    :class="activeAction === 'cancel' || (activeAction === null && (curStatus === 'cancelled' || curStatus === 'cancel'))
                                        ? 'bg-rose-600 text-white ring-2 ring-rose-500/40 shadow-sm'
                                        : 'bg-rose-50 hover:bg-rose-600 hover:text-white text-rose-800 border border-rose-300 hover:border-rose-600'"
                                    class="h-12 flex flex-col items-center justify-center rounded-xl font-bold text-[12.5px] transition shadow-2xs group cursor-pointer">
                                <span class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-rose-600 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                    <span>Cancel</span>
                                </span>
                                <span class="text-[9.5px] opacity-75 font-normal">Cancel service</span>
                            </button>

                            {{-- 3. Reschedule Button --}}
                            <button type="button"
                                    @click="toggleAction('reschedule')"
                                    :disabled="submitting"
                                    :class="activeAction === 'reschedule' || (activeAction === null && (curStatus === 'follow up' || curStatus === 'follow-up' || curStatus === 'rescheduled'))
                                        ? 'bg-amber-600 text-white ring-2 ring-amber-500/40 shadow-sm'
                                        : 'bg-amber-50 hover:bg-amber-600 hover:text-white text-amber-900 border border-amber-300 hover:border-amber-600'"
                                    class="h-12 flex flex-col items-center justify-center rounded-xl font-bold text-[12.5px] transition shadow-2xs group cursor-pointer">
                                <span class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-amber-600 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <span>Reschedule</span>
                                </span>
                                <span class="text-[9.5px] opacity-75 font-normal">Follow-up date</span>
                            </button>
                        </div>

                        {{-- INLINE FORM 1: SUCCESS FORM --}}
                        <div x-show="activeAction === 'success'" x-cloak
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 -translate-y-2"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             class="rounded-xl bg-emerald-50/90 border border-emerald-300 p-3 space-y-3 shadow-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-[12px] font-bold text-emerald-950 flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    <span>Complete Service (Success)</span>
                                </span>
                                <button type="button" @click="cancelForm()" class="text-[11px] font-semibold text-emerald-800 hover:underline cursor-pointer">Close ✕</button>
                            </div>

                            {{-- MRP Field --}}
                            <div class="space-y-1">
                                <label class="block text-[10.5px] font-bold text-slate-700 uppercase tracking-wide">
                                    Service MRP (₹) <span class="text-emerald-600">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-[13px]">₹</span>
                                    <input type="number" step="0.01" min="0" x-model="successMrp"
                                           placeholder="Enter MRP amount..."
                                           class="w-full pl-7 pr-3 py-1.5 rounded-lg border border-slate-300 bg-white text-slate-900 text-[13px] font-bold outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 shadow-2xs">
                                </div>
                            </div>

                            {{-- Upload Screenshot --}}
                            <div class="space-y-1">
                                <label class="block text-[10.5px] font-bold text-slate-700 uppercase tracking-wide">
                                    Upload Screenshot / Work Proof
                                </label>
                                <div class="relative border border-dashed border-emerald-400 hover:border-emerald-600 rounded-lg p-2.5 bg-white text-center transition">
                                    <input type="file" name="screenshot" accept="image/*"
                                           @change="const file = $event.target.files[0]; if (file) { const reader = new FileReader(); reader.onload = (e) => screenshotPreview = e.target.result; reader.readAsDataURL(file); }"
                                           class="absolute inset-0 opacity-0 cursor-pointer w-full h-full z-10">
                                    <template x-if="!screenshotPreview">
                                        <div class="flex items-center justify-center gap-2 text-slate-600 py-1">
                                            <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            <span class="text-[11.5px] font-semibold">Choose screenshot or work proof</span>
                                        </div>
                                    </template>
                                    <template x-if="screenshotPreview">
                                        <div class="flex items-center gap-2">
                                            <img :src="screenshotPreview" class="w-10 h-10 object-cover rounded border border-slate-200">
                                            <div class="text-left text-[11px] font-bold text-emerald-700 flex-1 truncate">Screenshot attached ✓</div>
                                            <button type="button" @click.stop="screenshotPreview = null; $el.closest('div').parentElement.querySelector('input[type=file]').value = ''" class="text-rose-600 hover:underline text-[10.5px] font-semibold z-20">Remove</button>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            {{-- Note / Remarks --}}
                            <div class="space-y-1">
                                <label class="block text-[10.5px] font-bold text-slate-700 uppercase tracking-wide">
                                    Note / Remarks
                                </label>
                                <input type="text" x-model="successNote" placeholder="e.g. Completed car wash, customer satisfied..."
                                       class="w-full px-2.5 py-1.5 text-[12px] rounded-lg border border-slate-300 bg-white outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 shadow-2xs">
                            </div>

                            {{-- Submit & Cancel Buttons --}}
                            <div class="flex items-center justify-end gap-2 pt-1 border-t border-emerald-200/80">
                                <button type="button" @click="cancelForm()" :disabled="submitting"
                                        class="px-3.5 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 font-semibold text-[12px] transition cursor-pointer">
                                    Cancel
                                </button>
                                <button type="button" @click="submitSuccess()" :disabled="submitting"
                                        class="px-4 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[12px] transition shadow-xs flex items-center gap-1.5 disabled:opacity-60 cursor-pointer">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    <span x-text="submitting ? 'Submitting…' : 'Submit Success'"></span>
                                </button>
                            </div>
                        </div>

                        {{-- INLINE FORM 2: CANCEL FORM --}}
                        <div x-show="activeAction === 'cancel'" x-cloak
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 -translate-y-2"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             class="rounded-xl bg-rose-50/90 border border-rose-300 p-3 space-y-3 shadow-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-[12px] font-bold text-rose-950 flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-rose-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                    <span>Cancel Service</span>
                                </span>
                                <button type="button" @click="cancelForm()" class="text-[11px] font-semibold text-rose-800 hover:underline cursor-pointer">Close ✕</button>
                            </div>

                            <div class="space-y-1">
                                <label class="block text-[10.5px] font-bold text-slate-700 uppercase tracking-wide">
                                    Why Cancel / Reason <span class="text-rose-600">*</span>
                                </label>
                                <textarea x-model="cancelReason" rows="2" placeholder="e.g. Customer cancelled, vehicle sold, price dispute..."
                                          class="w-full px-2.5 py-1.5 text-[12px] rounded-lg border border-rose-300 bg-white outline-none focus:ring-2 focus:ring-rose-500 focus:border-rose-500 shadow-2xs resize-none"></textarea>
                            </div>

                            {{-- Submit & Cancel Buttons --}}
                            <div class="flex items-center justify-end gap-2 pt-1 border-t border-rose-200/80">
                                <button type="button" @click="cancelForm()" :disabled="submitting"
                                        class="px-3.5 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 font-semibold text-[12px] transition cursor-pointer">
                                    Cancel
                                </button>
                                <button type="button" @click="submitCancel()" :disabled="submitting"
                                        class="px-4 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-bold text-[12px] transition shadow-xs flex items-center gap-1.5 disabled:opacity-60 cursor-pointer">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                    <span x-text="submitting ? 'Cancelling…' : 'Submit Cancellation'"></span>
                                </button>
                            </div>
                        </div>

                        {{-- INLINE FORM 3: RESCHEDULE FORM --}}
                        <div x-show="activeAction === 'reschedule'" x-cloak
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 -translate-y-2"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             class="rounded-xl bg-amber-50/90 border border-amber-300 p-3 space-y-3 shadow-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-[12px] font-bold text-amber-950 flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <span>Reschedule Service (Follow Up)</span>
                                </span>
                                <button type="button" @click="cancelForm()" class="text-[11px] font-semibold text-amber-800 hover:underline cursor-pointer">Close ✕</button>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                {{-- Follow-up Date --}}
                                <div class="space-y-1">
                                    <label class="block text-[10.5px] font-bold text-slate-700 uppercase tracking-wide">
                                        Follow-up Date <span class="text-amber-600">*</span>
                                    </label>
                                    <div class="flex items-center rounded-lg border border-amber-300 bg-white overflow-hidden shadow-2xs">
                                        <button type="button"
                                                @click="const d = new Date(rescheduleDate || Date.now()); d.setDate(d.getDate() - 1); rescheduleDate = d.toISOString().split('T')[0];"
                                                class="px-2 py-1.5 bg-amber-100/70 hover:bg-amber-200 text-amber-900 font-bold text-xs border-r border-amber-200 transition">‹</button>
                                        <input type="date" x-model="rescheduleDate"
                                               class="flex-1 min-w-0 px-2 py-1 text-[12px] font-bold text-slate-800 outline-none">
                                        <button type="button"
                                                @click="const d = new Date(rescheduleDate || Date.now()); d.setDate(d.getDate() + 1); rescheduleDate = d.toISOString().split('T')[0];"
                                                class="px-2 py-1.5 bg-amber-100/70 hover:bg-amber-200 text-amber-900 font-bold text-xs border-l border-amber-200 transition">›</button>
                                    </div>
                                </div>

                                {{-- Follow-up Time --}}
                                <div class="space-y-1">
                                    <label class="block text-[10.5px] font-bold text-slate-700 uppercase tracking-wide">
                                        Time
                                    </label>
                                    <div class="flex items-center rounded-lg border border-amber-300 bg-white overflow-hidden shadow-2xs">
                                        <input type="time" x-model="rescheduleTime"
                                               class="w-full px-2 py-1 text-[12px] font-bold text-slate-800 outline-none">
                                    </div>
                                </div>
                            </div>

                            {{-- Note / Reason --}}
                            <div class="space-y-1">
                                <label class="block text-[10.5px] font-bold text-slate-700 uppercase tracking-wide">
                                    Note / Reason
                                </label>
                                <input type="text" x-model="rescheduleNote" placeholder="e.g. Customer requested evening slot..."
                                       class="w-full px-2.5 py-1.5 text-[12px] rounded-lg border border-slate-300 bg-white outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 shadow-2xs">
                            </div>

                            {{-- Submit & Cancel Buttons --}}
                            <div class="flex items-center justify-end gap-2 pt-1 border-t border-amber-200/80">
                                <button type="button" @click="cancelForm()" :disabled="submitting"
                                        class="px-3.5 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 font-semibold text-[12px] transition cursor-pointer">
                                    Cancel
                                </button>
                                <button type="button" @click="submitReschedule()" :disabled="submitting"
                                        class="px-4 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-bold text-[12px] transition shadow-xs flex items-center gap-1.5 disabled:opacity-60 cursor-pointer">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span x-text="submitting ? 'Rescheduling…' : 'Submit Reschedule'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            @else
                <form action="{{ route('customers.activities', $customer) }}"
                      enctype="multipart/form-data"
                      data-customer-name="{{ $customer->name }}"
                      data-customer-mrp="{{ $panelMrp !== null && $panelMrp !== '' ? $panelMrp : '' }}"
                      data-pipeline="{{ $customer->pipeline }}"
                      data-is-call-crm="{{ $customer->isCallCrm() ? '1' : '0' }}"
                      data-initial-status="{{ $customer->status }}"
                      data-initial-fu="{{ $customer->follow_up ? '1' : '0' }}"
                      data-initial-fu-date="{{ $customer->follow_up_at?->format('Y-m-d') }}"
                      data-initial-fu-time="{{ $customer->follow_up_has_time ? $customer->follow_up_at?->format('H:i') : '' }}"
                      class="h-full flex flex-col rounded-xl border border-indigo-200/90 bg-indigo-50/55 shadow-xs overflow-hidden"
                      onsubmit="event.preventDefault(); Alpine.$data(document.body).openPostUpdateConfirm(this)">
                    @csrf
                    <div class="h-8 shrink-0 px-2.5 flex items-center justify-between gap-1.5 bg-indigo-100/80 border-b border-indigo-200/80">
                        <div class="flex items-center gap-1.5 min-w-0">
                            <span class="w-4 h-4 rounded bg-indigo-600 text-white inline-flex items-center justify-center shrink-0">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </span>
                            <span class="text-[11.5px] font-bold text-indigo-950">Update Form</span>
                        </div>
                        @if($customer->isCallCrm() && $customer->bookingCopy)
                            <div class="inline-flex items-center gap-1.5">
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Assigned to Service</span>
                                </span>
                            </div>
                        @elseif($customer->isCrm())
                            @php
                                $convertPayload = [
                                    'id' => $customer->id,
                                    'name' => (string) $customer->name,
                                    'phone' => (string) ($customer->phone ?? ''),
                                    'source' => (string) ($customer->fieldValue('source') ?? $customer->source ?? ''),
                                ];
                            @endphp
                            <button type="button"
                                    data-convert-payload='@json($convertPayload)'
                                    onclick="Alpine.$data(document.body).openConvertModal(JSON.parse(this.dataset.convertPayload))"
                                    class="ml-auto inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold shadow-2xs transition">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                <span>Convert</span>
                            </button>
                        @endif
                    </div>

                    <div class="flex-1 flex flex-col p-2 space-y-1.5">
                        <input type="hidden" name="type" value="note">
                        @if($customer->isBooking())
                            @php
                                $curBookStatus = strtolower(trim((string) $customer->status));
                                $activeBookingState = match (true) {
                                    in_array($curBookStatus, ['success', 'completed', 'converted'], true) => 'success',
                                    in_array($curBookStatus, ['cancel', 'cancelled', 'canceled', 'lost'], true) => 'cancelled',
                                    default => 'pending',
                                };
                            @endphp
                            <input type="hidden" name="wash_action" value="0" id="bookingActionFlag-{{ $customer->id }}">
                            <div class="grid grid-cols-2 gap-1.5" x-data="{ bookState: '{{ $activeBookingState }}' }">
                                <button type="button"
                                        @click="bookState = 'success'; const f = $el.closest('form'); f.querySelector('[name=type]').value = 'status'; const wf = f.querySelector('[name=wash_action]'); if (wf) wf.value = '1'; const sel = f.querySelector('[name=status_change]'); if (sel) { if (!Array.from(sel.options).some(o => o.value === 'Success')) { sel.add(new Option('Success', 'Success')); } sel.value = 'Success'; } Alpine.$data(document.body).postActivity(f);"
                                        :class="bookState === 'success'
                                            ? 'border-emerald-600 bg-emerald-600 text-white ring-2 ring-emerald-600/25'
                                            : 'border-emerald-200 bg-emerald-50/80 text-emerald-800 hover:bg-emerald-100'"
                                        class="inline-flex items-center justify-center gap-1 rounded-md border px-2.5 py-1.5 text-[11px] font-bold transition shadow-2xs">
                                    <span>✓</span>
                                    <span>Success</span>
                                </button>

                                <button type="button"
                                        @click="bookState = 'cancelled'; const f = $el.closest('form'); f.querySelector('[name=type]').value = 'status'; const wf = f.querySelector('[name=wash_action]'); if (wf) wf.value = '1'; const sel = f.querySelector('[name=status_change]'); if (sel) { if (!Array.from(sel.options).some(o => o.value === 'Cancelled')) { sel.add(new Option('Cancelled', 'Cancelled')); } sel.value = 'Cancelled'; } Alpine.$data(document.body).postActivity(f);"
                                        :class="bookState === 'cancelled'
                                            ? 'border-rose-600 bg-rose-600 text-white ring-2 ring-rose-600/25'
                                            : 'border-rose-200 bg-rose-50/80 text-rose-800 hover:bg-rose-100'"
                                        class="inline-flex items-center justify-center gap-1 rounded-md border px-2.5 py-1.5 text-[11px] font-bold transition shadow-2xs">
                                    <span>✕</span>
                                    <span>Cancel</span>
                                </button>
                            </div>
                        @endif
                        {{-- Section 1: Interaction & Call Outcome (Quick Notes, Status, Title, Note, Follow-up) --}}
                        <div class="rounded-lg bg-violet-50/70 border border-violet-200/80 p-2 space-y-1.5">
                            @php $noteTemplates = $noteTemplates ?? collect(); @endphp
                            @if($noteTemplates->isNotEmpty())
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between gap-1">
                                        <span class="text-[9.5px] font-extrabold uppercase tracking-wider text-violet-700 flex items-center gap-1">
                                            <svg class="w-3 h-3 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                            <span>Quick Notes</span>
                                        </span>
                                        <span class="text-[9px] text-violet-500 font-medium">Click to set note & status</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 overflow-x-auto whitespace-nowrap crm-scroll py-0.5 max-w-full">
                                        @foreach($noteTemplates as $tpl)
                                            <button type="button"
                                                    title="{{ $tpl->body }}{{ $tpl->status ? ' [Sets status: ' . $tpl->status . ']' : '' }}"
                                                    data-tpl-title="{{ $tpl->title }}"
                                                    data-tpl-body="{{ $tpl->body }}"
                                                    data-tpl-status="{{ $tpl->status ?? '' }}"
                                                    onclick="applyNoteTemplate(this.closest('form'), this.dataset.tplTitle, this.dataset.tplBody, this.dataset.tplStatus)"
                                                    class="shrink-0 inline-flex items-center gap-1 rounded-full border border-violet-300 bg-white hover:border-violet-600 hover:bg-violet-600 hover:text-white px-2.5 py-0.5 text-[10.5px] font-semibold text-violet-900 shadow-2xs transition group cursor-pointer">
                                                <span>{{ $tpl->title }}</span>
                                                @if(!empty($tpl->status))
                                                    <span class="px-1.5 py-0.2 rounded-full text-[8.5px] font-bold bg-violet-100 text-violet-700 group-hover:bg-white group-hover:text-violet-800 border border-violet-200">
                                                        {{ $tpl->status }}
                                                    </span>
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="flex items-center gap-1.5">
                                <div class="relative shrink-0">
                                    <select name="status_change"
                                            data-cid="{{ $customer->id }}"
                                            onchange="handleStatusChangeFollowUp(this.dataset.cid, this.value)"
                                            class="h-7.5 appearance-none rounded-md border border-violet-300 bg-white hover:bg-white focus:bg-white focus:border-brand focus:ring-2 focus:ring-brand/20 pl-2.5 pr-6 text-[11.5px] font-semibold text-ink outline-none transition">
                                        <option value="">Status</option>
                                        @php $matchedExisting = false; @endphp
                                        @foreach($statusOptions as $s)
                                            @php
                                                $isMatch = strtolower(trim((string)$customer->status)) === strtolower(trim((string)$s));
                                                if ($isMatch) $matchedExisting = true;
                                            @endphp
                                            <option value="{{ $s }}" @selected($isMatch)>{{ ucfirst($s) }}</option>
                                        @endforeach
                                        @if(!empty($customer->status) && ! $matchedExisting)
                                            <option value="{{ $customer->status }}" selected>{{ ucfirst($customer->status) }}</option>
                                        @endif
                                    </select>
                                    <svg class="pointer-events-none absolute right-1.5 top-1/2 -translate-y-1/2 w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>

                                <input name="title" placeholder="Internal message / short title…"
                                       class="h-7.5 flex-1 min-w-0 rounded-md border border-violet-300 bg-white px-2.5 text-[11.5px] outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">
                            </div>

                            <textarea name="body" id="activityBody-{{ $customer->id }}" rows="2" placeholder="What happened / note…"
                                      @keydown.enter.exact="if(!$event.shiftKey) { $event.preventDefault(); $el.closest('form').requestSubmit(); }"
                                      class="w-full rounded-md border border-violet-300 bg-white px-2.5 py-1 text-[11.5px] leading-snug outline-none resize-y min-h-[46px]
                                             focus:bg-white focus:border-brand focus:ring-2 focus:ring-brand/20 transition"></textarea>

                            {{-- Follow-up controls right under note in the interaction block --}}
                            <div class="rounded-md border border-violet-200/90 bg-white/90 p-1.5 flex items-center justify-between gap-1.5 flex-wrap">
                                <div class="inline-flex flex-wrap items-center gap-1.5 max-w-full">
                                    <label class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[10.5px] font-bold cursor-pointer transition
                                                  has-[:checked]:bg-amber-100 has-[:checked]:text-amber-900 text-slate-700 hover:bg-slate-100">
                                        <input type="checkbox" name="follow_up" value="1" id="followUpCheck-{{ $customer->id }}"
                                               data-cid="{{ $customer->id }}"
                                               class="w-3.5 h-3.5 rounded border-line text-amber-600 focus:ring-amber-500/30"
                                               @checked($customer->follow_up)
                                               onchange="toggleFollowUpFields(this.dataset.cid, this.checked)">
                                        <span>Follow up</span>
                                    </label>
                                    <div id="followUpFields-{{ $customer->id }}"
                                         class="{{ $customer->follow_up ? 'flex' : 'hidden' }} items-center gap-1 flex-wrap"
                                         style="{{ $customer->follow_up ? 'display: flex;' : 'display: none;' }}">
                                        <div class="inline-flex items-center rounded border border-line bg-white overflow-hidden">
                                            <button type="button"
                                                    data-cid="{{ $customer->id }}"
                                                    onclick="shiftFollowUpDate(this.dataset.cid, -1)"
                                                    title="Previous day"
                                                    class="px-1.5 py-0.5 bg-mist/60 hover:bg-brand-soft hover:text-brand text-slate-700 font-bold text-[11px] border-r border-line transition">‹</button>
                                            <input type="date" name="follow_up_date" id="followUpDate-{{ $customer->id }}"
                                                   value="{{ $customer->follow_up && $customer->follow_up_at ? $customer->follow_up_at->format('Y-m-d') : ($customer->follow_up_at?->format('Y-m-d') ?: now()->addDay()->format('Y-m-d')) }}"
                                                   title="Follow-up date"
                                                   class="bg-white px-1.5 py-0.5 text-[10.5px] outline-none focus:bg-brand-soft/20">
                                            <button type="button"
                                                    data-cid="{{ $customer->id }}"
                                                    onclick="shiftFollowUpDate(this.dataset.cid, 1)"
                                                    title="Next day"
                                                    class="px-1.5 py-0.5 bg-mist/60 hover:bg-brand-soft hover:text-brand text-slate-700 font-bold text-[11px] border-l border-line transition">›</button>
                                        </div>
                                        <label class="inline-flex items-center gap-1 rounded px-1 py-0.5 text-[10.5px] font-semibold cursor-pointer transition
                                                      has-[:checked]:bg-brand-soft has-[:checked]:text-brand text-slate-700 hover:bg-slate-100">
                                            <input type="checkbox" name="follow_up_time_on" value="1" id="followUpTimeOn-{{ $customer->id }}"
                                                   data-cid="{{ $customer->id }}"
                                                   class="w-3.5 h-3.5 rounded border-line text-brand focus:ring-brand/30"
                                                   @checked($customer->follow_up && $customer->follow_up_has_time)
                                                   onchange="toggleFollowUpTime(this.dataset.cid, this.checked)">
                                            <span>Time</span>
                                        </label>
                                        <div id="followUpTimeWrap-{{ $customer->id }}"
                                             class="{{ ($customer->follow_up && $customer->follow_up_has_time) ? 'inline-flex' : 'hidden' }} items-center rounded border border-line bg-white overflow-hidden"
                                             style="{{ ($customer->follow_up && $customer->follow_up_has_time) ? 'display: inline-flex;' : 'display: none;' }}">
                                            <button type="button"
                                                    data-cid="{{ $customer->id }}"
                                                    onclick="shiftFollowUpTime(this.dataset.cid, -30)"
                                                    title="Previous 30 min"
                                                    class="px-1.5 py-0.5 bg-mist/60 hover:bg-brand-soft hover:text-brand text-slate-700 font-bold text-[11px] border-r border-line transition">‹</button>
                                            <input type="time" name="follow_up_time" id="followUpTime-{{ $customer->id }}"
                                                   value="{{ ($customer->follow_up && $customer->follow_up_has_time) ? $customer->follow_up_at?->format('H:i') : '' }}"
                                                   title="Follow-up time"
                                                   class="bg-white px-1.5 py-0.5 text-[10.5px] outline-none focus:bg-brand-soft/20">
                                            <button type="button"
                                                    data-cid="{{ $customer->id }}"
                                                    onclick="shiftFollowUpTime(this.dataset.cid, 30)"
                                                    title="Next 30 min"
                                                    class="px-1.5 py-0.5 bg-mist/60 hover:bg-brand-soft hover:text-brand text-slate-700 font-bold text-[11px] border-l border-line transition">›</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Section 2: Update Fields in a Clean 2-Column Grid (Soft Emerald Tint) --}}
                        @if($updateFields->isNotEmpty())
                            <div class="rounded-lg bg-emerald-50/50 p-2 grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                                @foreach($updateFields as $uf)
                                    @php
                                        $rawInitVal = $customer->fieldValue($uf->key);
                                        if (is_array($rawInitVal)) $rawInitVal = implode(', ', $rawInitVal);
                                        $ufVal = $rawInitVal;
                                        if (($ufVal === null || trim((string) $ufVal) === '') && $uf->type === 'select' && ($uf->auto_select ?? false) && !empty($uf->options)) {
                                            $ufVal = $uf->options[0];
                                        }
                                        if (($ufVal === null || trim((string) $ufVal) === '') && $uf->type === 'date') {
                                            $ufVal = now()->toDateString();
                                        }
                                    @endphp
                                    <div>
                                        <label class="block text-[9.5px] font-bold uppercase tracking-wider text-slate-500 mb-0.5">{{ $uf->label }}</label>
                                        @if($uf->type === 'select')
                                            <div class="relative">
                                                <select name="update_fields[{{ $uf->key }}]"
                                                        data-field-label="{{ $uf->label }}"
                                                        data-initial-value="{{ (string) $rawInitVal }}"
                                                        class="w-full appearance-none rounded-lg border border-line bg-mist/50 hover:bg-white focus:bg-white focus:border-brand focus:ring-2 focus:ring-brand/20 pl-2.5 pr-7 py-1.5 text-[12px] font-medium text-ink outline-none transition">
                                                    @unless(($uf->auto_select ?? false) && !empty($uf->options))
                                                        <option value="">Select {{ $uf->label }}</option>
                                                    @endunless
                                                    @foreach($uf->options ?? [] as $opt)
                                                        <option value="{{ $opt }}" @selected(strtolower(trim((string) $ufVal)) === strtolower(trim((string) $opt)))>{{ $opt }}</option>
                                                    @endforeach
                                                </select>
                                                <svg class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                            </div>
                                        @elseif($uf->type === 'phone')
                                            <input name="update_fields[{{ $uf->key }}]" value="{{ $ufVal }}"
                                                   data-field-label="{{ $uf->label }}"
                                                   data-initial-value="{{ (string) $rawInitVal }}"
                                                   type="tel" inputmode="tel" pattern="[0-9+\-\s()]*"
                                                   oninput="this.value = this.value.replace(/[^0-9+\-\s()]/g, '')"
                                                   placeholder="{{ $uf->label }}"
                                                   class="w-full rounded-lg border border-line bg-white px-2.5 py-1.5 text-[12px] outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">
                                        @elseif($uf->key === 'pincode' || $uf->key === 'pin')
                                            <div class="relative" x-data="pincodeAutocomplete({
                                                initialPin: '{{ addslashes($ufVal ?? '') }}',
                                                cid: {{ $customer->id }}
                                            })" @click.outside="open = false">
                                                <input name="update_fields[{{ $uf->key }}]"
                                                       type="text"
                                                       inputmode="numeric"
                                                       x-model="pin"
                                                       @input="onInput($event.target.value)"
                                                       @focus="open = true"
                                                       @keydown.arrow-down.prevent="selectNext()"
                                                       @keydown.arrow-up.prevent="selectPrev()"
                                                       @keydown.enter.prevent="selectActive()"
                                                       placeholder="PIN (e.g. 751015)"
                                                       autocomplete="off"
                                                       data-field-label="{{ $uf->label }}"
                                                       data-initial-value="{{ (string) $rawInitVal }}"
                                                       data-pincode-input="{{ $customer->id }}"
                                                       class="w-full rounded-lg border border-line bg-white px-2.5 py-1.5 text-[12px] font-medium text-ink outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">

                                                {{-- Suggestions Dropdown (PIN + Area) --}}
                                                <div x-show="open && filteredList.length > 0" x-cloak
                                                     class="absolute left-0 top-full mt-1 w-full min-w-[240px] max-w-[340px] max-h-52 overflow-y-auto crm-scroll rounded-xl border border-line bg-white shadow-xl z-50 divide-y divide-slate-100">
                                                    <template x-for="(item, idx) in filteredList" :key="idx">
                                                        <button type="button"
                                                                @click="choose(item)"
                                                                :class="activeIndex === idx ? 'bg-brand-soft text-brand' : 'hover:bg-slate-50 text-slate-700'"
                                                                class="w-full text-left px-3 py-2 flex items-center justify-between gap-2 text-[12px] transition">
                                                            <div class="min-w-0">
                                                                <span class="font-extrabold text-ink" x-text="item.pincode"></span>
                                                                <span class="text-slate-400 mx-1">·</span>
                                                                <span class="font-semibold text-slate-800" x-text="item.area || '—'"></span>
                                                                <template x-if="item.city">
                                                                    <span class="text-[10.5px] text-slate-400 ml-1" x-text="'(' + item.city + ')'"></span>
                                                                </template>
                                                            </div>
                                                            <span class="text-[10px] font-bold text-brand shrink-0">Select ↵</span>
                                                        </button>
                                                    </template>
                                                </div>
                                            </div>
                                        @elseif($uf->key === 'area' || $uf->key === 'ariya')
                                            <div class="relative" x-data="areaAutocomplete({
                                                initialArea: '{{ addslashes($ufVal ?? '') }}',
                                                cid: {{ $customer->id }}
                                            })" @click.outside="open = false">
                                                <input name="update_fields[{{ $uf->key }}]"
                                                       type="text"
                                                       x-model="area"
                                                       @input="onInput($event.target.value)"
                                                       @focus="open = true"
                                                       @keydown.arrow-down.prevent="selectNext()"
                                                       @keydown.arrow-up.prevent="selectPrev()"
                                                       @keydown.enter.prevent="selectActive()"
                                                       placeholder="Area name…"
                                                       autocomplete="off"
                                                       data-field-label="{{ $uf->label }}"
                                                       data-initial-value="{{ (string) $rawInitVal }}"
                                                       data-area-input="{{ $customer->id }}"
                                                       class="w-full rounded-lg border border-line bg-white px-2.5 py-1.5 text-[12px] font-medium text-ink outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">

                                                {{-- Suggestions Dropdown (Area + PIN) --}}
                                                <div x-show="open && filteredList.length > 0" x-cloak
                                                     class="absolute left-0 top-full mt-1 w-full min-w-[240px] max-w-[340px] max-h-52 overflow-y-auto crm-scroll rounded-xl border border-line bg-white shadow-xl z-50 divide-y divide-slate-100">
                                                    <template x-for="(item, idx) in filteredList" :key="idx">
                                                        <button type="button"
                                                                @click="choose(item)"
                                                                :class="activeIndex === idx ? 'bg-brand-soft text-brand' : 'hover:bg-slate-50 text-slate-700'"
                                                                class="w-full text-left px-3 py-2 flex items-center justify-between gap-2 text-[12px] transition">
                                                            <div class="min-w-0">
                                                                <span class="font-extrabold text-ink" x-text="item.area || '—'"></span>
                                                                <span class="text-slate-400 mx-1">·</span>
                                                                <span class="font-semibold text-slate-600" x-text="item.pincode"></span>
                                                                <template x-if="item.city">
                                                                    <span class="text-[10.5px] text-slate-400 ml-1" x-text="'(' + item.city + ')'"></span>
                                                                </template>
                                                            </div>
                                                            <span class="text-[10px] font-bold text-brand shrink-0">Select ↵</span>
                                                        </button>
                                                    </template>
                                                </div>
                                            </div>
                                        @elseif($uf->type === 'number')
                                            <input name="update_fields[{{ $uf->key }}]" value="{{ $ufVal }}"
                                                   data-field-label="{{ $uf->label }}"
                                                   data-initial-value="{{ (string) $rawInitVal }}"
                                                   type="number" step="any" inputmode="decimal"
                                                   oninput="this.value = this.value.replace(/[^0-9.\-]/g, '')"
                                                   placeholder="{{ $uf->label }}"
                                                   class="w-full rounded-lg border border-line bg-white px-2.5 py-1.5 text-[12px] outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">
                                        @elseif($uf->type === 'date')
                                            <div class="flex items-center gap-1">
                                                <button type="button"
                                                        onclick="shiftInputDate(this.nextElementSibling, -1)"
                                                        title="Previous date"
                                                        class="h-8 px-2.5 inline-flex items-center justify-center rounded-lg border border-line bg-mist/60 hover:bg-brand-soft hover:text-brand text-slate-700 font-bold text-xs transition shrink-0">‹</button>
                                                <input name="update_fields[{{ $uf->key }}]" value="{{ $ufVal }}"
                                                       data-field-label="{{ $uf->label }}"
                                                       data-initial-value="{{ (string) $rawInitVal }}"
                                                       type="date"
                                                       class="flex-1 min-w-0 rounded-lg border border-line bg-white px-2.5 py-1.5 text-[12px] outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">
                                                <button type="button"
                                                        onclick="shiftInputDate(this.previousElementSibling, 1)"
                                                        title="Next date"
                                                        class="h-8 px-2.5 inline-flex items-center justify-center rounded-lg border border-line bg-mist/60 hover:bg-brand-soft hover:text-brand text-slate-700 font-bold text-xs transition shrink-0">›</button>
                                            </div>
                                        @elseif($uf->type === 'time')
                                            <div class="flex items-center gap-1">
                                                <button type="button"
                                                        onclick="shiftInputTime(this.nextElementSibling, -30)"
                                                        title="Previous 30 min"
                                                        class="h-8 px-2.5 inline-flex items-center justify-center rounded-lg border border-line bg-mist/60 hover:bg-brand-soft hover:text-brand text-slate-700 font-bold text-xs transition shrink-0">‹</button>
                                                <input name="update_fields[{{ $uf->key }}]" value="{{ $ufVal }}"
                                                       data-field-label="{{ $uf->label }}"
                                                       data-initial-value="{{ (string) $rawInitVal }}"
                                                       type="time"
                                                       class="flex-1 min-w-0 rounded-lg border border-line bg-white px-2.5 py-1.5 text-[12px] outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">
                                                <button type="button"
                                                        onclick="shiftInputTime(this.previousElementSibling, 30)"
                                                        title="Next 30 min"
                                                        class="h-8 px-2.5 inline-flex items-center justify-center rounded-lg border border-line bg-mist/60 hover:bg-brand-soft hover:text-brand text-slate-700 font-bold text-xs transition shrink-0">›</button>
                                            </div>
                                        @else
                                            <input name="update_fields[{{ $uf->key }}]" value="{{ $ufVal }}"
                                                   data-field-label="{{ $uf->label }}"
                                                   data-initial-value="{{ (string) $rawInitVal }}"
                                                   type="{{ $uf->type === 'email' ? 'email' : 'text' }}"
                                                   placeholder="{{ $uf->label }}"
                                                   class="w-full rounded-lg border border-line bg-white px-2.5 py-1.5 text-[12px] outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- Section: Products Multi-Select Section (Call CRM & Assign Service only, NOT CRM or Team Ops) --}}
                        @php
                            $showProductSelector = $customer->isCallCrm() || $customer->isBooking();
                            $availableProducts = $showProductSelector
                                ? \App\Models\Product::active()->ordered()->get()
                                : collect();
                            $assignedBookingProductIds = $showProductSelector ? $customer->assignedBookingProductIds() : [];
                            $initialSelectedProductIds = $customer->isBooking()
                                ? $assignedBookingProductIds
                                : [];
                            $availableProductsForJs = $availableProducts->map(fn ($p) => [
                                'id' => (int) $p->id,
                                'name' => $p->name,
                                'code' => $p->code ?? '',
                                'price' => (float) $p->price,
                                'formatted_price' => $p->formattedPrice(),
                                'category' => $p->category ?? '',
                                'already_assigned' => in_array((int) $p->id, $assignedBookingProductIds, true),
                            ])->values();
                        @endphp
                        @if($showProductSelector && $availableProducts->isNotEmpty())
                            <div class="rounded-lg border border-cyan-200/80 bg-cyan-50/50 p-1.5 space-y-1"
                                 data-assigned-product-ids='@json($assignedBookingProductIds)'
                                 x-data="{
                                     assignedIds: @js($assignedBookingProductIds),
                                     products: @js($availableProductsForJs),
                                     selected: @js($initialSelectedProductIds),
                                     toggle(id) {
                                         id = Number(id);
                                         if (this.selected.includes(id)) {
                                             this.selected = this.selected.filter(x => x !== id);
                                         } else {
                                             this.selected.push(id);
                                         }
                                     }
                                 }">
                                <div class="flex items-center justify-between gap-1.5 px-0.5">
                                    <span class="text-[9.5px] font-bold uppercase tracking-wider text-cyan-950">Select Products</span>
                                    <span class="text-[9px] font-bold px-1.5 py-0.2 rounded-full bg-cyan-200/80 text-cyan-900"
                                          x-text="selected.length + ' selected'"></span>
                                </div>

                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-1 max-h-36 overflow-y-auto crm-scroll p-0.5">
                                    <template x-for="p in products" :key="p.id">
                                        <label class="relative flex items-center gap-1.5 px-2 py-1 rounded-md border cursor-pointer select-none transition min-w-0"
                                               :class="selected.includes(p.id)
                                                    ? 'border-cyan-500 bg-cyan-100/90 text-cyan-950 shadow-2xs ring-1 ring-cyan-500/20'
                                                    : 'border-slate-200/90 bg-white hover:border-cyan-300 hover:bg-cyan-50/30 text-slate-700'">
                                            <input type="checkbox"
                                                   name="product_ids[]"
                                                   :value="p.id"
                                                   :data-product-label="p.name + (p.code ? ' (#' + p.code + ')' : '')"
                                                   :data-already-assigned="p.already_assigned ? '1' : '0'"
                                                   :checked="selected.includes(p.id)"
                                                   @change="toggle(p.id)"
                                                   class="w-3.5 h-3.5 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500/20 shrink-0">
                                            <div class="min-w-0 flex-1 flex items-center justify-between gap-1">
                                                <span class="font-bold text-[11px] truncate" x-text="p.name"></span>
                                                <template x-if="p.price > 0">
                                                    <span class="font-extrabold text-[9.5px] text-emerald-700 shrink-0" x-text="p.formatted_price"></span>
                                                </template>
                                                @if($customer->isCallCrm())
                                                    <template x-if="p.already_assigned">
                                                        <span class="shrink-0 px-1 py-0.2 rounded bg-emerald-100 text-emerald-800 text-[8.5px] font-bold" title="Already assigned to Service">✓</span>
                                                    </template>
                                                @endif
                                            </div>
                                        </label>
                                    </template>
                                </div>
                            </div>
                        @endif

                        {{-- Section 4: Action Footer --}}
                        <div class="mt-auto pt-1.5 flex items-center justify-end">
                            <button type="submit"
                                    class="h-9 inline-flex items-center justify-center gap-2 rounded-xl bg-brand hover:bg-brand-dark text-white text-[13px] font-bold px-5 shadow-sm shadow-brand/20 transition cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                <span>Post Update</span>
                            </button>
                        </div>
                    </div>
                </form>
            @endif
        </div>

        @if($hasBookingsCol)
            @php
                $lifetimeSellingTotal = $allCustomerBookings->sum(fn ($b) => (float) ($b->selling_price ?? 0));
                $lifetimeMrpTotal = $allCustomerBookings->sum(fn ($b) => (float) ($b->mrp ?? 0));
            @endphp
            {{-- RIGHT COLUMN: Compact Booking List Card + Lifetime Total --}}
            <div class="lg:col-span-3 h-full flex flex-col rounded-xl border border-indigo-200/90 bg-indigo-50/50 shadow-xs overflow-hidden">
                <div class="h-8 shrink-0 px-2.5 bg-indigo-100/80 border-b border-indigo-200/80 flex items-center justify-between gap-1.5">
                    <div class="flex items-center gap-1.5 min-w-0">
                        <span class="w-4 h-4 rounded bg-indigo-600 text-white inline-flex items-center justify-center shrink-0 text-[10px]">📅</span>
                        <span class="text-[11.5px] font-bold text-indigo-950 truncate">Booking List</span>
                    </div>
                    <span class="text-[10px] font-bold px-1.5 py-0.2 rounded-full bg-white text-indigo-800 border border-indigo-200 shrink-0">
                        {{ $allCustomerBookings->count() }}
                    </span>
                </div>

                <div class="p-2 space-y-1.5 flex-1 overflow-y-auto crm-scroll max-h-[480px]">
                    @foreach($allCustomerBookings as $bItem)
                        @php
                            $bTime = $bItem->label ?: \App\Models\BookingSlot::formatTimeLabel((string) $bItem->start_time, (string) $bItem->end_time);
                            $bCar = $bItem->car_model ?: $panelCarModel;
                            $bBadgeClass = match ($bItem->status) {
                                'success' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                'cancelled' => 'bg-rose-100 text-rose-800 border-rose-200',
                                default => 'bg-amber-100 text-amber-800 border-amber-200',
                            };
                            $bEditPayload = [
                                'id' => $bItem->id,
                                'date' => $bItem->slot_date?->format('d M Y'),
                                'time' => $bTime,
                                'car_model' => $bCar ?? '',
                                'mrp' => $bItem->mrp !== null ? (string) $bItem->mrp : '',
                                'selling_price' => $bItem->selling_price !== null ? (string) $bItem->selling_price : '',
                            ];
                        @endphp
                        <div class="rounded-lg bg-white border border-indigo-100/90 px-2 py-1.5 text-[10.5px] space-y-1 shadow-2xs">
                            <div class="flex items-start justify-between gap-1">
                                <div class="font-bold text-ink leading-tight">
                                    <div>{{ $bItem->slot_date?->format('d M Y') }}</div>
                                    <div class="text-[10px] font-semibold text-indigo-700">{{ $bTime }}</div>
                                </div>
                                <div class="flex items-center gap-1 shrink-0">
                                    <span class="px-1.5 py-0.2 rounded border text-[9px] font-bold uppercase {{ $bBadgeClass }}">
                                        {{ $bItem->status }}
                                    </span>
                                    <button type="button"
                                            title="Edit Car Model, MRP & Business Selling Price"
                                            data-booking-edit='@json($bEditPayload)'
                                            onclick="Alpine.$data(document.body).openEditBookingModal(JSON.parse(this.dataset.bookingEdit))"
                                            class="w-5 h-5 rounded border border-indigo-200 bg-indigo-50/70 hover:bg-indigo-600 hover:text-white text-indigo-700 inline-flex items-center justify-center transition">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                </div>
                            </div>
                            @if(!empty($bCar))
                                <div class="text-[10px] font-semibold text-teal-800 truncate" title="{{ $bCar }}">
                                    🚗 {{ $bCar }}
                                </div>
                            @endif
                            @if($bItem->mrp !== null || $bItem->selling_price !== null)
                                <div class="flex flex-wrap items-center justify-between gap-1 pt-0.5 border-t border-slate-100 text-[10px]">
                                    @if($bItem->selling_price !== null)
                                        <span class="text-emerald-700 font-semibold">Business Selling Price: <strong class="text-emerald-800">₹{{ number_format((float) $bItem->selling_price, 0) }}</strong></span>
                                    @endif
                                    @if($bItem->mrp !== null)
                                        <span class="ml-auto text-slate-800 font-extrabold text-[10.5px]">MRP: ₹{{ number_format((float) $bItem->mrp, 0) }}</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Lifetime Total Amount Banner at Bottom (MRP primary & bold on right, Business Selling Price below) --}}
                <div class="shrink-0 mx-2 mb-2 mt-auto rounded-lg bg-gradient-to-r from-emerald-600 to-teal-600 text-white px-2.5 py-2 shadow-xs">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[9.5px] font-bold uppercase tracking-wider text-emerald-100">Lifetime Total (MRP)</span>
                        <span class="text-[14px] font-extrabold leading-none">₹{{ number_format($lifetimeMrpTotal, 2) }}</span>
                    </div>
                    <div class="mt-1 pt-1 border-t border-white/20 flex items-center justify-between text-[10px] text-emerald-100">
                        <span>Business Selling Price</span>
                        <span class="font-bold text-white">₹{{ number_format($lifetimeSellingTotal, 2) }}</span>
                    </div>
                </div>
            </div>
        @elseif($isCallCrmPanel || $isBookingPanel || $hasServicesCol)
            @php
                $callCrmLifetimeTotal = $allCallCrmServices->sum(function ($s) {
                    $m = $s->fieldValue('mrp');
                    return ($m !== null && $m !== '') ? (float) $m : (float) $s->products->sum(fn ($p) => (float) ($p->price ?? 0));
                });
            @endphp
            {{-- RIGHT COLUMN: Customer All Bookings / Assigned Services List + Lifetime Total --}}
            <div class="lg:col-span-3 h-full flex flex-col rounded-xl border border-indigo-200/90 bg-indigo-50/50 shadow-xs overflow-hidden">
                <div class="h-8 shrink-0 px-2.5 bg-indigo-100/80 border-b border-indigo-200/80 flex items-center justify-between gap-1.5">
                    <div class="flex items-center gap-1.5 min-w-0">
                        <span class="w-4 h-4 rounded bg-indigo-600 text-white inline-flex items-center justify-center shrink-0 text-[10px]">📅</span>
                        <span class="text-[11.5px] font-bold text-indigo-950 truncate">Customer Bookings</span>
                    </div>
                    <span class="text-[10px] font-bold px-1.5 py-0.2 rounded-full bg-white text-indigo-800 border border-indigo-200 shrink-0">
                        {{ $allCallCrmServices->count() }}
                    </span>
                </div>

                <div class="p-2 space-y-1.5 flex-1 overflow-y-auto crm-scroll max-h-[480px]">
                    @forelse($allCallCrmServices as $svc)
                        @php
                            $isCurrentSvc = ($svc->id === $customer->id);
                            $svcDate = $svc->follow_up_at
                                ? $svc->follow_up_at->format('d M Y')
                                : ($svc->created_at ? $svc->created_at->format('d M Y') : '—');
                            $svcTime = ($svc->follow_up_has_time && $svc->follow_up_at) ? $svc->follow_up_at->format('h:i A') : '';
                            $svcTotal = $svc->products->sum(fn ($p) => (float) ($p->price ?? 0));
                            $svcBadgeClass = match (strtolower((string) $svc->status)) {
                                'success', 'completed', 'done' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                'cancelled', 'cancel', 'lost' => 'bg-rose-100 text-rose-800 border-rose-200',
                                'rescheduled' => 'bg-amber-100 text-amber-800 border-amber-200',
                                'engaged', 'booked' => 'bg-purple-100 text-purple-800 border-purple-200',
                                default => 'bg-sky-100 text-sky-800 border-sky-200',
                            };
                        @endphp
                        <div class="rounded-lg {{ $isCurrentSvc ? 'bg-indigo-50/90 border-2 border-indigo-500 shadow-sm' : 'bg-white border border-indigo-100/90 hover:border-indigo-300' }} px-2 py-1.5 text-[10.5px] space-y-1 shadow-2xs transition">
                            <div class="flex items-center justify-between gap-1">
                                <div class="font-bold text-ink leading-tight flex items-center gap-1 min-w-0">
                                    <span class="text-indigo-700 shrink-0">#{{ $svc->id }}</span>
                                    <span class="text-slate-600 font-semibold truncate">{{ $svcDate }}</span>
                                    @if($svcTime)
                                        <span class="text-[9.5px] font-bold text-indigo-600 shrink-0">({{ $svcTime }})</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1 shrink-0">
                                    <span class="px-1.5 py-0.2 rounded border text-[9px] font-bold uppercase {{ $svcBadgeClass }}">
                                        {{ $svc->status ?: 'Pending' }}
                                    </span>
                                    @if($isCurrentSvc)
                                        <span class="text-[9px] font-extrabold text-indigo-700 bg-white px-1.5 py-0.2 rounded border border-indigo-200">
                                            Active
                                        </span>
                                    @else
                                        <button type="button"
                                                onclick="selectCustomer({{ $svc->id }})"
                                                class="px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-indigo-100 hover:bg-brand hover:text-white text-indigo-800 border border-indigo-200 transition"
                                                title="View this booking">
                                            View →
                                        </button>
                                    @endif
                                </div>
                            </div>
                            @if($svc->products->isNotEmpty())
                                <div class="flex flex-wrap gap-1 pt-0.5">
                                    @foreach($svc->products as $sp)
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded bg-cyan-50 text-cyan-900 border border-cyan-200 text-[9.5px] font-semibold">
                                            <span>{{ $sp->name }}</span>
                                            @if((float) ($sp->price ?? 0) > 0)
                                                <span class="font-bold text-emerald-700">₹{{ number_format((float) $sp->price, 0) }}</span>
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                            @php
                                $svcMrp = $svc->fieldValue('mrp');
                            @endphp
                            <div class="flex items-center justify-between pt-0.5 border-t border-slate-100 text-[10px]">
                                <span class="text-slate-500">Service MRP</span>
                                <span class="font-extrabold text-emerald-700">
                                    @if($svcMrp !== null && $svcMrp !== '')
                                        ₹{{ number_format((float) $svcMrp, 2) }}
                                    @else
                                        ₹{{ number_format($svcTotal, 2) }}
                                    @endif
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-indigo-200 bg-white/70 p-4 text-center text-[11px] text-slate-500">
                            No services assigned yet.
                        </div>
                    @endforelse
                </div>

                <div class="shrink-0 mx-2 mb-2 mt-auto rounded-lg bg-gradient-to-r from-emerald-600 to-teal-600 text-white px-2.5 py-2 shadow-xs">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[9.5px] font-bold uppercase tracking-wider text-emerald-100">Total Bookings MRP</span>
                        <span class="text-[14px] font-extrabold leading-none">₹{{ number_format($callCrmLifetimeTotal, 2) }}</span>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Full-Width Bottom Section: 2 Recent Messages + View More Button (Soft Rose/Warm Slate Tint) --}}
    <div class="w-full rounded-xl border border-rose-200/80 bg-rose-50/55 shadow-xs overflow-hidden">
        <div class="px-3.5 py-2.5 bg-rose-100/75 border-b border-rose-200/80 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                <span class="text-[12.5px] font-bold text-rose-950">Recent Messages & Updates</span>
                <span class="px-2 py-0.5 rounded-full bg-white/90 text-rose-700 text-[10.5px] font-bold">{{ $activitiesCount }}</span>
            </div>
        </div>

        <div class="divide-y divide-line">
            @forelse($activitiesList as $a)
                <div @if($loop->index >= 2) data-more-msg="{{ $customer->id }}" @endif
                     class="{{ $loop->index >= 2 ? 'hidden ' : '' }}px-3.5 py-2.5 hover:bg-mist/40">
                    <div class="flex items-center gap-1.5 mb-0.5 flex-wrap">
                        <span class="inline-flex px-1.5 py-0.2 rounded text-[9.5px] font-bold uppercase {{ $typeColor[$a->type] ?? 'bg-mist text-slate-500' }}">{{ $a->type }}</span>
                        @if($customer->isBooking() && $customer->source_customer_id && (int) $a->customer_id === (int) $customer->source_customer_id)
                            <span class="inline-flex px-1.5 py-0.2 rounded text-[9.5px] font-bold uppercase bg-blue-100 text-blue-800 border border-blue-200">
                                Call CRM History
                            </span>
                        @elseif($customer->isCallCrm() && (int) $a->customer_id !== (int) $customer->id)
                            <span class="inline-flex px-1.5 py-0.2 rounded text-[9.5px] font-bold uppercase bg-indigo-100 text-indigo-800 border border-indigo-200">
                                Service #{{ $a->customer_id }}
                            </span>
                        @endif
                        @if(!empty($a->meta['status_to']))
                            <span class="inline-flex px-1.5 py-0.2 rounded text-[9.5px] font-bold uppercase bg-rose-100 text-rose-700">
                                {{ $a->meta['status_to'] }}
                            </span>
                        @endif
                        @if(!empty($a->meta['field_changes']) && is_array($a->meta['field_changes']))
                            @foreach($a->meta['field_changes'] as $fLabel => $fChg)
                                @php
                                    $toVal = trim((string) ($fChg['to'] ?? ''));
                                    $toIsUrl = (bool) filter_var($toVal, FILTER_VALIDATE_URL) || (bool) preg_match('~^https?://~i', $toVal);
                                @endphp
                                @if($toIsUrl)
                                    <a href="{{ $toVal }}" target="_blank" rel="noopener noreferrer"
                                       class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-sky-100 text-sky-800 hover:bg-sky-200 transition">
                                        <span>📍 {{ $fLabel }}: Open Map URL ↗</span>
                                    </a>
                                @else
                                    <span class="inline-flex px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-sky-100 text-sky-800">
                                        {{ $fLabel }}: {{ $toVal }}
                                    </span>
                                @endif
                            @endforeach
                        @endif
                        @if(!empty($a->meta['slot_date']) && !empty($a->meta['slot_time']))
                            <span class="inline-flex px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-indigo-100 text-indigo-800">
                                📅 {{ \Carbon\Carbon::parse($a->meta['slot_date'])->format('d M Y') }} · {{ $a->meta['slot_time'] }}
                            </span>
                        @endif
                        @if(!empty($a->meta['follow_up_at']))
                            <span class="inline-flex px-1.5 py-0.2 rounded text-[9.5px] font-bold uppercase bg-amber-100 text-amber-800">
                                Follow-up {{ \Carbon\Carbon::parse($a->meta['follow_up_at'])->format('d M Y') }}
                                @if(!empty($a->meta['follow_up_has_time']))
                                    · {{ \Carbon\Carbon::parse($a->meta['follow_up_at'])->format('h:i A') }}
                                @else
                                    · Anytime
                                @endif
                            </span>
                        @endif
                        @if(!empty($a->meta['products']) && is_array($a->meta['products']))
                            @foreach($a->meta['products'] as $ap)
                                @php
                                    $pName = is_array($ap) ? ($ap['name'] ?? '') : (string) $ap;
                                @endphp
                                @if($pName)
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-cyan-100 text-cyan-800 border border-cyan-200">
                                        📦 {{ $pName }}
                                    </span>
                                @endif
                            @endforeach
                        @endif
                        <span class="text-[11.5px] font-semibold">{{ $a->title }}</span>
                        <span class="ml-auto text-[10.5px] text-slate-400">{{ $a->created_at?->diffForHumans() }}</span>
                    </div>
                    <div class="text-[12px] text-slate-700 whitespace-pre-wrap leading-snug">{{ $a->body }}</div>
                    @php
                        $timelineImages = $a->meta['images'] ?? [];
                    @endphp
                    @if(!empty($timelineImages) && is_array($timelineImages))
                        <div class="mt-1.5 flex flex-wrap gap-2">
                            @foreach($timelineImages as $img)
                                @php
                                    $imgUrl = is_array($img) ? ($img['url'] ?? '') : (string) $img;
                                    $imgName = is_array($img) ? ($img['name'] ?? 'Image') : 'Image';
                                @endphp
                                @if($imgUrl !== '')
                                    <a href="{{ $imgUrl }}" target="_blank" rel="noopener noreferrer"
                                       class="group relative block w-20 h-20 rounded-lg border border-line overflow-hidden bg-mist shadow-2xs hover:border-brand transition"
                                       title="Click to view full image ({{ $imgName }})">
                                        <img src="{{ $imgUrl }}" alt="{{ $imgName }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-200">
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    @endif
                    <div class="text-[10px] text-slate-400 mt-0.5">{{ $a->user?->name ?: 'System' }}</div>
                </div>
            @empty
                <div class="px-3 py-5 text-center text-slate-400 text-xs">No timeline yet</div>
            @endforelse
        </div>

        @if($activitiesCount > 2)
            <div class="p-2.5 border-t border-line bg-mist/30">
                <button type="button"
                        data-cid="{{ $customer->id }}"
                        data-more-count="{{ $activitiesCount - 2 }}"
                        onclick="toggleMoreCustomerMessages(this)"
                        class="w-full h-9 inline-flex items-center justify-center gap-1.5 rounded-lg border border-brand/30 bg-brand-soft/70 hover:bg-brand hover:text-white text-brand text-[12px] font-bold transition shadow-2xs">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    <span data-view-more-label>View More ({{ $activitiesCount - 2 }} more)</span>
                </button>
            </div>
        @endif
    </div>
</div>
