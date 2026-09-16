@extends('layouts.app')

@section('title', 'CRM')

@section('split')
@php
    $openId = request('open');
    $statusTone = [
        'new' => 'text-sky-700 bg-sky-50',
        'interested' => 'text-teal-700 bg-teal-50',
        'contacted' => 'text-blue-700 bg-blue-50',
        'converted' => 'text-emerald-700 bg-emerald-50',
        'lost' => 'text-rose-700 bg-rose-50',
        'request' => 'text-amber-800 bg-amber-50',
    ];
@endphp

<div class="h-full flex flex-col"
     x-init="@if($openId) $nextTick(() => selectCustomer({{ (int) $openId }})) @endif">

    <div class="shrink-0 border-b border-line px-3 py-2 flex items-center gap-2 bg-white">
        <div class="text-[15px] font-semibold mr-2">CRM</div>
        <form method="GET" id="crmFilterForm" class="flex flex-1 items-center gap-2 min-w-0">
            <input type="text" name="q" id="crmSearch" value="{{ $q }}"
                   placeholder="Search… Enter or wait 3 sec"
                   autocomplete="off"
                   class="flex-1 max-w-md rounded border border-line px-3 py-1.5 text-[13px] bg-mist/50 focus:outline-none focus:ring-1 focus:ring-brand focus:bg-white">
            <select name="status" id="crmStatus" class="rounded border border-line px-2 py-1.5 text-[13px] bg-white">
                <option value="">All status</option>
                @foreach(['new','interested','contacted','converted','lost','request'] as $s)
                    <option value="{{ $s }}" @selected($status===$s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <button type="submit" title="Filter"
                    class="w-9 h-9 inline-flex items-center justify-center rounded-lg border border-line text-slate-600 hover:bg-mist">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M6 12h12M10 20h4"/></svg>
            </button>
            <span id="crmSearchHint" class="text-[11px] text-slate-400 hidden">Searching…</span>
        </form>

        <div class="flex items-center gap-1.5 shrink-0">
            <button type="button" x-show="selectedId" x-cloak title="Back to list"
                    x-transition.opacity.duration.200ms
                    @click="closeDesk()"
                    class="w-9 h-9 inline-flex items-center justify-center rounded-lg border border-line text-slate-600 hover:bg-mist">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </button>

            <button type="button" title="Add customer" @click="openCreateForm()"
                    class="w-9 h-9 inline-flex items-center justify-center rounded-lg bg-brand text-white hover:bg-brand-dark">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="shrink-0 px-3 py-1.5 text-[12px] bg-emerald-50 text-emerald-800 border-b border-emerald-100">{{ session('success') }}</div>
    @endif

    <div class="flex-1 min-h-0 desk-stage">
        <div class="desk-pane overflow-y-auto crm-scroll bg-mist/30 p-4"
             x-show="!selectedId"
             x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-x-4 scale-[0.985]"
             x-transition:enter-end="opacity-100 translate-x-0 scale-100"
             x-transition:leave="transition ease-in duration-220"
             x-transition:leave-start="opacity-100 translate-x-0 scale-100"
             x-transition:leave-end="opacity-0 -translate-x-6 scale-[0.985]">
            <div class="rounded-xl border border-line bg-white overflow-hidden shadow-sm">
                <table class="w-full text-[13px]">
                    <thead class="bg-mist text-slate-500 text-[11px] uppercase tracking-wide">
                        <tr>
                            <th class="text-left font-bold px-4 py-2.5">Customer</th>
                            <th class="text-left font-bold px-4 py-2.5">Contact</th>
                            <th class="text-left font-bold px-4 py-2.5">City</th>
                            <th class="text-left font-bold px-4 py-2.5">Status</th>
                            <th class="text-left font-bold px-4 py-2.5">Follow-up</th>
                            <th class="text-left font-bold px-4 py-2.5">Source</th>
                            <th class="text-right font-bold px-4 py-2.5">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customers as $c)
                            <tr class="border-t border-line hover:bg-mist/70 table-row-press" @click="selectCustomer({{ $c->id }})">
                                <td class="px-4 py-2.5">
                                    <div class="font-semibold">{{ $c->name }}</div>
                                    <div class="text-[11px] text-slate-400">{{ $c->company ?: '—' }}</div>
                                </td>
                                <td class="px-4 py-2.5 text-slate-500">
                                    <div>{{ $c->phone ?: '—' }}</div>
                                    <div class="text-[11px]">{{ $c->email ?: '' }}</div>
                                </td>
                                <td class="px-4 py-2.5 text-slate-500">{{ $c->city ?: '—' }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="inline-flex px-2 py-0.5 rounded text-[11px] font-bold capitalize {{ $statusTone[$c->status] ?? 'bg-mist' }}">{{ $c->status }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-[12px]" data-fu-cell="{{ $c->id }}">
                                    @if($c->follow_up && $c->follow_up_at)
                                        <span class="inline-flex px-2 py-0.5 rounded bg-amber-50 text-amber-800 font-semibold">
                                            {{ $c->follow_up_at->format('d M Y') }}
                                        </span>
                                        <div class="text-[10px] text-slate-400 mt-0.5">
                                            {{ $c->follow_up_has_time ? $c->follow_up_at->format('h:i A') : 'Anytime' }}
                                        </div>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-slate-500">{{ $c->source ?: '—' }}</td>
                                <td class="px-4 py-2.5 text-right" @click.stop>
                                    <div class="inline-flex items-center gap-1.5 justify-end">
                                        <button type="button"
                                                title="Edit"
                                                class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-line text-brand hover:bg-brand-soft transition"
                                                @click="openEditForm(@js([
                                                    'id' => $c->id,
                                                    'name' => $c->name,
                                                    'email' => $c->email,
                                                    'phone' => $c->phone,
                                                    'company' => $c->company,
                                                    'city' => $c->city,
                                                    'source' => $c->source,
                                                    'status' => $c->status,
                                                    'notes' => $c->notes,
                                                    'assigned_to' => $c->assigned_to,
                                                    'meta' => $c->meta ?? (object) [],
                                                ]))">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <form method="POST" action="{{ route('customers.destroy', $c) }}"
                                              onsubmit="return confirm('Delete this customer?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" title="Delete"
                                                    class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-line text-rose-600 hover:bg-rose-50 transition">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">No customers found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($customers->hasPages())
                <div class="mt-3">{{ $customers->links() }}</div>
            @endif
        </div>

        <div class="desk-pane bg-mist/30 p-4"
             x-show="selectedId"
             x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-x-8"
             x-transition:enter-end="opacity-100 translate-x-0"
             x-transition:leave="transition ease-in duration-220"
             x-transition:leave-start="opacity-100 translate-x-0"
             x-transition:leave-end="opacity-0 translate-x-6">
            <template x-if="selectedId">
                <div class="flex h-full min-h-0 rounded-xl border border-line bg-white overflow-hidden shadow-sm" :key="deskKey">
                    <section class="w-[340px] shrink-0 border-r border-line flex flex-col bg-white slide-list">
                        <div class="shrink-0 px-3 py-2.5 border-b border-line flex items-center gap-1 text-[12px]">
                            @php $deskFilter = $filter === 'follow_up_today' ? 'follow_up_today' : 'all'; @endphp
                            <a href="{{ route('customers.index', array_filter(['q' => $q ?: null])) }}"
                               class="px-2.5 py-1 rounded-md font-semibold transition {{ $deskFilter === 'all' ? 'bg-brand-soft text-brand' : 'text-slate-500 hover:bg-mist hover:text-ink' }}">All</a>
                            <a href="{{ route('customers.index', array_filter(['q' => $q ?: null, 'filter' => 'follow_up_today'])) }}"
                               class="px-2.5 py-1 rounded-md font-semibold transition {{ $deskFilter === 'follow_up_today' ? 'bg-amber-100 text-amber-900' : 'text-slate-500 hover:bg-mist hover:text-ink' }}">Follow-up Today</a>
                        </div>
                        <div class="flex-1 overflow-y-auto crm-scroll">
                            @foreach($customers as $c)
                                <button type="button"
                                        class="list-item w-full text-left px-3 py-2.5 border-b border-line"
                                        :class="selectedId == {{ $c->id }} ? 'active' : ''"
                                        @click="selectCustomer({{ $c->id }})">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="font-semibold text-[14px] text-ink truncate">{{ $c->name }}</div>
                                        <div class="text-[11px] text-slate-400 shrink-0">{{ $c->updated_at?->format('g:i A') }}</div>
                                    </div>
                                    <div class="text-[12px] text-slate-500 truncate mt-0.5">{{ $c->company ?: '—' }}</div>
                                    <div class="mt-1.5 flex items-center gap-2 min-w-0">
                                        <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded capitalize shrink-0 {{ $statusTone[$c->status] ?? 'bg-mist text-slate-600' }}">{{ $c->status }}</span>
                                        @if($c->follow_up && $c->follow_up_at)
                                            <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 shrink-0" data-fu-list="{{ $c->id }}">
                                                FU {{ $c->follow_up_at->format('d M') }}
                                            </span>
                                        @else
                                            <span class="hidden text-[10px] font-semibold px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 shrink-0" data-fu-list="{{ $c->id }}"></span>
                                        @endif
                                        <span class="text-[11px] text-slate-400 truncate">{{ trim(($c->source ?: '').' · '.($c->city ?: ''), ' ·') }}</span>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </section>

                    <section class="flex-1 min-w-0 flex flex-col bg-white slide-read">
                        <div class="shrink-0 px-5 py-3 border-b border-line flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-[20px] font-semibold truncate" x-text="title">Customer</div>
                                <div class="text-[12px] text-slate-500">Chat, updates & full details</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button"
                                        class="rounded border border-line px-3 py-1.5 text-[12px] font-semibold text-brand hover:bg-mist"
                                        @click="fetch('{{ url('/customers') }}/'+selectedId,{headers:{Accept:'application/json'}}).then(r=>r.json()).then(d=>openEditForm(d.customer))">Edit</button>
                                <button type="button" @click="closeDesk()" class="w-8 h-8 rounded hover:bg-mist text-slate-500 transition hover:rotate-90 duration-200" title="Close">✕</button>
                            </div>
                        </div>
                        <div class="flex-1 overflow-y-auto crm-scroll" x-html="html"></div>
                    </section>
                </div>
            </template>
        </div>
    </div>

    {{-- Right side form drawer (dynamic fields from DB) --}}
    @php
        $crmFormFieldsJson = $formFields->map(function ($f) {
            return [
                'key' => $f->key,
                'label' => $f->label,
                'type' => $f->type,
                'required' => (bool) $f->required,
                'options' => $f->options ?? [],
            ];
        })->values();
    @endphp
    <script>
        window.CRM_FORM_FIELDS = @json($crmFormFieldsJson);
    </script>
    <div class="fixed inset-0 z-50" x-show="formOpen" x-cloak>
        <div class="absolute inset-0 bg-ink/25 transition-opacity"
             x-show="formOpen"
             x-transition.opacity.duration.200ms
             @click="closeForm()"></div>
        <aside class="absolute right-0 top-0 h-full w-full max-w-[420px] bg-white border-l border-line shadow-xl flex flex-col"
               x-show="formOpen"
               x-transition:enter="transition ease-out duration-300"
               x-transition:enter-start="translate-x-full"
               x-transition:enter-end="translate-x-0"
               x-transition:leave="transition ease-in duration-200"
               x-transition:leave-start="translate-x-0"
               x-transition:leave-end="translate-x-full"
               @click.stop>
            <div class="px-5 py-4 border-b border-line flex items-center justify-between shrink-0">
                <div>
                    <div class="text-[11px] uppercase tracking-[0.14em] text-slate-400 font-bold">Customer form</div>
                    <div class="text-[17px] font-semibold" x-text="formMode === 'edit' ? 'Edit customer' : 'Add customer'"></div>
                </div>
                <button type="button" @click="closeForm()" class="w-8 h-8 rounded hover:bg-mist text-slate-500">✕</button>
            </div>

            <form method="POST"
                  class="flex-1 overflow-y-auto crm-scroll p-5 space-y-3"
                  :action="formMode === 'edit' ? `{{ url('/customers') }}/${form.id}` : `{{ route('customers.store') }}`">
                @csrf
                <template x-if="formMode === 'edit'">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                @foreach($formFields as $field)
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1">
                            {{ $field->label }}@if($field->required) * @endif
                        </label>
                        @if($field->key === 'assigned_to')
                            <select name="assigned_to" x-model="form.assigned_to" class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20" @required($field->required)>
                                <option value="">Unassigned</option>
                                @foreach($agents as $a)
                                    <option value="{{ $a->id }}">{{ $a->name }}</option>
                                @endforeach
                            </select>
                        @elseif($field->type === 'textarea')
                            <textarea name="{{ $field->key }}" x-model="form.{{ $field->key }}" rows="3" class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20" @required($field->required)></textarea>
                        @elseif($field->type === 'select')
                            <select name="{{ $field->key }}" x-model="form.{{ $field->key }}" class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20" @required($field->required)>
                                <option value="">Select</option>
                                @foreach($field->options ?? [] as $opt)
                                    <option value="{{ $opt }}">{{ ucfirst($opt) }}</option>
                                @endforeach
                            </select>
                        @else
                            <input
                                name="{{ $field->key }}"
                                x-model="form.{{ $field->key }}"
                                type="{{ $field->type === 'email' ? 'email' : ($field->type === 'phone' ? 'tel' : 'text') }}"
                                class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20"
                                @required($field->required)
                            >
                        @endif
                    </div>
                @endforeach

                <div class="pt-2 sticky bottom-0 bg-white pb-1">
                    <button type="submit" class="w-full rounded-lg bg-brand text-white font-semibold py-2.5 text-[13px] hover:bg-brand-dark"
                            x-text="formMode === 'edit' ? 'Update customer' : 'Save customer'"></button>
                </div>
            </form>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('crmFilterForm');
    const input = document.getElementById('crmSearch');
    const status = document.getElementById('crmStatus');
    const hint = document.getElementById('crmSearchHint');
    if (!form || !input) return;

    let timer = null;
    const runSearch = () => {
        if (hint) hint.classList.remove('hidden');
        form.submit();
    };

    input.addEventListener('input', () => {
        clearTimeout(timer);
        if (hint) {
            hint.textContent = 'Wait 3 sec…';
            hint.classList.remove('hidden');
        }
        timer = setTimeout(runSearch, 3000);
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(timer);
            runSearch();
        }
    });

    if (status) {
        status.addEventListener('change', () => {
            clearTimeout(timer);
            runSearch();
        });
    }
})();
</script>
@endpush
