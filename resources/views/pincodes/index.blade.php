@extends('layouts.app')

@section('title', 'Pincode Manager')
@section('heading', 'Pincode Manager')
@section('subheading', 'Fields, table columns & Excel columns auto-customize from Setting → Pincode Fields')

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        <button type="button"
                @click="$dispatch('open-pincode-excel')"
                class="h-9 inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 px-3 text-[12px] font-semibold transition shadow-2xs">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm0 4h16M4 13h16M4 17h16M9 8v12M15 8v12"/></svg>
            <span>Excel Import / Export</span>
        </button>
        @if(auth()->user()->isAdmin())
            <a href="{{ route('settings.index', ['tab' => 'pincode-fields']) }}"
               class="h-9 inline-flex items-center gap-1.5 rounded-lg border border-brand/30 bg-brand-soft text-brand hover:bg-brand hover:text-white px-3 text-[12px] font-semibold transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span>Customize Fields</span>
            </a>
        @endif
    </div>
@endsection

@section('content')
@php
    $pageIds = $pincodes->pluck('id')->map(fn ($id) => (int) $id)->values();

    $pincodesJson = $pincodes->getCollection()->map(function ($p) use ($formFields) {
        $row = [
            'id' => $p->id,
            'pincode' => $p->pincode,
            'area' => $p->area ?? '',
            'city' => $p->city ?? '',
            'state' => $p->state ?? '',
            'zone' => $p->zone ?? '',
            'assigned_to' => $p->assigned_to ?? '',
            'notes' => $p->notes ?? '',
        ];
        foreach ($formFields as $field) {
            $val = $p->fieldValue($field->key);
            $row[$field->key] = is_array($val) ? implode(', ', $val) : (string) ($val ?? '');
        }
        return $row;
    })->values();

    $createDefaults = [];
    foreach ($formFields as $f) {
        $def = old($f->key, '');
        if ($def === '') {
            if ($f->type === 'select' && $f->auto_select && ! empty($f->options)) {
                $def = $f->options[0];
            } elseif ($f->type === 'date') {
                $def = now()->toDateString();
            }
        }
        $createDefaults[$f->key] = $def;
    }
@endphp

<div x-data="pincodeManager({
        pageIds: @js($pageIds),
        createDefaults: @js($createDefaults)
     })"
     @open-pincode-excel.window="excelOpen = true">

    {{-- Search & Filter Bar --}}
    <div class="mb-4 rounded-2xl border border-line bg-white p-3 shadow-2xs flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('pincodes.index') }}" id="pincodeFilterForm" class="flex flex-1 flex-wrap sm:flex-nowrap items-center gap-2 min-w-[260px]">
            <div class="crm-toolbar-group w-full">
                <label class="crm-toolbar-search">
                    <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"/></svg>
                    <input type="text" name="q" value="{{ $q }}"
                           placeholder="Search pincode, area, city, state, zone…"
                           autocomplete="off">
                </label>
                <span class="crm-toolbar-split"></span>
                <select name="assigned_to" onchange="this.form.submit()" class="crm-toolbar-select" title="Filter by Executive">
                    <option value="">All Users</option>
                    @foreach($agents as $a)
                        <option value="{{ $a->id }}" @selected($assignedTo === $a->id)>{{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="h-9 px-3.5 rounded-xl bg-brand hover:bg-brand-dark text-white text-[12px] font-semibold transition shrink-0">
                Search
            </button>
            @if($q !== '' || $assignedTo)
                <a href="{{ route('pincodes.index') }}"
                   class="h-9 px-3 inline-flex items-center justify-center rounded-xl border border-line bg-mist/60 hover:bg-mist text-slate-700 text-[12px] font-semibold transition shrink-0">
                    Reset
                </a>
            @endif
        </form>

        <div class="flex items-center gap-2 text-[12px] text-slate-500 font-medium">
            <span>Showing <strong class="text-ink">{{ $pincodes->total() }}</strong> record{{ $pincodes->total() === 1 ? '' : 's' }}</span>
        </div>
    </div>

    {{-- Main Grid: Left Create Form + Right Table --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 items-start">
        {{-- Left Column: Add Pincode Card --}}
        <div class="rounded-2xl border border-sky-200/80 bg-sky-50/75 p-4 shadow-2xs">
            <div class="flex items-center justify-between gap-2 mb-3">
                <div>
                    <h3 class="text-sm font-extrabold text-sky-950">Add Pincode</h3>
                    <p class="text-[11px] text-slate-500">Enter pincode details or auto-fill via 6-digit PIN</p>
                </div>
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('settings.index', ['tab' => 'pincode-fields']) }}" class="text-[11px] font-semibold text-brand hover:underline shrink-0">
                        Manage fields
                    </a>
                @endif
            </div>

            <form method="POST" action="{{ route('pincodes.store') }}" class="space-y-2.5">
                @csrf
                @forelse($formFields as $field)
                    <div>
                        <div class="flex items-center justify-between gap-1 mb-1">
                            <label class="block text-[11px] font-bold text-slate-600">
                                {{ $field->label }}@if($field->required) <span class="text-rose-500">*</span> @endif
                            </label>
                            @if($field->key === 'pincode')
                                <button type="button"
                                        @click="lookupPostalPincode(createForm, 'create')"
                                        :disabled="lookupLoadingCreate"
                                        class="text-[10.5px] font-bold text-brand hover:underline inline-flex items-center gap-1 disabled:opacity-50">
                                    <span x-text="lookupLoadingCreate ? 'Fetching…' : '✨ Auto-fill Area/City/State'"></span>
                                </button>
                            @endif
                        </div>

                        @if($field->key === 'pincode')
                            <input name="pincode"
                                   x-model="createForm.pincode"
                                   @blur="if (String(createForm.pincode || '').trim().length === 6 && !createForm.city) lookupPostalPincode(createForm, 'create')"
                                   required
                                   placeholder="e.g. 751001"
                                   class="w-full rounded-xl border border-line bg-white px-3 py-2 text-[13px] font-semibold focus:outline-none focus:ring-2 focus:ring-brand/20">
                            <p x-show="lookupMessageCreate" x-cloak class="text-[10.5px] mt-0.5 font-medium"
                               :class="lookupErrorCreate ? 'text-rose-600' : 'text-emerald-700'"
                               x-text="lookupMessageCreate"></p>
                        @elseif($field->key === 'assigned_to')
                            <select name="assigned_to" x-model="createForm.assigned_to" @required($field->required)
                                    class="w-full rounded-xl border border-line bg-white px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                                <option value="">Unassigned</option>
                                @foreach($agents as $a)
                                    <option value="{{ $a->id }}">{{ $a->name }}{{ $a->username ? ' ('.$a->username.')' : '' }}</option>
                                @endforeach
                            </select>
                        @elseif($field->type === 'select')
                            <select name="{{ $field->key }}" x-model="createForm['{{ $field->key }}']" @required($field->required)
                                    class="w-full rounded-xl border border-line bg-white px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                                @unless($field->auto_select && !empty($field->options))
                                    <option value="">Select {{ $field->label }}</option>
                                @endunless
                                @foreach($field->options ?? [] as $opt)
                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                @endforeach
                            </select>
                        @elseif($field->type === 'textarea')
                            <textarea name="{{ $field->key }}" x-model="createForm['{{ $field->key }}']" rows="2" @required($field->required)
                                      placeholder="{{ $field->label }}"
                                      class="w-full rounded-xl border border-line bg-white px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20"></textarea>
                        @elseif($field->type === 'phone')
                            <input name="{{ $field->key }}" x-model="createForm['{{ $field->key }}']"
                                   type="tel" inputmode="tel" pattern="[0-9+\-\s()]*"
                                   oninput="this.value = this.value.replace(/[^0-9+\-\s()]/g, '')"
                                   @required($field->required)
                                   placeholder="{{ $field->label }}"
                                   class="w-full rounded-xl border border-line bg-white px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                        @elseif($field->type === 'number')
                            <input name="{{ $field->key }}" x-model="createForm['{{ $field->key }}']"
                                   type="number" step="any" inputmode="decimal"
                                   @required($field->required)
                                   placeholder="{{ $field->label }}"
                                   class="w-full rounded-xl border border-line bg-white px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                        @elseif($field->type === 'date')
                            <input name="{{ $field->key }}" x-model="createForm['{{ $field->key }}']"
                                   type="date" @required($field->required)
                                   class="w-full rounded-xl border border-line bg-white px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                        @elseif($field->type === 'time')
                            <input name="{{ $field->key }}" x-model="createForm['{{ $field->key }}']"
                                   type="time" @required($field->required)
                                   class="w-full rounded-xl border border-line bg-white px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                        @else
                            <input name="{{ $field->key }}" x-model="createForm['{{ $field->key }}']"
                                   type="{{ $field->type === 'email' ? 'email' : 'text' }}"
                                   @required($field->required)
                                   placeholder="{{ $field->label }}"
                                   class="w-full rounded-xl border border-line bg-white px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                        @endif
                    </div>
                @empty
                    <p class="text-[12px] text-slate-400">No form fields enabled. Enable them in Setting → Pincode Fields.</p>
                @endforelse

                @if($formFields->isNotEmpty())
                    <button type="submit" class="w-full rounded-xl bg-brand hover:bg-brand-dark text-white font-bold py-2.5 text-[13px] transition shadow-xs">
                        Save Pincode
                    </button>
                @endif
            </form>
        </div>

        {{-- Right Column: Customizable Pincode Table --}}
        <div class="xl:col-span-2 rounded-2xl border border-emerald-200/80 bg-emerald-50/40 overflow-hidden shadow-2xs">
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="bg-emerald-100/75 text-emerald-950 text-[11px] uppercase tracking-wide border-b border-emerald-200/80">
                        <tr>
                            <th class="text-left font-bold px-3 py-2.5 w-10">
                                <button type="button" class="crm-check"
                                        :class="pageSelected() ? 'on' : ''"
                                        @click.stop="togglePage()"
                                        title="Select page">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                </button>
                            </th>
                            @foreach($tableFields as $field)
                                <th class="text-left font-bold px-4 py-2.5 whitespace-nowrap">{{ $field->label }}</th>
                            @endforeach
                            @if(auth()->user()->isAdmin() || auth()->user()->canEditCustomers() || auth()->user()->canDeleteCustomers())
                                <th class="text-right font-bold px-4 py-2.5 whitespace-nowrap">Action</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-line">
                        @forelse($pincodes as $p)
                            <tr class="hover:bg-mist/40 transition"
                                :class="isSelected({{ (int) $p->id }}) ? 'bg-brand-soft/35' : ''">
                                <td class="px-3 py-2.5">
                                    <button type="button" class="crm-check"
                                            :class="isSelected({{ (int) $p->id }}) ? 'on' : ''"
                                            @click.stop="toggleSelect({{ (int) $p->id }}, $event)"
                                            title="Select row">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                </td>

                                @foreach($tableFields as $field)
                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        @if($field->key === 'pincode')
                                            <div class="inline-flex items-center gap-1.5 font-mono font-extrabold text-[13.5px] text-ink bg-slate-100 border border-slate-200 px-2.5 py-0.5 rounded-lg">
                                                <svg class="w-3.5 h-3.5 text-brand shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                                <span>{{ $p->pincode }}</span>
                                            </div>
                                        @elseif($field->key === 'assigned_to')
                                            <span class="text-slate-600 font-medium">{{ $p->assignee?->name ?: '—' }}</span>
                                        @else
                                            @php
                                                $val = $p->fieldValue($field->key);
                                                $strVal = is_array($val) ? implode(', ', $val) : trim((string) ($val ?? ''));
                                                $isMapCol = str_contains(strtolower($field->key), 'map') || str_contains(strtolower($field->label), 'map');
                                                $isUrlCol = (bool) filter_var($strVal, FILTER_VALIDATE_URL) || (bool) preg_match('~^https?://~i', $strVal);
                                            @endphp
                                            @if($strVal !== '')
                                                @if($isMapCol || $isUrlCol)
                                                    @php
                                                        $colHref = $isUrlCol
                                                            ? $strVal
                                                            : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($strVal);
                                                    @endphp
                                                    <a href="{{ $colHref }}" target="_blank" rel="noopener noreferrer"
                                                       class="inline-flex items-center gap-1 rounded-md border border-brand/30 bg-brand-soft hover:bg-brand hover:text-white text-brand px-2 py-0.5 text-[11px] font-bold transition">
                                                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                                        <span>{{ $isMapCol ? 'Open Map' : 'Open URL' }}</span>
                                                    </a>
                                                @else
                                                    <span class="text-slate-700">{{ $strVal }}</span>
                                                @endif
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        @endif
                                    </td>
                                @endforeach

                                @if(auth()->user()->isAdmin() || auth()->user()->canEditCustomers() || auth()->user()->canDeleteCustomers())
                                    <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                        <div class="inline-flex items-center gap-1.5 justify-end">
                                            @if(auth()->user()->isAdmin() || auth()->user()->canEditCustomers())
                                                <button type="button" title="Edit Pincode"
                                                        @click="openEdit(@js($pincodesJson[$loop->index]))"
                                                        class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-line text-brand hover:bg-brand-soft transition">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                </button>
                                            @endif
                                            @if(auth()->user()->isAdmin() || auth()->user()->canDeleteCustomers())
                                                <form method="POST" action="{{ route('pincodes.destroy', $p) }}"
                                                      onsubmit="return confirm('Delete pincode {{ $p->pincode }}?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" title="Delete"
                                                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-line text-rose-600 hover:bg-rose-50 transition">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $tableFields->count() + 1 + ((auth()->user()->isAdmin() || auth()->user()->canEditCustomers() || auth()->user()->canDeleteCustomers()) ? 1 : 0) }}" class="px-4 py-12 text-center text-slate-400">
                                    No pincodes found. Add a pincode on the left or import from Excel.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($pincodes->hasPages())
                <div class="p-3 border-t border-line bg-white">{{ $pincodes->links() }}</div>
            @endif
        </div>
    </div>

    {{-- Floating Bulk Action Bar --}}
    <form method="POST" action="{{ route('pincodes.bulk') }}"
          x-show="selectedIds.length > 0"
          x-cloak
          x-transition:enter="transition ease-out duration-200"
          x-transition:enter-start="opacity-0 translate-y-4 scale-95"
          x-transition:enter-end="opacity-100 translate-y-0 scale-100"
          class="fixed bottom-4 left-3 right-3 sm:left-1/2 sm:right-auto sm:-translate-x-1/2 z-40 flex flex-wrap sm:flex-nowrap items-center justify-center gap-2 rounded-2xl sm:rounded-full bg-white border border-line shadow-[0_16px_40px_rgba(32,31,30,.22)] px-3 py-2"
          @submit="prepareBulkSubmit($event)">
        @csrf
        <input type="hidden" name="action_type" x-model="bulkActionType">
        <span class="inline-flex items-center justify-center min-w-7 h-7 rounded-full bg-brand text-white text-[12px] font-bold px-2" x-text="selectedIds.length"></span>
        <span class="text-[12px] font-semibold text-slate-700 pr-1">Selected</span>

        {{-- Bulk Assign --}}
        <select name="assigned_to" class="rounded-full border border-line px-3 py-1.5 text-[12.5px] bg-mist/50 focus:outline-none">
            <option value="">Unassigned</option>
            @foreach($agents as $a)
                <option value="{{ $a->id }}">{{ $a->name }}</option>
            @endforeach
        </select>
        <button type="submit" @click="bulkActionType = 'assign'"
                class="rounded-full bg-brand hover:bg-brand-dark text-white font-semibold px-3 py-1.5 text-[12px] transition">
            Assign
        </button>

        <span class="w-px h-5 bg-line hidden sm:block"></span>

        {{-- Bulk Delete --}}
        <button type="submit"
                @click="if (!confirm('Delete ' + selectedIds.length + ' selected pincode(s)?')) { $event.preventDefault(); return; } bulkActionType = 'delete'"
                class="rounded-full bg-rose-600 hover:bg-rose-700 text-white font-semibold px-3 py-1.5 text-[12px] transition">
            Delete
        </button>

        <button type="button" title="Clear selection" @click="clearSelection()"
                class="w-7 h-7 rounded-full text-slate-400 hover:bg-mist hover:text-ink">✕</button>
    </form>

    {{-- Right Edit Slide-Over Drawer --}}
    <div class="fixed inset-0 z-[60]" x-show="editOpen" x-cloak>
        <div class="absolute inset-0 bg-ink/25" x-show="editOpen" x-transition.opacity.duration.200ms @click="closeEdit()"></div>
        <aside class="absolute right-0 top-0 h-full w-full max-w-[420px] bg-white border-l border-line shadow-xl flex flex-col"
               x-show="editOpen"
               x-transition:enter="transition ease-out duration-300"
               x-transition:enter-start="translate-x-full"
               x-transition:enter-end="translate-x-0"
               x-transition:leave="transition ease-in duration-200"
               x-transition:leave-start="translate-x-0"
               x-transition:leave-end="translate-x-full"
               @click.stop>
            <div class="px-5 py-4 border-b border-line flex items-center justify-between shrink-0">
                <div class="min-w-0">
                    <div class="text-[11px] uppercase tracking-[0.14em] text-slate-400 font-bold">Edit Pincode</div>
                    <div class="text-[17px] font-semibold truncate" x-text="editForm.pincode ? ('PIN ' + editForm.pincode) : 'Pincode'"></div>
                </div>
                <button type="button" @click="closeEdit()" class="w-8 h-8 rounded hover:bg-mist text-slate-500">✕</button>
            </div>

            <form method="POST" class="flex-1 flex flex-col min-h-0"
                  :action="`{{ url('/pincodes') }}/${editForm.id}`">
                @csrf
                <input type="hidden" name="_method" value="PUT">
                <div class="flex-1 overflow-y-auto crm-scroll p-5 space-y-3">
                    @foreach($formFields as $field)
                        <div>
                            <div class="flex items-center justify-between gap-1 mb-1">
                                <label class="block text-[11px] font-bold text-slate-500">
                                    {{ $field->label }}@if($field->required) <span class="text-rose-500">*</span> @endif
                                </label>
                                @if($field->key === 'pincode')
                                    <button type="button"
                                            @click="lookupPostalPincode(editForm, 'edit')"
                                            :disabled="lookupLoadingEdit"
                                            class="text-[10.5px] font-bold text-brand hover:underline inline-flex items-center gap-1 disabled:opacity-50">
                                        <span x-text="lookupLoadingEdit ? 'Fetching…' : '✨ Auto-fill Area/City/State'"></span>
                                    </button>
                                @endif
                            </div>

                            @if($field->key === 'pincode')
                                <input name="pincode" x-model="editForm.pincode" required
                                       class="w-full rounded-lg border border-line px-3 py-2 text-[13px] font-semibold focus:outline-none focus:ring-2 focus:ring-brand/20">
                                <p x-show="lookupMessageEdit" x-cloak class="text-[10.5px] mt-0.5 font-medium"
                                   :class="lookupErrorEdit ? 'text-rose-600' : 'text-emerald-700'"
                                   x-text="lookupMessageEdit"></p>
                            @elseif($field->key === 'assigned_to')
                                <select name="assigned_to" x-model="editForm.assigned_to" @required($field->required)
                                        class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                                    <option value="">Unassigned</option>
                                    @foreach($agents as $a)
                                        <option value="{{ $a->id }}">{{ $a->name }}{{ $a->username ? ' ('.$a->username.')' : '' }}</option>
                                    @endforeach
                                </select>
                            @elseif($field->type === 'select')
                                <select name="{{ $field->key }}" x-model="editForm['{{ $field->key }}']" @required($field->required)
                                        class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                                    <option value="">Select</option>
                                    @foreach($field->options ?? [] as $opt)
                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                    @endforeach
                                </select>
                            @elseif($field->type === 'textarea')
                                <textarea name="{{ $field->key }}" x-model="editForm['{{ $field->key }}']" rows="3" @required($field->required)
                                          class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20"></textarea>
                            @elseif($field->type === 'phone')
                                <input name="{{ $field->key }}" x-model="editForm['{{ $field->key }}']"
                                       type="tel" inputmode="tel" pattern="[0-9+\-\s()]*"
                                       oninput="this.value = this.value.replace(/[^0-9+\-\s()]/g, '')"
                                       @required($field->required)
                                       class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                            @elseif($field->type === 'number')
                                <input name="{{ $field->key }}" x-model="editForm['{{ $field->key }}']"
                                       type="number" step="any" inputmode="decimal"
                                       @required($field->required)
                                       class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                            @elseif($field->type === 'date')
                                <input name="{{ $field->key }}" x-model="editForm['{{ $field->key }}']"
                                       type="date" @required($field->required)
                                       class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                            @elseif($field->type === 'time')
                                <input name="{{ $field->key }}" x-model="editForm['{{ $field->key }}']"
                                       type="time" @required($field->required)
                                       class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                            @else
                                <input name="{{ $field->key }}" x-model="editForm['{{ $field->key }}']"
                                       type="{{ $field->type === 'email' ? 'email' : 'text' }}"
                                       @required($field->required)
                                       class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="shrink-0 border-t border-line px-5 py-3 bg-white">
                    <button type="submit" class="w-full rounded-lg bg-brand hover:bg-brand-dark text-white font-semibold py-2.5 text-[13px]">
                        Update Pincode
                    </button>
                </div>
            </form>
        </aside>
    </div>

    {{-- Right Excel Upload & Download Slide-Over Drawer --}}
    <div class="fixed inset-0 z-[60]" x-show="excelOpen" x-cloak>
        <div class="absolute inset-0 bg-ink/25 transition-opacity"
             x-show="excelOpen"
             x-transition.opacity.duration.200ms
             @click="excelOpen = false"></div>

        <aside class="absolute right-0 top-0 h-full w-full max-w-[440px] bg-white border-l border-line shadow-xl flex flex-col"
               x-show="excelOpen"
               x-transition:enter="transition ease-out duration-300"
               x-transition:enter-start="translate-x-full"
               x-transition:enter-end="translate-x-0"
               x-transition:leave="transition ease-in duration-200"
               x-transition:leave-start="translate-x-0"
               x-transition:leave-end="translate-x-full"
               @click.stop>

            <div class="px-5 py-4 border-b border-line flex items-center justify-between shrink-0 bg-white">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-200 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm0 4h16M4 13h16M4 17h16M9 8v12M15 8v12"/></svg>
                    </div>
                    <div>
                        <div class="text-[11px] uppercase tracking-[0.14em] text-slate-400 font-bold">Pincode Manager</div>
                        <div class="text-[17px] font-semibold text-ink">Excel Upload & Download</div>
                    </div>
                </div>
                <button type="button" @click="excelOpen = false" class="w-8 h-8 rounded-lg hover:bg-mist text-slate-500 flex items-center justify-center">✕</button>
            </div>

            <div class="flex-1 overflow-y-auto crm-scroll p-5 space-y-5">
                {{-- Bulk Upload --}}
                <div class="rounded-xl border border-line bg-white p-4 space-y-3.5 shadow-2xs">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h3 class="text-[13.5px] font-bold text-ink">Bulk Upload Pincodes</h3>
                            <p class="text-[11px] text-slate-500 mt-0.5">Import pincodes from Excel / CSV using active fields.</p>
                        </div>
                        <a href="{{ route('pincodes.demo') }}"
                           class="shrink-0 inline-flex items-center gap-1 rounded-lg bg-brand-soft text-brand hover:bg-brand hover:text-white text-[11px] font-semibold px-2.5 py-1.5 transition">
                            ↓ Demo Excel
                        </a>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[10.5px] font-bold uppercase tracking-wide text-slate-400">Active Excel Columns</span>
                            @if(auth()->user()->isAdmin())
                                <a href="{{ route('settings.index', ['tab' => 'pincode-fields']) }}" class="text-[11px] font-semibold text-brand hover:underline">Manage fields</a>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-1">
                            @forelse($excelFields as $ef)
                                <span class="inline-flex items-center gap-0.5 rounded bg-mist px-2 py-0.5 text-[10.5px] font-semibold text-ink">
                                    {{ $ef->key }}@if($ef->required)<span class="text-rose-500">*</span>@endif
                                </span>
                            @empty
                                <span class="text-[11px] text-slate-400">No Excel fields enabled.</span>
                            @endforelse
                        </div>
                    </div>

                    <form method="POST" action="{{ route('pincodes.import') }}" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        <label class="flex flex-col items-center justify-center gap-1.5 rounded-xl border-2 border-dashed border-line bg-mist/40 px-4 py-6 cursor-pointer hover:border-brand/40 transition text-center">
                            <span class="text-[13px] font-semibold text-ink">Drop Excel file or click</span>
                            <span class="text-[11px] text-slate-400">.xlsx · .xls · .csv (max 10MB)</span>
                            <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="text-[11.5px] mt-1 max-w-full">
                        </label>
                        <button type="submit" class="w-full rounded-lg bg-brand hover:bg-brand-dark text-white font-semibold py-2.5 text-[13px] transition">
                            Upload & Import
                        </button>
                    </form>
                </div>

                {{-- Download Excel --}}
                <div class="rounded-xl border border-line bg-white p-4 space-y-3.5 shadow-2xs">
                    <div>
                        <h3 class="text-[13.5px] font-bold text-ink">Download Pincodes Excel</h3>
                        <p class="text-[11px] text-slate-500 mt-0.5">Export pincode records with all active Excel columns.</p>
                    </div>

                    <form method="GET" action="{{ route('pincodes.export') }}" class="space-y-3">
                        <button type="submit"
                                class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 text-[13px] transition shadow-xs">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            <span>Download Excel</span>
                        </button>
                    </form>
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script>
function pincodeManager(config) {
    return {
        pageIds: (config.pageIds || []).map(Number),
        selectedIds: [],
        lastSelectId: null,
        bulkActionType: 'assign',
        createForm: { ...(config.createDefaults || {}) },
        editOpen: false,
        editForm: {},
        excelOpen: false,
        lookupLoadingCreate: false,
        lookupMessageCreate: '',
        lookupErrorCreate: false,
        lookupLoadingEdit: false,
        lookupMessageEdit: '',
        lookupErrorEdit: false,

        openEdit(row) {
            this.editForm = { ...row };
            this.lookupMessageEdit = '';
            this.lookupErrorEdit = false;
            this.editOpen = true;
        },
        closeEdit() {
            this.editOpen = false;
            this.editForm = {};
        },
        isSelected(id) {
            return this.selectedIds.includes(Number(id));
        },
        pageSelected() {
            return this.pageIds.length > 0 && this.pageIds.every(id => this.selectedIds.includes(id));
        },
        toggleSelect(id, event) {
            id = Number(id);
            if (event?.shiftKey && this.lastSelectId != null) {
                const from = this.pageIds.indexOf(Number(this.lastSelectId));
                const to = this.pageIds.indexOf(id);
                if (from !== -1 && to !== -1) {
                    const range = this.pageIds.slice(Math.min(from, to), Math.max(from, to) + 1);
                    this.selectedIds = [...new Set([...this.selectedIds, ...range])];
                    return;
                }
            }
            this.selectedIds = this.selectedIds.includes(id)
                ? this.selectedIds.filter(x => x !== id)
                : [...this.selectedIds, id];
            this.lastSelectId = id;
        },
        togglePage() {
            this.selectedIds = this.pageSelected()
                ? this.selectedIds.filter(id => !this.pageIds.includes(id))
                : [...new Set([...this.selectedIds, ...this.pageIds])];
        },
        clearSelection() {
            this.selectedIds = [];
            this.lastSelectId = null;
        },
        prepareBulkSubmit(event) {
            if (!this.selectedIds.length) {
                event.preventDefault();
                return;
            }
            const form = event.target;
            form.querySelectorAll('input[data-bulk-id]').forEach(el => el.remove());
            this.selectedIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = String(id);
                input.setAttribute('data-bulk-id', '1');
                form.appendChild(input);
            });
        },
        async lookupPostalPincode(targetForm, mode) {
            const pin = String(targetForm.pincode || '').trim();
            const isEdit = mode === 'edit';
            if (!/^\d{6}$/.test(pin)) {
                if (isEdit) {
                    this.lookupErrorEdit = true;
                    this.lookupMessageEdit = 'Enter a valid 6-digit Indian pincode to auto-fill.';
                } else {
                    this.lookupErrorCreate = true;
                    this.lookupMessageCreate = 'Enter a valid 6-digit Indian pincode to auto-fill.';
                }
                return;
            }
            if (isEdit) {
                this.lookupLoadingEdit = true;
                this.lookupMessageEdit = '';
            } else {
                this.lookupLoadingCreate = true;
                this.lookupMessageCreate = '';
            }
            try {
                const res = await fetch(`https://api.postalpincode.in/pincode/${encodeURIComponent(pin)}`);
                const data = await res.json();
                const first = Array.isArray(data) ? data[0] : null;
                const offices = first && Array.isArray(first.PostOffice) ? first.PostOffice : [];
                if (offices.length > 0) {
                    const po = offices[0];
                    if ('area' in targetForm) targetForm.area = po.Name || targetForm.area || '';
                    if ('city' in targetForm) targetForm.city = po.District || po.Block || targetForm.city || '';
                    if ('state' in targetForm) targetForm.state = po.State || targetForm.state || '';
                    if ('zone' in targetForm && !targetForm.zone) targetForm.zone = po.Division || po.Region || '';
                    const msg = `Auto-filled: ${po.Name}, ${po.District}, ${po.State}`;
                    if (isEdit) {
                        this.lookupErrorEdit = false;
                        this.lookupMessageEdit = msg;
                    } else {
                        this.lookupErrorCreate = false;
                        this.lookupMessageCreate = msg;
                    }
                } else {
                    const msg = 'No postal data found for this pincode — please fill manually.';
                    if (isEdit) {
                        this.lookupErrorEdit = true;
                        this.lookupMessageEdit = msg;
                    } else {
                        this.lookupErrorCreate = true;
                        this.lookupMessageCreate = msg;
                    }
                }
            } catch (e) {
                const msg = 'Could not reach postal API — please fill fields manually.';
                if (isEdit) {
                    this.lookupErrorEdit = true;
                    this.lookupMessageEdit = msg;
                } else {
                    this.lookupErrorCreate = true;
                    this.lookupMessageCreate = msg;
                }
            } finally {
                if (isEdit) {
                    this.lookupLoadingEdit = false;
                } else {
                    this.lookupLoadingCreate = false;
                }
            }
        }
    };
}
</script>
@endpush

