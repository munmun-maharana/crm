@php
    $typeColor = [
        'note' => 'bg-slate-100 text-slate-700',
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
    $myStock = $myStock ?? [];
    $activationRequests = $activationRequests ?? collect();
    $actTone = [
        'draft' => 'bg-slate-100 text-slate-600',
        'pending' => 'bg-amber-50 text-amber-800',
        'approved' => 'bg-emerald-50 text-emerald-800',
        'rejected' => 'bg-rose-50 text-rose-700',
        'fulfilled' => 'bg-sky-50 text-sky-800',
    ];
@endphp

<div class="px-5 py-4 space-y-5 w-full">
    {{-- One row: profile + action icons --}}
    <div class="flex items-center gap-3">
        <div class="w-11 h-11 rounded-full bg-brand text-white flex items-center justify-center font-semibold text-sm shrink-0">{{ strtoupper($initials) }}</div>
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2 min-w-0">
                <div class="text-[15px] font-semibold truncate">{{ $customer->name }}</div>
                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded capitalize bg-brand-soft text-brand shrink-0">{{ $customer->status }}</span>
                @if($customer->follow_up && $customer->follow_up_at)
                    <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 shrink-0" title="Follow-up scheduled">
                        FU {{ $customer->follow_up_at->format('d M') }}
                    </span>
                @endif
            </div>
            <div class="text-[13px] text-slate-500 truncate">{{ $customer->email ?: 'No email' }} · {{ $customer->phone ?: 'No phone' }}</div>
            <div class="text-[12px] text-slate-400 mt-0.5 truncate">{{ $customer->company ?: '—' }} · {{ $customer->city ?: '—' }} · Agent: {{ $customer->assignee?->name ?: 'Unassigned' }}</div>
            @if($customer->isOperations() && $customer->source_customer_id)
                <div class="text-[12px] text-brand font-semibold mt-0.5">CRM ID #{{ $customer->source_customer_id }}</div>
            @endif
            @if($customer->follow_up && $customer->follow_up_at)
                <div class="text-[12px] text-amber-700 font-semibold mt-0.5">
                    Call schedule: {{ $customer->followUpScheduleLabel() }}
                </div>
            @endif
        </div>

        <div class="flex items-center gap-1.5 shrink-0">
            <button type="button" title="Call" onclick="runAutoAction({{ $customer->id }}, 'call')"
                    class="w-9 h-9 inline-flex items-center justify-center rounded-lg border border-sky-200 bg-sky-50 text-sky-700 hover:bg-sky-100">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
            </button>
            <button type="button" title="WhatsApp" onclick="runAutoAction({{ $customer->id }}, 'whatsapp')"
                    class="w-9 h-9 inline-flex items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0012.04 2zm0 1.82c4.46 0 8.09 3.63 8.09 8.09s-3.63 8.09-8.09 8.09c-1.42 0-2.8-.37-4.01-1.06l-.29-.17-3.12.82.83-3.04-.19-.31a8.05 8.05 0 01-1.22-4.33c0-4.46 3.63-8.09 8.09-8.09zm4.57 10.38c-.18-.09-1.06-.52-1.22-.58-.16-.06-.28-.09-.4.09-.12.18-.46.58-.56.7-.1.12-.2.13-.38.04-.18-.09-.76-.28-1.45-.89-.54-.48-.9-1.07-1.01-1.25-.1-.18-.01-.28.08-.37.08-.08.18-.2.27-.3.09-.1.12-.18.18-.3.06-.12.03-.22-.02-.31-.04-.09-.4-.96-.55-1.31-.14-.34-.29-.3-.4-.3h-.34c-.12 0-.31.04-.47.22-.16.18-.62.61-.62 1.48s.64 1.72.73 1.84c.09.12 1.25 1.91 3.03 2.68.42.18.75.29 1.01.37.42.13.81.11 1.11.07.34-.05 1.06-.43 1.21-.85.15-.42.15-.78.1-.85-.04-.07-.16-.12-.34-.21z"/></svg>
            </button>
            <button type="button" title="Email" onclick="runAutoAction({{ $customer->id }}, 'email')"
                    class="w-9 h-9 inline-flex items-center justify-center rounded-lg border border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </button>
            <button type="button" title="SMS" onclick="runAutoAction({{ $customer->id }}, 'sms')"
                    class="w-9 h-9 inline-flex items-center justify-center rounded-lg border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            </button>
            @unless($customer->isOperations())
                @if($customer->operationCopy)
                    <span title="Assigned to Operation Team"
                          class="h-9 inline-flex items-center px-2.5 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 text-[11px] font-semibold">
                        Ops
                    </span>
                @else
                    <button type="button" title="Assign to Operation Team" onclick="assignToOperations({{ $customer->id }})"
                            class="h-9 inline-flex items-center px-2.5 rounded-lg bg-ink text-white text-[11px] font-semibold hover:bg-slate-800">
                        Operation
                    </button>
                @endif
            @endunless
        </div>
    </div>

    @php
        $headerKeys = ['name', 'email', 'phone', 'company', 'city', 'status', 'assigned_to', 'notes'];
        $extraFields = collect($formFields ?? [])->reject(fn ($f) => in_array($f->key, $headerKeys, true));
    @endphp
    @if($extraFields->isNotEmpty())
        <div class="rounded border border-line p-3 grid grid-cols-2 gap-2 text-[12px]">
            @foreach($extraFields as $field)
                @php $v = $customer->fieldValue($field->key); @endphp
                @if($v !== null && $v !== '')
                    <div>
                        <div class="text-slate-400">{{ $field->label }}</div>
                        <div class="font-semibold">{{ $v }}</div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    @unless($customer->isOperations())
    <form action="{{ route('customers.activities', $customer) }}"
          class="rounded-xl border border-line bg-white shadow-sm overflow-hidden"
          onsubmit="event.preventDefault(); Alpine.$data(document.body).postActivity(this)">
        @csrf
        <div class="flex items-center justify-between gap-2 px-3.5 py-2.5 bg-gradient-to-r from-mist/80 to-white border-b border-line">
            <div class="flex items-center gap-2 min-w-0">
                <span class="w-7 h-7 rounded-lg bg-brand-soft text-brand inline-flex items-center justify-center shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </span>
                <div class="min-w-0">
                    <div class="text-[13px] font-semibold text-ink leading-tight">Add update</div>
                    <div class="text-[11px] text-slate-400 truncate">Note, status change & follow-up</div>
                </div>
            </div>
            <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[12px] font-semibold px-3.5 py-1.5 shadow-sm transition shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                Post
            </button>
        </div>

        <div class="p-3 space-y-2.5">
            <div class="flex items-center gap-2 flex-wrap">
                <div class="relative shrink-0">
                    <select name="type"
                            class="appearance-none rounded-lg border border-line bg-mist/50 hover:bg-white focus:bg-white focus:border-brand focus:ring-2 focus:ring-brand/20 pl-2.5 pr-7 py-1.5 text-[12px] font-medium text-ink outline-none transition">
                        <option value="note">Note</option>
                        <option value="call">Call</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="email">Email</option>
                        <option value="sms">SMS</option>
                    </select>
                    <svg class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>
                <div class="relative shrink-0">
                    <select name="status_change"
                            class="appearance-none rounded-lg border border-line bg-mist/50 hover:bg-white focus:bg-white focus:border-brand focus:ring-2 focus:ring-brand/20 pl-2.5 pr-7 py-1.5 text-[12px] font-medium text-ink outline-none transition">
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
                    <svg class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>
                <input name="title" placeholder="Title (optional)"
                       class="flex-1 min-w-[140px] rounded-lg border border-line bg-white px-3 py-1.5 text-[12px] outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">

                <div class="inline-flex items-center gap-1 rounded-lg border border-line bg-mist/40 p-1 shrink-0">
                    <label class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-[11px] font-semibold cursor-pointer transition
                                  has-[:checked]:bg-amber-100 has-[:checked]:text-amber-900 text-slate-600 hover:bg-white">
                        <input type="checkbox" name="follow_up" value="1" id="followUpCheck-{{ $customer->id }}"
                               class="rounded border-line text-amber-600 focus:ring-amber-500/30"
                               @checked($customer->follow_up)
                               onchange="toggleFollowUpFields({{ $customer->id }}, this.checked)">
                        Follow up
                    </label>
                    <div id="followUpFields-{{ $customer->id }}" class="items-center gap-1"
                         style="display: {{ $customer->follow_up ? 'flex' : 'none' }}">
                        <input type="date" name="follow_up_date" id="followUpDate-{{ $customer->id }}"
                               value="{{ $customer->follow_up_at?->format('Y-m-d') }}"
                               title="Follow-up date"
                               class="rounded-md border border-line bg-white px-2 py-1 text-[11px] outline-none focus:border-brand focus:ring-1 focus:ring-brand/20">
                        <label class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-[11px] font-semibold cursor-pointer transition
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
                               class="rounded-md border border-line bg-white px-2 py-1 text-[11px] outline-none focus:border-brand focus:ring-1 focus:ring-brand/20"
                               style="display: {{ $customer->follow_up_has_time ? 'inline-block' : 'none' }}">
                    </div>
                </div>
            </div>

            <textarea name="body" id="activityBody-{{ $customer->id }}" rows="3" placeholder="What happened / note…"
                      class="w-full rounded-lg border border-line bg-mist/20 px-3 py-2.5 text-[13px] leading-relaxed outline-none resize-y min-h-[72px]
                             focus:bg-white focus:border-brand focus:ring-2 focus:ring-brand/20 transition"></textarea>

            @php $noteTemplates = $noteTemplates ?? collect(); @endphp
            @if($noteTemplates->isNotEmpty())
                <div class="pt-0.5">
                    <div class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 mb-1.5">Quick notes</div>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($noteTemplates as $tpl)
                            <button type="button"
                                    title="{{ $tpl->body }}"
                                    onclick="applyNoteTemplate(this.closest('form'), @js($tpl->title), @js($tpl->body))"
                                    class="inline-flex items-center max-w-full rounded-full border border-line bg-white hover:border-brand hover:bg-brand-soft hover:text-brand px-2.5 py-1 text-[11px] font-semibold text-slate-600 transition truncate">
                                {{ $tpl->title }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </form>

    <div>
        <div class="text-[12px] font-semibold text-slate-500 mb-2">Chat & updates</div>
        <div class="space-y-0 border border-line rounded overflow-hidden">
            @forelse($customer->activities as $a)
                <div class="px-3 py-3 border-b border-line last:border-b-0 hover:bg-mist/40">
                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                        <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold uppercase {{ $typeColor[$a->type] ?? 'bg-mist text-slate-500' }}">{{ $a->type }}</span>
                        @if(!empty($a->meta['status_to']))
                            <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-rose-100 text-rose-700">
                                {{ $a->meta['status_to'] }}
                            </span>
                        @endif
                        @if(!empty($a->meta['follow_up_at']))
                            <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-100 text-amber-800">
                                Follow-up {{ \Carbon\Carbon::parse($a->meta['follow_up_at'])->format('d M Y') }}
                                @if(!empty($a->meta['follow_up_has_time']))
                                    · {{ \Carbon\Carbon::parse($a->meta['follow_up_at'])->format('h:i A') }}
                                @else
                                    · Anytime
                                @endif
                            </span>
                        @endif
                        <span class="text-[12px] font-semibold">{{ $a->title }}</span>
                        <span class="ml-auto text-[11px] text-slate-400">{{ $a->created_at?->diffForHumans() }}</span>
                    </div>
                    <div class="text-[13px] text-slate-700 whitespace-pre-wrap">{{ $a->body }}</div>
                    <div class="text-[11px] text-slate-400 mt-1">{{ $a->user?->name ?: 'System' }}</div>
                </div>
            @empty
                <div class="px-3 py-8 text-center text-slate-400 text-sm">No timeline yet</div>
            @endforelse
        </div>
    </div>
    @endunless

    @if($customer->isOperations())
        <div>
            <div class="flex items-center justify-between gap-2 mb-2">
                <div class="text-[12px] font-semibold text-slate-500">Activation requests</div>
                <button type="button"
                        onclick="startActivationRequest({{ $customer->id }})"
                        class="rounded-lg bg-brand text-white px-3 py-1.5 text-[12px] font-semibold hover:bg-brand/90">
                    + Activation request
                </button>
            </div>

            <div class="space-y-2">
                @forelse($activationRequests as $req)
                    @php
                        $lines = $req->itemLines();
                        $isDraft = $req->status === 'draft';
                    @endphp
                    <div class="rounded-xl border border-line bg-white shadow-sm overflow-hidden" data-act-card="{{ $req->id }}">
                        <div class="px-3.5 py-2.5 border-b border-line flex items-center gap-2 flex-wrap bg-mist/40">
                            <div class="text-[13px] font-semibold text-ink">{{ $customer->name }}</div>
                            <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold uppercase {{ $actTone[$req->status] ?? 'bg-mist text-slate-600' }}">
                                {{ $req->statusLabel() }}
                            </span>
                            <span class="ml-auto text-[11px] text-slate-400">{{ $req->created_at?->format('d M Y · h:i A') }}</span>
                        </div>

                        <div class="px-3.5 py-3 space-y-3">
                            @if($isDraft)
                                <button type="button"
                                        onclick="toggleActivationForm({{ $req->id }})"
                                        class="rounded-lg border border-line px-3 py-1.5 text-[12px] font-semibold hover:bg-mist">
                                    Activation request — select devices
                                </button>

                                <form id="act-form-{{ $req->id }}" class="hidden space-y-3"
                                      onsubmit="event.preventDefault(); submitActivationItemRequest(this, {{ $customer->id }})">
                                    <input type="hidden" name="activation_request_id" value="{{ $req->id }}">
                                    @if(! empty($myStock))
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            @foreach($myStock as $cat)
                                                <div>
                                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ $cat['name'] }}</label>
                                                    <select name="inventory_item_ids[]"
                                                            class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                                                        <option value="">Select {{ $cat['name'] }}…</option>
                                                        @foreach($cat['items'] as $item)
                                                            <option value="{{ $item['id'] }}">{{ $item['label'] }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-[12px] text-amber-700 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">
                                            No inventory on your ID. Assign stock to yourself in Inventory first.
                                        </div>
                                    @endif
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Note</label>
                                        <textarea name="notes" rows="2" placeholder="Write a note…"
                                                  class="w-full rounded-lg border border-line bg-mist/20 px-3 py-2 text-[13px] outline-none focus:bg-white focus:border-brand focus:ring-2 focus:ring-brand/20"></textarea>
                                    </div>
                                    <button type="submit"
                                            class="rounded-lg bg-brand text-white px-4 py-2 text-[13px] font-semibold hover:bg-brand/90 disabled:opacity-50"
                                            @disabled(empty($myStock))>
                                        Submit activation
                                    </button>
                                </form>
                            @else
                                @foreach($lines as $line)
                                    <div class="flex items-start gap-2 text-[13px]">
                                        <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-brand-soft text-brand shrink-0">{{ $line['type'] }}</span>
                                        <span class="font-semibold text-ink">{{ $line['label'] }}</span>
                                    </div>
                                @endforeach
                                @if($req->notes)
                                    <div class="text-[12px] text-slate-600 pt-1 border-t border-line">{{ $req->notes }}</div>
                                @endif
                                <div class="text-[11px] text-slate-400">Sent by {{ $req->requester?->name ?: 'System' }} · Status: {{ $req->statusLabel() }}</div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-line bg-white px-3 py-6 text-center text-slate-400 text-sm">
                        No activation requests yet. Click “+ Activation request” to start one.
                    </div>
                @endforelse
            </div>
        </div>
    @endif
</div>
