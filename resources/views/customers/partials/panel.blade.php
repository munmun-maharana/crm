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
@endphp

<div class="px-3 sm:px-4 py-2.5 space-y-3 w-full">
    @php
        $headerKeys = ['name', 'email', 'phone', 'company', 'city', 'status', 'assigned_to', 'notes', 'date', 'entry_date', 'created_date', 'created_at'];
        $extraFields = collect($formFields ?? [])->reject(fn ($f) => in_array(strtolower($f->key), $headerKeys, true));
    @endphp

    {{-- Top grid: Left = Profile + Actions at bottom full-width | Right = Customer extra details --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-2.5 items-stretch">
        {{-- Left Card: Profile info + Full-width bottom action buttons --}}
        <div class="rounded-lg border border-line bg-white p-2.5 flex flex-col justify-between gap-2 shadow-xs">
            <div class="flex items-start gap-2.5">
                <div class="w-9 h-9 rounded-full bg-brand text-white flex items-center justify-center font-semibold text-xs shrink-0">{{ strtoupper($initials) }}</div>
                <div class="min-w-0 flex-1 leading-tight">
                    <div class="flex flex-wrap items-center gap-x-1.5 gap-y-0.5 min-w-0">
                        <div class="text-[13.5px] font-semibold truncate">{{ $customer->name }}</div>
                        <span class="text-[10px] font-semibold px-1.5 py-0.2 rounded capitalize bg-brand-soft text-brand shrink-0">{{ $customer->status }}</span>
                        @if($customer->follow_up && $customer->follow_up_at)
                            <span class="text-[10px] font-semibold px-1.5 py-0.2 rounded bg-amber-100 text-amber-800 shrink-0" title="Follow-up scheduled">
                                FU {{ $customer->follow_up_at->format('d M') }}
                            </span>
                        @endif
                    </div>
                    <div class="text-[11.5px] text-slate-500 truncate mt-0.5">{{ $customer->email ?: 'No email' }} · {{ $customer->phone ?: 'No phone' }}</div>
                    <div class="text-[11px] text-slate-400 truncate">{{ $customer->company ?: '—' }} · {{ $customer->city ?: '—' }} · Agent: {{ $customer->assignee?->name ?: 'Unassigned' }}</div>
                    @if($customer->isOperations() && $customer->source_customer_id)
                        <div class="text-[11px] text-brand font-semibold">CRM ID #{{ $customer->source_customer_id }}</div>
                    @endif
                    @if($customer->follow_up && $customer->follow_up_at)
                        <div class="text-[11px] text-amber-700 font-semibold">
                            Follow-up: {{ $customer->followUpScheduleLabel() }}
                        </div>
                    @elseif($customer->follow_up_at)
                        <div class="text-[11px] text-emerald-700 font-semibold">
                            Booking Date: {{ $customer->follow_up_at->format('d M Y') }}{{ !empty($customer->meta['booking_slot_time']) ? ' · '.$customer->meta['booking_slot_time'] : '' }}
                        </div>
                    @endif
                </div>
            </div>

            {{-- Bottom of card: full-width action buttons (icon-only except Assign to OPS) --}}
            <div class="pt-1.5 border-t border-line flex items-center gap-1 w-full">
                <button type="button" title="Call" onclick="runAutoAction({{ $customer->id }}, 'call')"
                        class="flex-1 h-7 inline-flex items-center justify-center rounded-md border border-sky-200 bg-sky-50 text-sky-700 hover:bg-sky-100 transition">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                </button>
                <button type="button" title="WhatsApp" onclick="runAutoAction({{ $customer->id }}, 'whatsapp')"
                        class="flex-1 h-7 inline-flex items-center justify-center rounded-md border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0012.04 2zm0 1.82c4.46 0 8.09 3.63 8.09 8.09s-3.63 8.09-8.09 8.09c-1.42 0-2.8-.37-4.01-1.06l-.29-.17-3.12.82.83-3.04-.19-.31a8.05 8.05 0 01-1.22-4.33c0-4.46 3.63-8.09 8.09-8.09zm4.57 10.38c-.18-.09-1.06-.52-1.22-.58-.16-.06-.28-.09-.4.09-.12.18-.46.58-.56.7-.1.12-.2.13-.38.04-.18-.09-.76-.28-1.45-.89-.54-.48-.9-1.07-1.01-1.25-.1-.18-.01-.28.08-.37.08-.08.18-.2.27-.3.09-.1.12-.18.18-.3.06-.12.03-.22-.02-.31-.04-.09-.4-.96-.55-1.31-.14-.34-.29-.3-.4-.3h-.34c-.12 0-.31.04-.47.22-.16.18-.62.61-.62 1.48s.64 1.72.73 1.84c.09.12 1.25 1.91 3.03 2.68.42.18.75.29 1.01.37.42.13.81.11 1.11.07.34-.05 1.06-.43 1.21-.85.15-.42.15-.78.1-.85-.04-.07-.16-.12-.34-.21z"/></svg>
                </button>
                <button type="button" title="Email" onclick="runAutoAction({{ $customer->id }}, 'email')"
                        class="flex-1 h-7 inline-flex items-center justify-center rounded-md border border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100 transition">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </button>
                <button type="button" title="SMS" onclick="runAutoAction({{ $customer->id }}, 'sms')"
                        class="flex-1 h-7 inline-flex items-center justify-center rounded-md border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                </button>
                @unless($customer->isOperations())
                    @if($customer->operationCopy)
                        <span title="Assigned to Operation Team"
                              class="flex-1 h-7 inline-flex items-center justify-center px-2.5 rounded-md border border-emerald-200 bg-emerald-50 text-emerald-800 text-[10.5px] font-semibold whitespace-nowrap">
                            Assigned to OPS
                        </span>
                    @else
                        <button type="button" title="Assign to Operation Team" onclick="assignToOperations({{ $customer->id }})"
                                class="flex-1 h-7 inline-flex items-center justify-center px-2.5 rounded-md bg-ink text-white text-[10.5px] font-semibold hover:bg-slate-800 transition whitespace-nowrap">
                            Assign to OPS
                        </button>
                    @endif
                @endunless
            </div>
        </div>

        {{-- Right Card: Customer details only --}}
        <div class="rounded-lg border border-line bg-white p-2.5 shadow-xs flex flex-col justify-center">
            @php
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
            @endphp
            @if($visibleExtras->isNotEmpty() || $createdDateVal || $actionDateVal)
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-3 gap-y-1.5 text-[11.5px] leading-tight break-words">
                    @if($createdDateVal)
                        <div>
                            <div class="text-slate-400 text-[10px]">Created Date</div>
                            <div class="font-semibold text-ink">{{ $createdDateVal }}</div>
                        </div>
                    @endif
                    @if($actionDateVal)
                        <div>
                            <div class="text-slate-400 text-[10px]">{{ $actionDateLabel }}</div>
                            <div class="font-semibold {{ $customer->follow_up ? 'text-amber-700' : 'text-emerald-700' }}">{{ $actionDateVal }}</div>
                        </div>
                    @endif
                    @foreach($visibleExtras as $field)
                        @php
                            $v = $customer->fieldValue($field->key);
                            $strVal = trim(is_array($v) ? implode(', ', $v) : (string) $v);
                            $isMapField = str_contains(strtolower($field->key), 'map') || str_contains(strtolower($field->label), 'map');
                            $isUrlVal = (bool) filter_var($strVal, FILTER_VALIDATE_URL) || (bool) preg_match('~^https?://~i', $strVal);
                        @endphp
                        <div>
                            <div class="text-slate-400 text-[10px]">{{ $field->label }}</div>
                            @if($isMapField || $isUrlVal)
                                @php
                                    $mapHref = $isUrlVal
                                        ? $strVal
                                        : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($strVal);
                                @endphp
                                <a href="{{ $mapHref }}" target="_blank" rel="noopener noreferrer"
                                   class="mt-0.5 inline-flex items-center gap-1 rounded-md border border-brand/30 bg-brand-soft hover:bg-brand hover:text-white text-brand px-2 py-0.5 text-[10.5px] font-bold transition shadow-2xs">
                                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <span>{{ $isMapField ? 'Open Map URL' : 'Open URL' }}</span>
                                </a>
                            @else
                                <div class="font-semibold text-ink">{{ $strVal }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center text-slate-400 text-[11px] py-2">No additional customer details</div>
            @endif
        </div>
    </div>

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
              class="rounded-lg border border-line bg-white shadow-xs overflow-hidden p-3 space-y-3"
              onsubmit="event.preventDefault(); Alpine.$data(document.body).postActivity(this)">
            @csrf
            <input type="hidden" name="type" value="status">
            <input type="hidden" name="wash_action" value="1">
            <input type="hidden" name="status_change" id="opsWashStatus-{{ $customer->id }}" value="{{ ucfirst($activeWashState) }}">

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                <div class="flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-brand-soft text-brand inline-flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <div>
                        <div class="text-[12.5px] font-bold text-ink leading-tight">Wash Status</div>
                        <div class="text-[10.5px] text-slate-400">Syncs status with CRM & Booking Slot</div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-2 sm:w-auto w-full">
                    {{-- Pending (Default) --}}
                    <button type="button"
                            @click="washState = 'pending'; const f = $el.closest('form'); f.querySelector('[name=status_change]').value = 'Pending'; Alpine.$data(document.body).postActivity(f);"
                            :class="washState === 'pending'
                                ? 'border-amber-500 bg-amber-500 text-white ring-2 ring-amber-500/25'
                                : 'border-amber-200 bg-amber-50/70 text-amber-800 hover:bg-amber-100'"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg border px-3.5 py-2 text-[12px] font-bold transition shadow-2xs">
                        <span>⏳</span>
                        <span>Pending</span>
                    </button>

                    {{-- Wash Success --}}
                    <button type="button"
                            @click="washState = 'success'; const f = $el.closest('form'); f.querySelector('[name=status_change]').value = 'Success'; Alpine.$data(document.body).postActivity(f);"
                            :class="washState === 'success'
                                ? 'border-emerald-600 bg-emerald-600 text-white ring-2 ring-emerald-600/25'
                                : 'border-emerald-200 bg-emerald-50/70 text-emerald-800 hover:bg-emerald-100'"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg border px-3.5 py-2 text-[12px] font-bold transition shadow-2xs">
                        <span>✓</span>
                        <span>Wash Success</span>
                    </button>

                    {{-- Cancel --}}
                    <button type="button"
                            @click="washState = 'cancelled'; const f = $el.closest('form'); f.querySelector('[name=status_change]').value = 'Cancelled'; Alpine.$data(document.body).postActivity(f);"
                            :class="washState === 'cancelled'
                                ? 'border-rose-600 bg-rose-600 text-white ring-2 ring-rose-600/25'
                                : 'border-rose-200 bg-rose-50/70 text-rose-800 hover:bg-rose-100'"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg border px-3.5 py-2 text-[12px] font-bold transition shadow-2xs">
                        <span>✕</span>
                        <span>Cancel</span>
                    </button>
                </div>
            </div>

            {{-- Feedback, Optional Image & Change Fields (Always Visible) --}}
            <div class="pt-2.5 border-t border-line space-y-2.5">
                <div class="flex items-center justify-between gap-2">
                    <div class="text-[11.5px] font-bold text-ink flex items-center gap-1.5">
                        <span>📝 Wash Feedback & Update Fields</span>
                        <span class="text-[10.5px] font-normal text-slate-400">(Optional image & field updates)</span>
                    </div>
                    <button type="submit"
                            class="inline-flex items-center gap-1 rounded-md bg-brand hover:bg-brand-dark text-white text-[11.5px] font-semibold px-3 py-1 shadow-xs transition shrink-0">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Save Feedback & Update
                    </button>
                </div>

                <textarea name="body" id="activityBody-{{ $customer->id }}" rows="2" placeholder="Write wash feedback / what happened…"
                          class="w-full rounded-md border border-line bg-mist/20 px-2.5 py-1.5 text-[12px] leading-snug outline-none resize-y min-h-[52px]
                                 focus:bg-white focus:border-brand focus:ring-2 focus:ring-brand/20 transition"></textarea>

                <div class="flex flex-wrap items-center gap-2">
                    <label class="inline-flex items-center gap-1.5 rounded-md border border-dashed border-slate-300 bg-mist/40 hover:bg-brand-soft/50 hover:border-brand px-2.5 py-1 text-[11px] font-semibold text-slate-600 hover:text-brand cursor-pointer transition">
                        <svg class="w-3.5 h-3.5 shrink-0 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span>Upload Image (Optional)</span>
                        <input type="file" name="images[]" accept="image/*" multiple class="hidden"
                               onchange="const info = this.closest('div').querySelector('[data-img-info]'); if (info) { info.textContent = this.files.length ? Array.from(this.files).map(f => f.name).join(', ') : 'No image selected'; }">
                    </label>
                    <span data-img-info class="text-[10.5px] text-slate-400 truncate max-w-[260px]">No image selected</span>
                </div>

                @if($updateFields->isNotEmpty())
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-1.5 pt-1">
                        @foreach($updateFields as $uf)
                            @php
                                $ufVal = $customer->fieldValue($uf->key);
                                if (is_array($ufVal)) $ufVal = implode(', ', $ufVal);
                            @endphp
                            <div>
                                <label class="block text-[9.5px] font-semibold uppercase tracking-wide text-slate-400 mb-0.5">{{ $uf->label }}</label>
                                @if($uf->type === 'select')
                                    <div class="relative">
                                        <select name="update_fields[{{ $uf->key }}]"
                                                class="w-full appearance-none rounded-md border border-line bg-mist/50 hover:bg-white focus:bg-white focus:border-brand focus:ring-2 focus:ring-brand/20 pl-2 pr-6 py-1 text-[11.5px] font-medium text-ink outline-none transition">
                                            <option value="">Select {{ $uf->label }}</option>
                                            @foreach($uf->options ?? [] as $opt)
                                                <option value="{{ $opt }}" @selected(strtolower(trim((string) $ufVal)) === strtolower(trim((string) $opt)))>{{ $opt }}</option>
                                            @endforeach
                                        </select>
                                        <svg class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </div>
                                @elseif($uf->type === 'phone')
                                    <input name="update_fields[{{ $uf->key }}]" value="{{ $ufVal }}"
                                           type="tel" inputmode="tel" pattern="[0-9+\-\s()]*"
                                           oninput="this.value = this.value.replace(/[^0-9+\-\s()]/g, '')"
                                           placeholder="{{ $uf->label }}"
                                           class="w-full rounded-md border border-line bg-white px-2 py-1 text-[11.5px] outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">
                                @elseif($uf->type === 'number')
                                    <input name="update_fields[{{ $uf->key }}]" value="{{ $ufVal }}"
                                           type="number" step="any" inputmode="decimal"
                                           oninput="this.value = this.value.replace(/[^0-9.\-]/g, '')"
                                           placeholder="{{ $uf->label }}"
                                           class="w-full rounded-md border border-line bg-white px-2 py-1 text-[11.5px] outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">
                                @elseif($uf->type === 'date')
                                    <input name="update_fields[{{ $uf->key }}]" value="{{ $ufVal }}"
                                           type="date"
                                           class="w-full rounded-md border border-line bg-white px-2 py-1 text-[11.5px] outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">
                                @elseif($uf->type === 'time')
                                    <input name="update_fields[{{ $uf->key }}]" value="{{ $ufVal }}"
                                           type="time"
                                           class="w-full rounded-md border border-line bg-white px-2 py-1 text-[11.5px] outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">
                                @else
                                    <input name="update_fields[{{ $uf->key }}]" value="{{ $ufVal }}"
                                           type="{{ $uf->type === 'email' ? 'email' : 'text' }}"
                                           placeholder="{{ $uf->label }}"
                                           class="w-full rounded-md border border-line bg-white px-2 py-1 text-[11.5px] outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </form>
    @else
    <form action="{{ route('customers.activities', $customer) }}"
          enctype="multipart/form-data"
          class="rounded-lg border border-line bg-white shadow-xs overflow-hidden"
          onsubmit="event.preventDefault(); Alpine.$data(document.body).postActivity(this)">
        @csrf
        <div class="flex items-center justify-between gap-2 px-3 py-1.5 bg-gradient-to-r from-mist/80 to-white border-b border-line">
            <div class="flex items-center gap-1.5 min-w-0">
                <span class="w-6 h-6 rounded-md bg-brand-soft text-brand inline-flex items-center justify-center shrink-0">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </span>
                <div class="min-w-0 flex items-baseline gap-1.5">
                    <div class="text-[12.5px] font-semibold text-ink leading-tight">Add update</div>
                    <div class="text-[10.5px] text-slate-400 truncate hidden sm:inline">Note, status change & follow-up</div>
                </div>
            </div>
            <button type="submit"
                    class="inline-flex items-center gap-1 rounded-md bg-brand hover:bg-brand-dark text-white text-[11.5px] font-semibold px-3 py-1 shadow-xs transition shrink-0">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                Post
            </button>
        </div>

        <div class="p-2.5 space-y-2">
            <div class="flex items-center gap-1.5 flex-wrap">
                <div class="relative shrink-0">
                    <select name="type"
                            class="appearance-none rounded-md border border-line bg-mist/50 hover:bg-white focus:bg-white focus:border-brand focus:ring-2 focus:ring-brand/20 pl-2 pr-6 py-1 text-[11.5px] font-medium text-ink outline-none transition">
                        <option value="note">Note</option>
                        <option value="call">Call</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="email">Email</option>
                        <option value="sms">SMS</option>
                    </select>
                    <svg class="pointer-events-none absolute right-1.5 top-1/2 -translate-y-1/2 w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>
                <div class="relative shrink-0">
                    <select name="status_change"
                            onchange="handleStatusChangeFollowUp({{ $customer->id }}, this.value)"
                            class="appearance-none rounded-md border border-line bg-mist/50 hover:bg-white focus:bg-white focus:border-brand focus:ring-2 focus:ring-brand/20 pl-2 pr-6 py-1 text-[11.5px] font-medium text-ink outline-none transition">
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
                <input name="title" placeholder="Internal message"
                       class="flex-1 min-w-[130px] rounded-md border border-line bg-white px-2.5 py-1 text-[11.5px] outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">

                <div class="inline-flex flex-wrap items-center gap-1 rounded-md border border-line bg-mist/40 p-0.5 shrink-0 max-w-full">
                    <label class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] font-semibold cursor-pointer transition
                                  has-[:checked]:bg-amber-100 has-[:checked]:text-amber-900 text-slate-600 hover:bg-white">
                        <input type="checkbox" name="follow_up" value="1" id="followUpCheck-{{ $customer->id }}"
                               class="rounded border-line text-amber-600 focus:ring-amber-500/30"
                               @checked($customer->follow_up)
                               onchange="toggleFollowUpFields({{ $customer->id }}, this.checked)">
                        Follow up
                    </label>
                    <div id="followUpFields-{{ $customer->id }}" class="items-center gap-1 flex-wrap"
                         style="display: {{ $customer->follow_up ? 'flex' : 'none' }}">
                        <input type="date" name="follow_up_date" id="followUpDate-{{ $customer->id }}"
                               value="{{ $customer->follow_up_at?->format('Y-m-d') }}"
                               title="Follow-up date"
                               class="rounded border border-line bg-white px-1.5 py-0.5 text-[11px] outline-none focus:border-brand focus:ring-1 focus:ring-brand/20">
                        <label class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] font-semibold cursor-pointer transition
                                      has-[:checked]:bg-brand-soft has-[:checked]:text-brand text-slate-600 hover:bg-white">
                            <input type="checkbox" name="follow_up_time_on" value="1" id="followUpTimeOn-{{ $customer->id }}"
                                   class="rounded border-line text-brand focus:ring-brand/30"
                                   @checked($customer->follow_up_has_time)
                                   onchange="toggleFollowUpTime({{ $customer->id }}, this.checked)">
                            Time
                        </label>
                        <input type="time" name="follow_up_time" id="followUpTime-{{ $customer->id }}"
                               value="{{ $customer->follow_up_has_time ? $customer->follow_up_at?->format('H:i') : '' }}"
                               title="Follow-up time"
                               class="rounded border border-line bg-white px-1.5 py-0.5 text-[11px] outline-none focus:border-brand focus:ring-1 focus:ring-brand/20"
                               style="display: {{ $customer->follow_up_has_time ? 'inline-block' : 'none' }}">
                    </div>
                </div>
            </div>

            <textarea name="body" id="activityBody-{{ $customer->id }}" rows="2" placeholder="What happened / note…"
                      class="w-full rounded-md border border-line bg-mist/20 px-2.5 py-1.5 text-[12px] leading-snug outline-none resize-y min-h-[52px]
                             focus:bg-white focus:border-brand focus:ring-2 focus:ring-brand/20 transition"></textarea>

            @php $noteTemplates = $noteTemplates ?? collect(); @endphp
            @if($noteTemplates->isNotEmpty())
                <div class="flex flex-wrap items-center gap-1">
                    <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 mr-1">Quick notes:</span>
                    @foreach($noteTemplates as $tpl)
                        <button type="button"
                                title="{{ $tpl->body }}"
                                onclick="applyNoteTemplate(this.closest('form'), @js($tpl->title), @js($tpl->body))"
                                class="inline-flex items-center max-w-full rounded-full border border-line bg-white hover:border-brand hover:bg-brand-soft hover:text-brand px-2 py-0.5 text-[10.5px] font-semibold text-slate-600 transition truncate">
                            {{ $tpl->title }}
                        </button>
                    @endforeach
                </div>
            @endif

            @if($updateFields->isNotEmpty())
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-1.5">
                    @foreach($updateFields as $uf)
                        @php
                            $ufVal = $customer->fieldValue($uf->key);
                            if (is_array($ufVal)) $ufVal = implode(', ', $ufVal);
                        @endphp
                        <div>
                            <label class="block text-[9.5px] font-semibold uppercase tracking-wide text-slate-400 mb-0.5">{{ $uf->label }}</label>
                            @if($uf->type === 'select')
                                <div class="relative">
                                    <select name="update_fields[{{ $uf->key }}]"
                                            class="w-full appearance-none rounded-md border border-line bg-mist/50 hover:bg-white focus:bg-white focus:border-brand focus:ring-2 focus:ring-brand/20 pl-2 pr-6 py-1 text-[11.5px] font-medium text-ink outline-none transition">
                                        <option value="">Select {{ $uf->label }}</option>
                                        @foreach($uf->options ?? [] as $opt)
                                            <option value="{{ $opt }}" @selected(strtolower(trim((string) $ufVal)) === strtolower(trim((string) $opt)))>{{ $opt }}</option>
                                        @endforeach
                                    </select>
                                    <svg class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                            @elseif($uf->type === 'phone')
                                <input name="update_fields[{{ $uf->key }}]" value="{{ $ufVal }}"
                                       type="tel" inputmode="tel" pattern="[0-9+\-\s()]*"
                                       oninput="this.value = this.value.replace(/[^0-9+\-\s()]/g, '')"
                                       placeholder="{{ $uf->label }}"
                                       class="w-full rounded-md border border-line bg-white px-2 py-1 text-[11.5px] outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">
                            @elseif($uf->type === 'number')
                                <input name="update_fields[{{ $uf->key }}]" value="{{ $ufVal }}"
                                       type="number" step="any" inputmode="decimal"
                                       oninput="this.value = this.value.replace(/[^0-9.\-]/g, '')"
                                       placeholder="{{ $uf->label }}"
                                       class="w-full rounded-md border border-line bg-white px-2 py-1 text-[11.5px] outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">
                            @elseif($uf->type === 'date')
                                <input name="update_fields[{{ $uf->key }}]" value="{{ $ufVal }}"
                                       type="date"
                                       class="w-full rounded-md border border-line bg-white px-2 py-1 text-[11.5px] outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">
                            @elseif($uf->type === 'time')
                                <input name="update_fields[{{ $uf->key }}]" value="{{ $ufVal }}"
                                       type="time"
                                       class="w-full rounded-md border border-line bg-white px-2 py-1 text-[11.5px] outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">
                            @else
                                <input name="update_fields[{{ $uf->key }}]" value="{{ $ufVal }}"
                                       type="{{ $uf->type === 'email' ? 'email' : 'text' }}"
                                       placeholder="{{ $uf->label }}"
                                       class="w-full rounded-md border border-line bg-white px-2 py-1 text-[11.5px] outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </form>
    @endif

    <div>
        <div class="text-[11px] font-semibold text-slate-500 mb-1.5">Chat & updates</div>
        <div class="space-y-0 border border-line rounded-lg overflow-hidden">
            @forelse($customer->activities as $a)
                <div class="px-2.5 py-2 border-b border-line last:border-b-0 hover:bg-mist/40">
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
    </div>
</div>
