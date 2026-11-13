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
    $activitiesCount = $customer->activities->count();
@endphp

<div class="p-3 sm:p-4 space-y-3 w-full">
    {{-- Main 2-Column Layout: Left = Compact Customer Details (4 cols) | Right = Update Form (8 cols) --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-2.5 items-stretch">

        {{-- LEFT COLUMN: Compact Customer Details Card (4 cols on lg) - Soft Sky Blue Theme --}}
        <div class="lg:col-span-4 h-full flex flex-col rounded-xl border border-sky-200/90 bg-sky-50/70 shadow-xs overflow-hidden">
            {{-- Card Header --}}
            <div class="h-9 shrink-0 px-3 bg-sky-100/80 border-b border-sky-200/80 flex items-center justify-between gap-2">
                <div class="flex items-center gap-1.5">
                    <span class="w-5 h-5 rounded bg-sky-600 text-white inline-flex items-center justify-center shrink-0">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </span>
                    <span class="text-[12px] font-bold text-sky-950">Customer Details</span>
                </div>
                <span class="text-[10px] font-bold px-1.5 py-0.2 rounded capitalize bg-white/90 text-sky-800 shrink-0">
                    {{ $customer->status }}
                </span>
            </div>

            <div class="p-2.5 space-y-2 flex-1 flex flex-col">
                {{-- Profile Summary --}}
                <div class="flex items-start gap-2 rounded-lg bg-white/80 p-2">
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
                    @unless($customer->isOperations() || $customer->isCallCrm())
                        @if($customer->operationCopy)
                            <span title="Assigned to Operation Team"
                                  class="h-7 inline-flex items-center justify-center px-2 rounded-md bg-emerald-100/90 text-emerald-800 text-[10px] font-bold whitespace-nowrap">
                                Assigned to OPS
                            </span>
                        @else
                            <button type="button" title="Assign to Operation Team" data-cid="{{ $customer->id }}" onclick="assignToOperations(this.dataset.cid)"
                                    class="h-7 inline-flex items-center justify-center px-2 rounded-md bg-ink text-white text-[10px] font-bold hover:bg-slate-800 transition whitespace-nowrap">
                                Assign to OPS
                            </button>
                        @endif
                    @endunless
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
                    <div class="pt-1.5 border-t border-sky-200/60 grid grid-cols-2 gap-x-2 gap-y-1.5 text-[11px] leading-tight break-words">
                        @if($createdDateVal)
                            <div class="rounded-md bg-white/90 border border-sky-200/70 px-2 py-1.5">
                                <div class="text-slate-500 text-[9.5px] font-semibold uppercase tracking-wide">Created Date</div>
                                <div class="font-semibold text-ink">{{ $createdDateVal }}</div>
                            </div>
                        @endif
                        @if($actionDateVal)
                            <div class="rounded-md {{ $customer->follow_up ? 'bg-amber-100/80 border border-amber-200/80' : 'bg-emerald-100/80 border border-emerald-200/80' }} px-2 py-1.5">
                                <div class="text-slate-600 text-[9.5px] font-semibold uppercase tracking-wide">{{ $actionDateLabel }}</div>
                                <div class="font-bold {{ $customer->follow_up ? 'text-amber-900' : 'text-emerald-900' }}">{{ $actionDateVal }}</div>
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
                            <div class="rounded-md {{ $tileBg }} px-2 py-1.5">
                                <div class="text-slate-500 text-[9.5px] font-semibold uppercase tracking-wide">{{ $field->label }}</div>
                                @if($isMapField || $isUrlVal)
                                    @php
                                        $mapHref = $isUrlVal
                                            ? $strVal
                                            : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($strVal);
                                    @endphp
                                    <a href="{{ $mapHref }}" target="_blank" rel="noopener noreferrer"
                                       class="mt-0.5 inline-flex items-center gap-1 rounded bg-brand-soft hover:bg-brand hover:text-white text-brand px-1.5 py-0.5 text-[10px] font-bold transition">
                                        <svg class="w-2.5 h-2.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        <span>{{ $isMapField ? 'Open Map URL' : 'Open URL' }}</span>
                                    </a>
                                @else
                                    <div class="font-semibold text-ink">{{ $strVal }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- RIGHT COLUMN: Update Form Card (8 cols on lg) --}}
        <div class="lg:col-span-8 flex flex-col">
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
            @else
                <form action="{{ route('customers.activities', $customer) }}"
                      enctype="multipart/form-data"
                      data-customer-name="{{ $customer->name }}"
                      data-initial-status="{{ $customer->status }}"
                      data-initial-fu="{{ $customer->follow_up ? '1' : '0' }}"
                      data-initial-fu-date="{{ $customer->follow_up_at?->format('Y-m-d') }}"
                      data-initial-fu-time="{{ $customer->follow_up_has_time ? $customer->follow_up_at?->format('H:i') : '' }}"
                      class="h-full flex flex-col rounded-xl border border-indigo-200/90 bg-indigo-50/55 shadow-xs overflow-hidden"
                      onsubmit="event.preventDefault(); Alpine.$data(document.body).openPostUpdateConfirm(this)">
                    @csrf
                    <div class="h-9 shrink-0 px-3 flex items-center justify-between gap-2 bg-indigo-100/80 border-b border-indigo-200/80">
                        <div class="flex items-center gap-1.5 min-w-0">
                            <span class="w-5 h-5 rounded bg-indigo-600 text-white inline-flex items-center justify-center shrink-0">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </span>
                            <span class="text-[12px] font-bold text-indigo-950">Update Form</span>
                        </div>
                    </div>

                    <div class="flex-1 flex flex-col p-3 space-y-2.5">
                        <input type="hidden" name="type" value="note">
                        {{-- Section 1: Status + Internal Message + Note (Soft Violet Tint) --}}
                        <div class="rounded-lg bg-violet-50/60 p-2.5 space-y-2">
                            <div class="flex items-center gap-2">
                                <div class="relative shrink-0">
                                    <select name="status_change"
                                            data-cid="{{ $customer->id }}"
                                            onchange="handleStatusChangeFollowUp(this.dataset.cid, this.value)"
                                            class="h-8 appearance-none rounded-lg border border-violet-200 bg-white hover:bg-white focus:bg-white focus:border-brand focus:ring-2 focus:ring-brand/20 pl-2.5 pr-7 text-[12px] font-medium text-ink outline-none transition">
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
                                    <svg class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>

                                <input name="title" placeholder="Internal message / short title…"
                                       class="h-8 flex-1 min-w-0 rounded-lg border border-violet-200 bg-white px-3 text-[12px] outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">
                            </div>

                            <textarea name="body" id="activityBody-{{ $customer->id }}" rows="2" placeholder="What happened / note…"
                                      @keydown.enter.exact="if(!$event.shiftKey) { $event.preventDefault(); $el.closest('form').requestSubmit(); }"
                                      class="w-full rounded-lg border border-violet-200 bg-white px-3 py-2 text-[12px] leading-snug outline-none resize-y min-h-[60px]
                                             focus:bg-white focus:border-brand focus:ring-2 focus:ring-brand/20 transition"></textarea>

                            @php $noteTemplates = $noteTemplates ?? collect(); @endphp
                            @if($noteTemplates->isNotEmpty())
                                <div class="flex flex-wrap items-center gap-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wide text-violet-500 mr-1">Quick notes:</span>
                                    @foreach($noteTemplates as $tpl)
                                        <button type="button"
                                                title="{{ $tpl->body }}"
                                                data-tpl-title="{{ $tpl->title }}"
                                                data-tpl-body="{{ $tpl->body }}"
                                                onclick="applyNoteTemplate(this.closest('form'), this.dataset.tplTitle, this.dataset.tplBody)"
                                                class="inline-flex items-center max-w-full rounded-full border border-violet-200 bg-white hover:border-brand hover:bg-brand-soft hover:text-brand px-2.5 py-0.5 text-[10.5px] font-semibold text-slate-600 transition truncate">
                                            {{ $tpl->title }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- Section 2: Update Fields in a Clean 2-Column Grid (Soft Emerald Tint) --}}
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

                        {{-- Section 3: Bottom Footer Bar: Left = Follow up controls | Right = Post Update button (Soft Amber Tint) --}}
                        <div class="mt-auto rounded-lg bg-amber-50/60 p-2 flex items-center justify-between gap-2 flex-wrap">
                            <div class="inline-flex flex-wrap items-center gap-1.5 rounded-lg bg-white/90 p-1 max-w-full">
                                <label class="inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-[11.5px] font-semibold cursor-pointer transition
                                              has-[:checked]:bg-amber-100 has-[:checked]:text-amber-900 text-slate-600 hover:bg-white">
                                    <input type="checkbox" name="follow_up" value="1" id="followUpCheck-{{ $customer->id }}"
                                           data-cid="{{ $customer->id }}"
                                           class="rounded border-line text-amber-600 focus:ring-amber-500/30"
                                           @checked($customer->follow_up)
                                           onchange="toggleFollowUpFields(this.dataset.cid, this.checked)">
                                    Follow up
                                </label>
                                <div id="followUpFields-{{ $customer->id }}"
                                     class="{{ $customer->follow_up ? 'flex' : 'hidden' }} items-center gap-1 flex-wrap">
                                    <div class="inline-flex items-center rounded border border-line bg-white overflow-hidden">
                                        <button type="button"
                                                data-cid="{{ $customer->id }}"
                                                onclick="shiftFollowUpDate(this.dataset.cid, -1)"
                                                title="Previous day"
                                                class="px-2 py-0.5 bg-mist/60 hover:bg-brand-soft hover:text-brand text-slate-700 font-bold text-[12px] border-r border-line transition">‹</button>
                                        <input type="date" name="follow_up_date" id="followUpDate-{{ $customer->id }}"
                                               value="{{ $customer->follow_up_at?->format('Y-m-d') }}"
                                               title="Follow-up date"
                                               class="bg-white px-2 py-0.5 text-[11px] outline-none focus:bg-brand-soft/20">
                                        <button type="button"
                                                data-cid="{{ $customer->id }}"
                                                onclick="shiftFollowUpDate(this.dataset.cid, 1)"
                                                title="Next day"
                                                class="px-2 py-0.5 bg-mist/60 hover:bg-brand-soft hover:text-brand text-slate-700 font-bold text-[12px] border-l border-line transition">›</button>
                                    </div>
                                    <label class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] font-semibold cursor-pointer transition
                                                  has-[:checked]:bg-brand-soft has-[:checked]:text-brand text-slate-600 hover:bg-white">
                                        <input type="checkbox" name="follow_up_time_on" value="1" id="followUpTimeOn-{{ $customer->id }}"
                                               data-cid="{{ $customer->id }}"
                                               class="rounded border-line text-brand focus:ring-brand/30"
                                               @checked($customer->follow_up_has_time)
                                               onchange="toggleFollowUpTime(this.dataset.cid, this.checked)">
                                        Time
                                    </label>
                                    <div id="followUpTimeWrap-{{ $customer->id }}"
                                         class="{{ $customer->follow_up_has_time ? 'inline-flex' : 'hidden' }} items-center rounded border border-line bg-white overflow-hidden">
                                        <button type="button"
                                                data-cid="{{ $customer->id }}"
                                                onclick="shiftFollowUpTime(this.dataset.cid, -30)"
                                                title="Previous 30 min"
                                                class="px-2 py-0.5 bg-mist/60 hover:bg-brand-soft hover:text-brand text-slate-700 font-bold text-[12px] border-r border-line transition">‹</button>
                                        <input type="time" name="follow_up_time" id="followUpTime-{{ $customer->id }}"
                                               value="{{ $customer->follow_up_has_time ? $customer->follow_up_at?->format('H:i') : '' }}"
                                               title="Follow-up time"
                                               class="bg-white px-2 py-0.5 text-[11px] outline-none focus:bg-brand-soft/20">
                                        <button type="button"
                                                data-cid="{{ $customer->id }}"
                                                onclick="shiftFollowUpTime(this.dataset.cid, 30)"
                                                title="Next 30 min"
                                                class="px-2 py-0.5 bg-mist/60 hover:bg-brand-soft hover:text-brand text-slate-700 font-bold text-[12px] border-l border-line transition">›</button>
                                    </div>
                                </div>
                            </div>

                            <button type="submit"
                                    class="w-full sm:w-auto ml-auto inline-flex items-center justify-center gap-1.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[12.5px] font-semibold px-4 py-2 shadow-xs transition">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                Post Update
                            </button>
                        </div>
                    </div>
                </form>
            @endif
        </div>
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
            @forelse($customer->activities as $a)
                <div @if($loop->index >= 2) data-more-msg="{{ $customer->id }}" @endif
                     class="{{ $loop->index >= 2 ? 'hidden ' : '' }}px-3.5 py-2.5 hover:bg-mist/40">
                    <div class="flex items-center gap-1.5 mb-0.5 flex-wrap">
                        <span class="inline-flex px-1.5 py-0.2 rounded text-[9.5px] font-bold uppercase {{ $typeColor[$a->type] ?? 'bg-mist text-slate-500' }}">{{ $a->type }}</span>
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
