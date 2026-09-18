@extends('layouts.app')

@section('title', 'Inventory')
@section('heading', 'Inventory')
@section('subheading', 'GPS, SIM, license keys, fuel sensors — types are managed in Setting')

@section('content')
<div x-data="{
    userStockOpen: false,
    userStockLoading: false,
    userStockType: '',
    userStockRows: [],
    userStockTotals: { total: 0, in_stock: 0 },
    async openUserStock(){
        this.userStockOpen = true;
        this.userStockLoading = true;
        this.userStockRows = [];
        try {
            const res = await fetch(`{{ route('inventory.user-stock') }}?type={{ (int) $typeId }}`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            this.userStockType = data.type || '';
            this.userStockRows = data.users || [];
            this.userStockTotals = data.totals || { total: 0, in_stock: 0 };
        } catch (e) {
            this.userStockRows = [];
        } finally {
            this.userStockLoading = false;
        }
    },
    closeUserStock(){ this.userStockOpen = false; }
}">
@php
    $statusTone = [
        'in_stock' => 'bg-emerald-50 text-emerald-800',
        'assigned' => 'bg-sky-50 text-sky-800',
        'damaged' => 'bg-amber-50 text-amber-800',
        'repair' => 'bg-violet-50 text-violet-800',
    ];
    $statCards = [
        'all' => ['All', 'text-ink', 'total units'],
        'in_stock' => ['In stock', 'text-emerald-700/80', 'available'],
        'assigned' => ['Assigned', 'text-sky-700/80', 'assigned out'],
        'damaged' => ['Damaged', 'text-amber-700/80', 'damaged'],
        'repair' => ['Repair', 'text-violet-700/80', 'in repair'],
    ];
@endphp

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap items-center gap-1 rounded-xl border border-line bg-white p-1 w-fit">
        @forelse($types as $type)
            @php $tabAll = (int) ($statusTotals[$type->id]['all'] ?? 0); @endphp
            <a href="{{ route('inventory.index', ['type' => $type->id]) }}"
               class="px-3.5 py-1.5 rounded-lg text-[13px] font-semibold uppercase tracking-wide transition
                      {{ (int) $typeId === (int) $type->id ? 'bg-brand-soft text-brand' : 'text-slate-500 hover:bg-mist' }}">
                {{ $type->name }}
                <span class="ml-1.5 text-[10px] font-bold normal-case tracking-normal
                             {{ (int) $typeId === (int) $type->id ? 'text-brand/70' : 'text-slate-400' }}">
                    {{ $tabAll }}
                </span>
            </a>
        @empty
            <span class="px-3 py-1.5 text-[12px] text-slate-400">No inventory types. Add them in Setting → Inventory.</span>
        @endforelse
        @if(auth()->user()->isAdmin())
            <a href="{{ route('settings.index', ['tab' => 'inventory']) }}"
               class="px-3 py-1.5 rounded-lg text-[12px] font-semibold text-brand hover:bg-mist">+ Types</a>
        @endif
    </div>

    @if($types->isNotEmpty() && $typeId)
        <button type="button" @click="openUserStock()"
                class="inline-flex items-center gap-2 rounded-xl border border-line bg-white px-3.5 py-2 text-[13px] font-semibold text-ink hover:bg-brand-soft hover:text-brand hover:border-brand/30 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m4-4a4 4 0 11-8 0 4 4 0 018 0zm6 4a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            User wise stock
        </button>
    @endif
</div>

@if($types->isNotEmpty())
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-3 mb-4">
        @foreach($statCards as $key => [$label, $tone, $hint])
            <div class="stat-card rounded-2xl border border-line bg-white px-4 py-3.5">
                <div class="text-[11px] font-bold uppercase tracking-wide {{ $tone }}">{{ $label }}</div>
                <div class="text-[28px] font-extrabold text-ink leading-none mt-1">{{ (int) ($typeCounts[$key] ?? 0) }}</div>
                <div class="text-[11px] text-slate-400 mt-1">{{ $hint }}</div>
            </div>
        @endforeach
    </div>
@endif

<div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
    <div class="rounded-2xl border border-line bg-white overflow-hidden h-fit shadow-sm"
         x-data="{ status: '{{ old('status', 'in_stock') }}' }">
        <div class="px-3.5 py-2.5 border-b border-line bg-mist/50 flex items-center justify-between gap-2">
            <h3 class="text-[14px] font-extrabold text-ink leading-tight">Add item</h3>
            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">New</span>
        </div>

        @if($types->isEmpty())
            <p class="px-3.5 py-4 text-[12px] text-slate-400">Add inventory types in Setting first.</p>
        @else
            <form method="POST" action="{{ route('inventory.store') }}" class="p-3.5 space-y-2.5">
                @csrf

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 mb-0.5">Type *</label>
                        <select name="inventory_type_id" required
                                class="w-full rounded-lg border border-line bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                            @foreach($types as $type)
                                <option value="{{ $type->id }}" @selected((int) old('inventory_type_id', $typeId) === (int) $type->id)>{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 mb-0.5">Name *</label>
                        <input name="name" required maxlength="160" value="{{ old('name') }}"
                               placeholder="Item name / label"
                               class="w-full rounded-lg border border-line px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                    </div>
                </div>
                <div class="grid grid-cols-[1fr_72px] gap-2">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 mb-0.5">Serial / code</label>
                        <input name="code" maxlength="120" value="{{ old('code') }}"
                               placeholder="Optional"
                               class="w-full rounded-lg border border-line px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 mb-0.5">Qty *</label>
                        <input name="quantity" type="number" min="0" value="{{ old('quantity', 1) }}" required
                               class="w-full rounded-lg border border-line px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-500 mb-1">Status *</label>
                    <div class="flex flex-wrap gap-1">
                        @foreach(\App\Models\InventoryItem::STATUSES as $value => $label)
                            <label class="relative cursor-pointer">
                                <input type="radio" name="status" value="{{ $value }}" class="peer sr-only"
                                       x-model="status" @checked(old('status', 'in_stock') === $value)>
                                <span class="inline-flex items-center rounded-md border border-line px-2 py-1 text-[11px] font-semibold text-slate-600
                                             peer-checked:border-brand peer-checked:bg-brand-soft peer-checked:text-brand transition">
                                    {{ $label }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div x-show="status === 'in_stock'" x-cloak>
                    <label class="block text-[10px] font-bold text-emerald-800 mb-0.5">Stock with user *</label>
                    <select name="held_by_user_id" :required="status === 'in_stock'"
                            class="w-full rounded-lg border border-emerald-200 bg-emerald-50/40 px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                        <option value="">Select user</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected((int) old('held_by_user_id') === (int) $user->id)>
                                {{ $user->name }}{{ $user->username ? ' ('.$user->username.')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div x-show="status === 'assigned'" x-cloak>
                    <label class="block text-[10px] font-bold text-sky-800 mb-0.5">Assigned to *</label>
                    <input name="assigned_to_name" maxlength="160" value="{{ old('assigned_to_name') }}"
                           :required="status === 'assigned'"
                           placeholder="Person / company name"
                           class="w-full rounded-lg border border-sky-200 bg-sky-50/40 px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-500 mb-0.5">Notes</label>
                    <textarea name="notes" rows="1" placeholder="Optional"
                              class="w-full rounded-lg border border-line px-2.5 py-1.5 text-[13px] resize-y focus:outline-none focus:ring-2 focus:ring-brand/20">{{ old('notes') }}</textarea>
                </div>

                <button class="w-full rounded-lg bg-brand hover:bg-brand-dark text-white font-semibold py-2 text-[13px] transition">
                    Save item
                </button>
            </form>
        @endif
    </div>

    <div class="xl:col-span-2 rounded-2xl border border-line bg-white overflow-hidden"
         x-data="{
            editOpen: false,
            form: {},
            historyOpen: false,
            historyLoading: false,
            historyTitle: '',
            histories: [],
            openEdit(row){ this.form = {...row}; this.editOpen = true; },
            closeEdit(){ this.editOpen = false; },
            async openHistory(id, name){
                this.historyOpen = true;
                this.historyLoading = true;
                this.historyTitle = name || 'Item';
                this.histories = [];
                try {
                    const res = await fetch(`{{ url('/inventory') }}/${id}/history`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await res.json();
                    this.historyTitle = data.item || name;
                    this.histories = data.histories || [];
                } catch (e) {
                    this.histories = [];
                } finally {
                    this.historyLoading = false;
                }
            },
            closeHistory(){ this.historyOpen = false; }
         }">
        <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead class="bg-mist/80 text-slate-500 text-[11px] uppercase tracking-wide">
                    <tr>
                        <th class="text-left font-bold px-4 py-2.5">Name</th>
                        <th class="text-left font-bold px-4 py-2.5">Type</th>
                        <th class="text-left font-bold px-4 py-2.5">Code</th>
                        <th class="text-left font-bold px-4 py-2.5">Qty</th>
                        <th class="text-left font-bold px-4 py-2.5">Status</th>
                        <th class="text-right font-bold px-4 py-2.5">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr class="border-t border-line hover:bg-mist/30">
                            <td class="px-4 py-2.5">
                                <div class="font-semibold">{{ $item->name }}</div>
                                @if($item->notes)
                                    <div class="text-[11px] text-slate-400 truncate max-w-[220px]">{{ $item->notes }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-slate-500">{{ $item->type?->name }}</td>
                            <td class="px-4 py-2.5 text-slate-500">{{ $item->code ?: '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-500">{{ $item->quantity }}</td>
                            <td class="px-4 py-2.5">
                                <span class="inline-flex text-[11px] font-bold px-2 py-1 rounded-md {{ $statusTone[$item->status] ?? 'bg-mist' }}">
                                    {{ $item->statusLabel() }}
                                </span>
                                @if($item->status === 'in_stock' && $item->heldBy)
                                    <div class="text-[11px] text-slate-500 mt-1">
                                        Stock with <span class="font-semibold text-ink">{{ $item->heldBy->name }}</span>
                                    </div>
                                @elseif($item->status === 'assigned' && $item->assigned_to_name)
                                    <div class="text-[11px] text-slate-500 mt-1">
                                        Assigned to <span class="font-semibold text-ink">{{ $item->assigned_to_name }}</span>
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <div class="inline-flex items-center gap-1.5 justify-end">
                                    <button type="button" title="History"
                                            @click="openHistory({{ $item->id }}, @js($item->name))"
                                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-line text-slate-600 hover:bg-mist">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </button>
                                    <button type="button" title="Edit"
                                            @click="openEdit(@js([
                                                'id' => $item->id,
                                                'inventory_type_id' => $item->inventory_type_id,
                                                'name' => $item->name,
                                                'code' => $item->code,
                                                'quantity' => $item->quantity,
                                                'status' => $item->status,
                                                'held_by_user_id' => $item->held_by_user_id,
                                                'assigned_to_name' => $item->assigned_to_name,
                                                'notes' => $item->notes,
                                            ]))"
                                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-line text-brand hover:bg-brand-soft">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <form method="POST" action="{{ route('inventory.destroy', $item) }}" onsubmit="return confirm('Delete item?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" title="Delete"
                                                class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-line text-rose-600 hover:bg-rose-50">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">No items in this type yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $items->links() }}</div>

        <div class="fixed inset-0 z-[60]" x-show="editOpen" x-cloak>
            <div class="absolute inset-0 bg-ink/25" @click="closeEdit()"></div>
            <aside class="absolute right-0 top-0 h-full w-full max-w-[420px] bg-white border-l border-line shadow-xl flex flex-col"
                   x-show="editOpen"
                   x-transition:enter="transition ease-out duration-300"
                   x-transition:enter-start="translate-x-full"
                   x-transition:enter-end="translate-x-0"
                   @click.stop>
                <div class="px-5 py-4 border-b border-line flex items-center justify-between">
                    <div>
                        <div class="text-[11px] uppercase tracking-[0.14em] text-slate-400 font-bold">Edit item</div>
                        <div class="text-[17px] font-semibold" x-text="form.name || 'Item'"></div>
                    </div>
                    <button type="button" @click="closeEdit()" class="w-8 h-8 rounded hover:bg-mist">✕</button>
                </div>
                <form method="POST" class="flex-1 flex flex-col min-h-0" :action="`{{ url('/inventory') }}/${form.id}`">
                    @csrf
                    <input type="hidden" name="_method" value="PUT">
                    <div class="flex-1 overflow-y-auto crm-scroll p-5 space-y-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 mb-1">Type *</label>
                            <select name="inventory_type_id" x-model="form.inventory_type_id" required class="w-full rounded-lg border border-line px-3 py-2 text-[13px]">
                                @foreach($types as $type)
                                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 mb-1">Name *</label>
                            <input name="name" x-model="form.name" required class="w-full rounded-lg border border-line px-3 py-2 text-[13px]">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 mb-1">Code</label>
                            <input name="code" x-model="form.code" class="w-full rounded-lg border border-line px-3 py-2 text-[13px]">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 mb-1">Quantity *</label>
                            <input name="quantity" type="number" min="0" x-model="form.quantity" required class="w-full rounded-lg border border-line px-3 py-2 text-[13px]">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 mb-1">Status *</label>
                            <select name="status" x-model="form.status" required class="w-full rounded-lg border border-line px-3 py-2 text-[13px]">
                                @foreach(\App\Models\InventoryItem::STATUSES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div x-show="form.status === 'in_stock'" x-cloak>
                            <label class="block text-[11px] font-bold text-slate-500 mb-1">Stock with user *</label>
                            <select name="held_by_user_id" x-model="form.held_by_user_id"
                                    :required="form.status === 'in_stock'"
                                    class="w-full rounded-lg border border-line px-3 py-2 text-[13px]">
                                <option value="">Select user</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">
                                        {{ $user->name }}{{ $user->username ? ' ('.$user->username.')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div x-show="form.status === 'assigned'" x-cloak>
                            <label class="block text-[11px] font-bold text-slate-500 mb-1">Assigned to *</label>
                            <input name="assigned_to_name" x-model="form.assigned_to_name" maxlength="160"
                                   :required="form.status === 'assigned'"
                                   placeholder="Enter name"
                                   class="w-full rounded-lg border border-line px-3 py-2 text-[13px]">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 mb-1">Notes</label>
                            <textarea name="notes" x-model="form.notes" rows="3" class="w-full rounded-lg border border-line px-3 py-2 text-[13px]"></textarea>
                        </div>
                    </div>
                    <div class="shrink-0 border-t border-line px-5 py-3">
                        <button class="w-full rounded-lg bg-brand text-white font-semibold py-2.5 text-[13px]">Update item</button>
                    </div>
                </form>
            </aside>
        </div>

        <div class="fixed inset-0 z-[60]" x-show="historyOpen" x-cloak>
            <div class="absolute inset-0 bg-ink/25" @click="closeHistory()"></div>
            <aside class="absolute right-0 top-0 h-full w-full max-w-[440px] bg-white border-l border-line shadow-xl flex flex-col"
                   x-show="historyOpen"
                   x-transition:enter="transition ease-out duration-300"
                   x-transition:enter-start="translate-x-full"
                   x-transition:enter-end="translate-x-0"
                   @click.stop>
                <div class="px-5 py-4 border-b border-line flex items-center justify-between">
                    <div>
                        <div class="text-[11px] uppercase tracking-[0.14em] text-slate-400 font-bold">History</div>
                        <div class="text-[17px] font-semibold" x-text="historyTitle"></div>
                    </div>
                    <button type="button" @click="closeHistory()" class="w-8 h-8 rounded hover:bg-mist">✕</button>
                </div>
                <div class="flex-1 overflow-y-auto crm-scroll">
                    <template x-if="historyLoading">
                        <div class="px-5 py-10 text-center text-slate-400 text-sm">Loading…</div>
                    </template>
                    <template x-if="!historyLoading && histories.length === 0">
                        <div class="px-5 py-10 text-center text-slate-400 text-sm">No history yet</div>
                    </template>
                    <div class="divide-y divide-line" x-show="!historyLoading && histories.length">
                        <template x-for="row in histories" :key="row.id">
                            <div class="px-5 py-3.5">
                                <div class="flex items-center gap-2 mb-1 flex-wrap">
                                    <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold uppercase"
                                          :class="row.action === 'created' ? 'bg-emerald-50 text-emerald-700' : 'bg-sky-50 text-sky-700'"
                                          x-text="row.action"></span>
                                    <span class="text-[13px] font-semibold" x-text="row.title"></span>
                                    <span class="ml-auto text-[11px] text-slate-400" x-text="row.ago"></span>
                                </div>
                                <div class="text-[12px] text-slate-600 whitespace-pre-wrap" x-text="row.body" x-show="row.body"></div>
                                <template x-if="row.changes && row.changes.length">
                                    <ul class="mt-2 space-y-1">
                                        <template x-for="(c, i) in row.changes" :key="i">
                                            <li class="text-[12px] text-slate-600">
                                                <span class="font-semibold text-ink" x-text="c.label"></span>:
                                                <span class="text-slate-400" x-text="c.from"></span>
                                                <span class="text-slate-400"> → </span>
                                                <span class="font-medium" x-text="c.to"></span>
                                            </li>
                                        </template>
                                    </ul>
                                </template>
                                <div class="text-[11px] text-slate-400 mt-1.5">
                                    <span x-text="row.user"></span>
                                    <span> · </span>
                                    <span x-text="row.at"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>

<div class="fixed inset-0 z-[70]" x-show="userStockOpen" x-cloak>
    <div class="absolute inset-0 bg-ink/25" @click="closeUserStock()"></div>
    <aside class="absolute right-0 top-0 h-full w-full max-w-[560px] bg-white border-l border-line shadow-xl flex flex-col"
           x-show="userStockOpen"
           x-transition:enter="transition ease-out duration-300"
           x-transition:enter-start="translate-x-full"
           x-transition:enter-end="translate-x-0"
           @click.stop>
        <div class="px-5 py-4 border-b border-line flex items-center justify-between">
            <div>
                <div class="text-[11px] uppercase tracking-[0.14em] text-slate-400 font-bold">User wise stock</div>
                <div class="text-[17px] font-semibold" x-text="userStockType || 'Inventory'"></div>
            </div>
            <button type="button" @click="closeUserStock()" class="w-8 h-8 rounded hover:bg-mist">✕</button>
        </div>
        <div class="px-5 py-3 border-b border-line grid grid-cols-2 gap-3 bg-mist/40">
            <div>
                <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Total</div>
                <div class="text-[22px] font-extrabold text-ink" x-text="userStockTotals.total"></div>
            </div>
            <div>
                <div class="text-[10px] font-bold uppercase tracking-wide text-emerald-700/80">In stock</div>
                <div class="text-[22px] font-extrabold text-ink" x-text="userStockTotals.in_stock"></div>
            </div>
        </div>
        <div class="flex-1 overflow-y-auto crm-scroll">
            <template x-if="userStockLoading">
                <div class="px-5 py-10 text-center text-slate-400 text-sm">Loading…</div>
            </template>
            <template x-if="!userStockLoading && userStockRows.length === 0">
                <div class="px-5 py-10 text-center text-slate-400 text-sm">No user stock for this type yet</div>
            </template>
            <div x-show="!userStockLoading && userStockRows.length">
                <template x-for="row in userStockRows" :key="row.user_id">
                    <div class="border-b border-line">
                        <div class="px-5 py-2.5 bg-mist/50 flex items-center justify-between gap-3">
                            <div>
                                <div class="text-[13px] font-semibold" x-text="row.name"></div>
                                <div class="text-[11px] text-slate-400" x-text="row.username ? '@' + row.username : ''"></div>
                            </div>
                            <div class="flex items-center gap-3 text-right shrink-0">
                                <div>
                                    <div class="text-[10px] uppercase text-slate-400 font-bold">Total</div>
                                    <div class="text-[14px] font-extrabold" x-text="row.total"></div>
                                </div>
                                <div>
                                    <div class="text-[10px] uppercase text-emerald-700/80 font-bold">In stock</div>
                                    <div class="inline-flex text-[12px] font-bold px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800" x-text="row.in_stock"></div>
                                </div>
                            </div>
                        </div>
                        <table class="w-full text-[12px]">
                            <thead>
                                <tr class="text-slate-400 uppercase tracking-wide text-[10px]">
                                    <th class="text-left font-bold px-5 py-1.5">Type / item</th>
                                    <th class="text-left font-bold px-3 py-1.5">Code</th>
                                    <th class="text-right font-bold px-3 py-1.5">Qty</th>
                                    <th class="text-right font-bold px-5 py-1.5">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="item in row.items" :key="item.id">
                                    <tr class="border-t border-line/70 hover:bg-mist/20">
                                        <td class="px-5 py-2">
                                            <div class="font-semibold text-ink" x-text="item.type"></div>
                                            <div class="text-[11px] text-slate-500" x-text="item.name"></div>
                                        </td>
                                        <td class="px-3 py-2 text-slate-600 font-mono text-[11px]" x-text="item.code"></td>
                                        <td class="px-3 py-2 text-right font-bold" x-text="item.quantity"></td>
                                        <td class="px-5 py-2 text-right">
                                            <span class="text-[11px] font-semibold text-slate-600" x-text="item.status_label"></span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>
        </div>
    </aside>
</div>
</div>
@endsection
