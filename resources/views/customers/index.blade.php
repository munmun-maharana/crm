@extends('layouts.app')

@section('title', ($pipeline ?? 'crm') === 'operations' ? 'TEAM OPS' : (($pipeline ?? 'crm') === 'call_crm' ? 'CALL CRM' : 'CRM'))

@section('split')
@php
    $openId = request('open');
    $statusTone = [
        'new' => 'text-sky-700 bg-sky-50',
        'pending' => 'text-amber-800 bg-amber-100',
        'no answer' => 'text-slate-700 bg-slate-100',
        'follow-up' => 'text-amber-800 bg-amber-100',
        'interested' => 'text-teal-700 bg-teal-50',
        'contacted' => 'text-blue-700 bg-blue-50',
        'converted' => 'text-emerald-700 bg-emerald-50',
        'success' => 'text-emerald-700 bg-emerald-50',
        'cancelled' => 'text-rose-700 bg-rose-50',
        'lost' => 'text-rose-700 bg-rose-50',
        'confirmed' => 'text-emerald-700 bg-emerald-50',
        'request' => 'text-amber-800 bg-amber-50',
    ];
@endphp

<div class="h-full flex flex-col"
     x-init="@if($openId) $nextTick(() => selectCustomer({{ (int) $openId }})) @endif @if(request('excel')) excelOpen = true; @endif">

    @php
        $topListRoute = $listRoute ?? (($pipeline ?? 'crm') === 'operations' ? 'operations.index' : 'customers.index');
        $isTodayFuActive = ($filter ?? '') === 'follow_up_today';
    @endphp
    <div class="shrink-0 min-h-14 lg:h-14 border-b border-line px-3 sm:px-4 py-1.5 flex flex-wrap items-center gap-2 sm:gap-3 bg-sky-50/80">
        <form method="GET" id="crmFilterForm" class="flex flex-1 basis-full sm:basis-0 order-last sm:order-none items-center gap-3 min-w-0">
            @if(!empty($filter))
                <input type="hidden" name="filter" value="{{ $filter }}">
            @endif
            <div class="crm-toolbar-group">
                <label class="crm-toolbar-search">
                    <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"/></svg>
                    <input type="text" name="q" id="crmSearch" value="{{ $q }}"
                           placeholder="{{ ($pipeline ?? 'crm') === 'call_crm' ? 'Search name, phone, area, pin…' : 'Search name, phone, email…' }}"
                           autocomplete="off">
                </label>
                <span class="crm-toolbar-split"></span>
                <select name="status" id="crmStatus" class="crm-toolbar-select" title="Status">
                    <option value="">All status</option>
                    @foreach($statusOptions ?? ['new','interested','contacted','converted','lost','request'] as $s)
                        <option value="{{ $s }}" @selected(strtolower((string)$status)===strtolower((string)$s))>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
                @if(($pipeline ?? 'crm') === 'call_crm')
                    <span class="crm-toolbar-split"></span>
                    <select name="area" id="crmArea" class="crm-toolbar-select" title="Filter by Area">
                        <option value="">All Areas</option>
                        @foreach($areaOptions ?? [] as $aOpt)
                            <option value="{{ $aOpt }}" @selected(strtolower((string)($area ?? '')) === strtolower((string)$aOpt))>{{ $aOpt }}</option>
                        @endforeach
                    </select>
                @endif
                @if(auth()->user()->canSeeAllRecords() && ($pipeline ?? 'crm') !== 'call_crm')
                    <span class="crm-toolbar-split"></span>
                    <select name="assigned_to" id="crmAssigned" class="crm-toolbar-select" title="Mapped user">
                        <option value="">All users</option>
                        @foreach($agents as $a)
                            <option value="{{ $a->id }}" @selected($assignedTo===$a->id)>{{ $a->name }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            <span id="crmSearchHint" class="text-[11px] text-slate-400 hidden shrink-0">Searching…</span>
        </form>

        <div class="flex items-center gap-2 shrink-0 ml-auto sm:ml-0">
            <button type="button" x-show="selectedId" x-cloak title="Back to list"
                    x-transition.opacity.duration.200ms
                    @click="closeDesk()"
                    class="w-9 h-9 inline-flex items-center justify-center rounded-lg border border-line text-slate-600 hover:bg-mist">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </button>

            <a href="{{ route($topListRoute, array_filter([
                    'q' => $q ?: null,
                    'status' => $status ?: null,
                    'assigned_to' => $assignedTo ?: null,
                    'area' => ($area ?? '') ?: null,
                    'filter' => $isTodayFuActive ? null : 'follow_up_today',
                ])) }}"
               title="{{ $isTodayFuActive ? 'Showing Today Follow-up (Click to show all)' : 'Filter Today Follow-up' }}"
               class="h-9 inline-flex items-center gap-1.5 rounded-lg px-3 text-[12px] font-semibold transition {{ $isTodayFuActive ? 'bg-amber-600 text-white ring-2 ring-amber-300 shadow-xs' : 'bg-amber-500 text-white hover:bg-amber-600' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Today Follow-up</span>
            </a>

            @if(($pipeline ?? 'crm') !== 'call_crm')
                <button type="button" title="Booking Slots"
                        @click="toggleBookingSlots()"
                        :class="bookingSlotsOpen ? 'bg-indigo-700 text-white ring-2 ring-indigo-300' : 'bg-indigo-600 text-white hover:bg-indigo-700'"
                        class="h-9 inline-flex items-center gap-1.5 rounded-lg px-3 text-[12px] font-semibold transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>Booking Slots</span>
                </button>
            @endif

            <button type="button" title="Add customer" @click="openCreateForm()"
                    class="h-9 inline-flex items-center gap-1.5 rounded-lg bg-brand text-white hover:bg-brand-dark px-3 text-[12px] font-semibold">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add
            </button>
            @if(($pipeline ?? 'crm') !== 'operations')
                <button type="button" title="Excel Upload & Download"
                        @click="excelOpen = true"
                        class="h-9 inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 px-3 text-[12px] font-semibold transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm0 4h16M4 13h16M4 17h16M9 8v12M15 8v12"/></svg>
                    Excel
                </button>
            @endif
        </div>
    </div>

    {{-- Right-side Booking Slots Slide-Over Drawer --}}
    <datalist id="crmCustomerNamesDatalist">
        @foreach($customers as $cItem)
            @if(!empty($cItem->name))
                <option value="{{ $cItem->name }}">{{ $cItem->phone }}</option>
            @endif
        @endforeach
    </datalist>

    <datalist id="pincodeDirectoryList">
        @foreach($pincodeDirectory ?? [] as $pItem)
            <option value="{{ $pItem->pincode }}">{{ $pItem->area ? $pItem->area . ($pItem->city ? ', ' . $pItem->city : '') : '' }}</option>
        @endforeach
    </datalist>

    <datalist id="areaDirectoryList">
        @foreach($areaOptions ?? [] as $aOpt)
            <option value="{{ $aOpt }}">{{ $aOpt }}</option>
        @endforeach
    </datalist>

    <div class="fixed inset-0 z-50" x-show="bookingSlotsOpen" x-cloak>
        <div class="absolute inset-0 bg-ink/25 transition-opacity"
             x-show="bookingSlotsOpen"
             x-transition.opacity.duration.200ms
             @click="bookingSlotsOpen = false"></div>

        <aside class="absolute right-0 top-0 h-full w-full max-w-[440px] bg-white border-l border-line shadow-xl flex flex-col"
               x-show="bookingSlotsOpen"
               x-transition:enter="transition ease-out duration-300"
               x-transition:enter-start="translate-x-full"
               x-transition:enter-end="translate-x-0"
               x-transition:leave="transition ease-in duration-200"
               x-transition:leave-start="translate-x-0"
               x-transition:leave-end="translate-x-full"
               @click.stop>

            {{-- Drawer Header --}}
            <div class="px-5 py-4 border-b border-line flex items-center justify-between shrink-0 bg-white">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-200 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <div>
                        <div class="text-[11px] uppercase tracking-[0.14em] text-slate-400 font-bold">Schedule & Availability</div>
                        <div class="text-[17px] font-semibold text-ink">Booking Slots</div>
                    </div>
                </div>
                <button type="button" @click="bookingSlotsOpen = false" class="w-8 h-8 rounded-lg hover:bg-mist text-slate-500 flex items-center justify-center">✕</button>
            </div>

            {{-- Date Navigation & Counters Bar --}}
            <div class="px-5 py-3 border-b border-line bg-slate-50/90 space-y-2.5 shrink-0">
                <div class="flex items-center justify-between gap-2">
                    <div class="inline-flex items-center rounded-lg border border-line bg-white shadow-2xs overflow-hidden">
                        <button type="button" @click="shiftBookingSlotsDate(-1)" title="Previous day"
                                class="px-2.5 py-1.5 text-slate-600 hover:bg-mist text-[13px] font-bold">‹</button>
                        <input type="date"
                               :value="bookingSlotsDate"
                               @change="loadBookingSlots($event.target.value)"
                               class="border-x border-line px-2.5 py-1.5 text-[12px] font-semibold text-ink focus:outline-none">
                        <button type="button" @click="shiftBookingSlotsDate(1)" title="Next day"
                                class="px-2.5 py-1.5 text-slate-600 hover:bg-mist text-[13px] font-bold">›</button>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <button type="button"
                                @click="loadBookingSlots('{{ now()->toDateString() }}')"
                                class="px-2.5 py-1.5 rounded-lg border border-line bg-white hover:bg-mist text-[11px] font-semibold text-slate-700">
                            Today
                        </button>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-[12px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-2.5 py-0.5 rounded-md" x-text="bookingSlotsDateFormatted"></span>
                    <div class="flex items-center gap-1.5 text-[11px] font-semibold">
                        <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800" x-text="'Available: ' + (bookingSlotsCounts.available || 0)"></span>
                        <span class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-800" x-text="'Booked: ' + (bookingSlotsCounts.booked || 0)"></span>
                        <template x-if="(bookingSlotsCounts.success || 0) > 0">
                            <span class="px-2 py-0.5 rounded-full bg-teal-100 text-teal-800" x-text="'Success: ' + bookingSlotsCounts.success"></span>
                        </template>
                        <template x-if="(bookingSlotsCounts.cancelled || 0) > 0">
                            <span class="px-2 py-0.5 rounded-full bg-slate-200 text-slate-700" x-text="'Cancelled: ' + bookingSlotsCounts.cancelled"></span>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Drawer Body: Slots List --}}
            <div class="flex-1 overflow-y-auto crm-scroll p-5 space-y-2.5">
                {{-- Loading Skeleton --}}
                <template x-if="bookingSlotsLoading">
                    <div class="space-y-2.5">
                        <template x-for="i in 5" :key="i">
                            <div class="w-full h-18 rounded-xl border border-line bg-white p-3.5 animate-pulse flex flex-col justify-between">
                                <div class="h-3.5 bg-slate-200 rounded w-1/2"></div>
                                <div class="h-3 bg-slate-100 rounded w-1/3"></div>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- Empty State --}}
                <template x-if="!bookingSlotsLoading && bookingSlotsList.length === 0">
                    <div class="rounded-xl border border-dashed border-slate-300 bg-mist/30 py-10 px-4 text-center space-y-2 text-[13px] text-slate-500">
                        <div>No booking slots configured for <strong class="text-ink" x-text="bookingSlotsDateFormatted"></strong>.</div>
                        @if(auth()->user()->isAdmin())
                            <div>
                                <a :href="`{{ route('settings.index', ['tab' => 'booking-slots']) }}&slot_date=${bookingSlotsDate}`"
                                   class="inline-flex items-center gap-1 font-bold text-brand hover:underline">
                                    + Add slots in Settings
                                </a>
                            </div>
                        @endif
                    </div>
                </template>

                {{-- Slot Cards --}}
                <template x-if="!bookingSlotsLoading && bookingSlotsList.length > 0">
                    <div class="space-y-2.5">
                        <template x-for="slot in bookingSlotsList" :key="slot.id">
                            <div @click="slot.status === 'available' ? openBookSlotModal(slot) : null"
                                 :class="{
                                     'border-emerald-300 bg-white hover:bg-emerald-50/60 hover:border-emerald-400 cursor-pointer shadow-2xs': slot.status === 'available',
                                     'border-rose-200 bg-rose-50/70 cursor-not-allowed opacity-95': slot.status === 'booked',
                                     'border-teal-300 bg-teal-50/70 cursor-not-allowed opacity-95': slot.status === 'success',
                                     'border-slate-200 bg-slate-100 cursor-not-allowed opacity-75': slot.status === 'cancelled'
                                 }"
                                 class="w-full rounded-xl border p-3.5 flex items-center justify-between gap-3 transition select-none">

                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[13px] font-bold text-ink" x-text="slot.label"></span>
                                        <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded shrink-0"
                                              :class="{
                                                  'bg-emerald-100 text-emerald-800': slot.status === 'available',
                                                  'bg-rose-100 text-rose-800': slot.status === 'booked',
                                                  'bg-teal-600 text-white': slot.status === 'success',
                                                  'bg-slate-200 text-slate-600': slot.status === 'cancelled'
                                              }"
                                              x-text="slot.status"></span>
                                    </div>

                                    <div class="mt-1">
                                        <template x-if="slot.status === 'available'">
                                            <span class="text-[11px] font-medium text-emerald-700">Available — Click to book for a customer</span>
                                        </template>
                                        <template x-if="slot.status === 'booked' || slot.status === 'success'">
                                            <div class="space-y-1 mt-0.5">
                                                <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[12px] font-bold"
                                                     :class="slot.status === 'success' ? 'text-teal-900' : 'text-rose-900'">
                                                    <span class="inline-flex items-center gap-1 truncate" :title="slot.customer_name">
                                                        <svg class="w-3.5 h-3.5 shrink-0" :class="slot.status === 'success' ? 'text-teal-600' : 'text-rose-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                                        <span class="truncate" x-text="(slot.status === 'success' ? 'Success: ' : 'Booked: ') + (slot.customer_name || 'Customer')"></span>
                                                    </span>
                                                    <template x-if="slot.customer_phone">
                                                        <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-1.5 py-0.2 rounded"
                                                              :class="slot.status === 'success' ? 'text-teal-800 bg-teal-100/80' : 'text-rose-800 bg-rose-100/80'">
                                                            <span>📞</span>
                                                            <span x-text="slot.customer_phone"></span>
                                                        </span>
                                                    </template>
                                                </div>
                                                <template x-if="slot.car_model || slot.service_type || slot.price">
                                                    <div class="flex flex-wrap items-center gap-1.5 text-[11px] text-slate-700">
                                                        <template x-if="slot.car_model">
                                                            <span class="inline-flex items-center gap-1 rounded bg-white/90 border border-line px-1.5 py-0.5 font-semibold text-slate-700">
                                                                <span class="text-slate-400">Model:</span>
                                                                <span class="text-ink" x-text="slot.car_model"></span>
                                                            </span>
                                                        </template>
                                                        <template x-if="slot.service_type">
                                                            <span class="inline-flex items-center gap-1 rounded bg-white/90 border border-line px-1.5 py-0.5 font-semibold text-slate-700">
                                                                <span class="text-slate-400">Service:</span>
                                                                <span class="text-ink" x-text="slot.service_type"></span>
                                                            </span>
                                                        </template>
                                                        <template x-if="slot.price">
                                                            <span class="inline-flex items-center gap-1 rounded bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 font-bold text-emerald-800">
                                                                <span>₹</span>
                                                                <span x-text="slot.price"></span>
                                                            </span>
                                                        </template>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                        <template x-if="slot.status === 'cancelled'">
                                            <span class="text-[11px] font-semibold text-slate-500 line-through">Slot Cancelled</span>
                                        </template>
                                    </div>
                                </div>

                                <div class="shrink-0">
                                    <template x-if="slot.status === 'available'">
                                        <span class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 text-white px-3 py-1.5 text-[11px] font-bold shadow-2xs">
                                            Book →
                                        </span>
                                    </template>
                                    <template x-if="slot.status === 'booked'">
                                        <span class="inline-flex items-center gap-1 rounded-lg bg-rose-100 text-rose-700 px-2.5 py-1 text-[11px] font-bold">
                                            Locked
                                        </span>
                                    </template>
                                    <template x-if="slot.status === 'success'">
                                        <span class="inline-flex items-center gap-1 rounded-lg bg-teal-600 text-white px-2.5 py-1 text-[11px] font-bold">
                                            ✓ Success
                                        </span>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </aside>
    </div>

    @if(session('success'))
        <div class="shrink-0 px-3 py-1.5 text-[12px] bg-emerald-50 text-emerald-800 border-b border-emerald-100">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="shrink-0 px-3 py-1.5 text-[12px] bg-rose-50 text-rose-800 border-b border-rose-100">{{ $errors->first() }}</div>
    @endif

    <div class="flex-1 min-h-0 desk-stage">
        <div class="desk-pane overflow-y-auto crm-scroll bg-mist/30 p-2 sm:p-4"
             x-show="!selectedId"
             x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-x-4 scale-[0.985]"
             x-transition:enter-end="opacity-100 translate-x-0 scale-100"
             x-transition:leave="transition ease-in duration-220"
             x-transition:leave-start="opacity-100 translate-x-0 scale-100"
             x-transition:leave-end="opacity-0 -translate-x-6 scale-[0.985]">
            @php $pageIds = $customers->pluck('id')->map(fn ($id) => (int) $id)->values(); @endphp
            <script id="crm-page-ids-json" type="application/json">@json($pageIds)</script>
            <script>window.CRM_PAGE_IDS = JSON.parse(document.getElementById('crm-page-ids-json')?.textContent || '[]');</script>

            @if(!empty($statusCards))
                @php
                    $kpiBgs = [
                        'bg-sky-100/75 border-sky-200/90 text-sky-950',
                        'bg-emerald-100/75 border-emerald-200/90 text-emerald-950',
                        'bg-amber-100/75 border-amber-200/90 text-amber-950',
                        'bg-violet-100/75 border-violet-200/90 text-violet-950',
                        'bg-rose-100/75 border-rose-200/90 text-rose-950',
                        'bg-teal-100/75 border-teal-200/90 text-teal-950',
                        'bg-indigo-100/75 border-indigo-200/90 text-indigo-950',
                        'bg-cyan-100/75 border-cyan-200/90 text-cyan-950',
                    ];
                @endphp
                <div class="grid gap-2 sm:gap-2.5 mb-3 sm:mb-3.5 grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:[grid-template-columns:repeat(var(--cols),minmax(0,1fr))]" style="--cols: {{ min(10, max(2, count($statusCards))) }};">
                    @foreach($statusCards as $card)
                        @php $kpiBg = $kpiBgs[$loop->index % count($kpiBgs)]; @endphp
                        <a href="{{ $card['url'] ?? '#' }}"
                           class="stat-card block rounded-xl border {{ !empty($card['active']) ? 'ring-2 ring-brand bg-white shadow-sm border-brand' : $kpiBg }} px-3 py-2 sm:py-2.5 hover:shadow-sm transition min-w-0"
                           title="{{ $card['label'] }}">
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-[10px] sm:text-[11px] uppercase tracking-wider font-bold text-slate-600 truncate">{{ $card['label'] }}</span>
                                @if(!empty($card['active']))
                                    <span class="w-1.5 h-1.5 rounded-full bg-brand shrink-0" title="Active filter"></span>
                                @endif
                            </div>
                            <div class="mt-0.5 text-lg sm:text-xl font-extrabold text-{{ $card['tone'] }}-700 leading-tight">{{ $card['value'] }}</div>
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="rounded-xl border border-indigo-200/80 bg-indigo-50/35 overflow-x-auto shadow-xs select-none">
                <table class="w-full text-[13px]">
                    <thead class="bg-indigo-100/75 text-indigo-950 text-[11px] uppercase tracking-wide border-b border-indigo-200/80">
                        <tr>
                            @if(auth()->user()->canSeeAllRecords() && ($pipeline ?? 'crm') !== 'call_crm')
                                <th class="text-left font-bold px-3 py-2.5 w-10">
                                    <button type="button" class="crm-check"
                                            :class="pageSelected(@js($pageIds)) ? 'on' : ''"
                                            @click.stop="togglePage(@js($pageIds))"
                                            title="Select page">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                </th>
                            @endif
                            @if(($pipeline ?? 'crm') === 'operations')
                                <th class="text-left font-bold px-4 py-2.5 whitespace-nowrap">CRM ID</th>
                            @endif
                            <th class="text-left font-bold px-4 py-2.5 whitespace-nowrap">Date</th>
                            @foreach($tableFields as $field)
                                <th class="text-left font-bold px-4 py-2.5 whitespace-nowrap">{{ $field->label }}</th>
                            @endforeach
                            @if(auth()->user()->canEditCustomers() || auth()->user()->canDeleteCustomers())
                                <th class="text-right font-bold px-4 py-2.5 whitespace-nowrap">Action</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customers as $c)
                            <tr class="border-t border-line hover:bg-mist/70 table-row-press" @click="selectCustomer({{ $c->id }})"
                                :class="isSelected({{ (int) $c->id }}) ? 'bg-brand-soft/40' : ''">
                                @if(auth()->user()->canSeeAllRecords() && ($pipeline ?? 'crm') !== 'call_crm')
                                    <td class="px-3 py-2.5" @click.stop>
                                        <button type="button" class="crm-check"
                                                :class="isSelected({{ (int) $c->id }}) ? 'on' : ''"
                                                @click.stop="toggleSelect({{ (int) $c->id }}, $event)"
                                                title="Select">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                        </button>
                                    </td>
                                @endif
                                @if(($pipeline ?? 'crm') === 'operations')
                                    <td class="px-4 py-2.5 text-slate-500 font-semibold whitespace-nowrap">{{ $c->source_customer_id ? '#'.$c->source_customer_id : '—' }}</td>
                                @endif
                                <td class="px-4 py-2.5 text-[12px] whitespace-nowrap" data-fu-cell="{{ $c->id }}">
                                    {!! $c->actionDateCellHtml() !!}
                                </td>
                                @foreach($tableFields as $field)
                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        @if($field->key === 'name')
                                            <div class="font-semibold text-ink">{{ $c->name }}</div>
                                            @if($tableFields->where('key', 'company')->isEmpty() && $c->company)
                                                <div class="text-[11px] text-slate-400">{{ $c->company }}</div>
                                            @endif
                                        @elseif($field->key === 'status')
                                            <span class="inline-flex px-2 py-0.5 rounded text-[11px] font-bold capitalize {{ $statusTone[strtolower($c->status)] ?? 'bg-sky-50 text-sky-800' }}" data-status-cell="{{ $c->id }}">{{ $c->status }}</span>
                                        @elseif($field->key === 'assigned_to')
                                            <span class="text-slate-600">{{ $c->assignee?->name ?: 'Unassigned' }}</span>
                                        @elseif($field->key === 'phone')
                                            <span class="text-slate-600">{{ $c->phone ?: '—' }}</span>
                                        @elseif($field->key === 'email')
                                            <span class="text-slate-600">{{ $c->email ?: '—' }}</span>
                                        @elseif($field->key === 'company')
                                            <span class="text-slate-600">{{ $c->company ?: '—' }}</span>
                                        @elseif($field->key === 'city')
                                            <span class="text-slate-600">{{ $c->city ?: '—' }}</span>
                                        @elseif($field->key === 'source')
                                            <span class="text-slate-600">{{ $c->source ?: '—' }}</span>
                                        @elseif($field->key === 'notes')
                                            <span class="text-slate-500 line-clamp-1 max-w-[200px]" title="{{ $c->notes }}">{{ $c->notes ?: '—' }}</span>
                                        @else
                                            @php
                                                $val = $c->fieldValue($field->key);
                                                $strVal = is_array($val) ? implode(', ', $val) : trim((string) ($val ?? ''));
                                                $isMapCol = str_contains(strtolower($field->key), 'map') || str_contains(strtolower($field->label), 'map');
                                                $isUrlCol = (bool) filter_var($strVal, FILTER_VALIDATE_URL) || (bool) preg_match('~^https?://~i', $strVal);
                                            @endphp
                                            <span class="text-slate-600">
                                                @if($strVal !== '')
                                                    @if($isMapCol || $isUrlCol)
                                                        @php
                                                            $colHref = $isUrlCol
                                                                ? $strVal
                                                                : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($strVal);
                                                        @endphp
                                                        <a href="{{ $colHref }}" target="_blank" rel="noopener noreferrer"
                                                           @click.stop
                                                           class="inline-flex items-center gap-1 rounded-md border border-brand/30 bg-brand-soft hover:bg-brand hover:text-white text-brand px-2 py-0.5 text-[11px] font-bold transition">
                                                            <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                                            <span>{{ $isMapCol ? 'Open Map URL' : 'Open URL' }}</span>
                                                        </a>
                                                    @else
                                                        {{ $strVal }}
                                                    @endif
                                                @else
                                                    —
                                                @endif
                                            </span>
                                        @endif
                                    </td>
                                @endforeach
                                @if(auth()->user()->canEditCustomers() || auth()->user()->canDeleteCustomers())
                                    <td class="px-4 py-2.5 text-right whitespace-nowrap" @click.stop>
                                        <div class="inline-flex items-center gap-1.5 justify-end">
                                            @if(auth()->user()->canEditCustomers())
                                                <button type="button"
                                                        title="Edit"
                                                        class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-line text-brand hover:bg-brand-soft transition"
                                                        @click="openEditForm(@js(array_merge([
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
                                                        ], $c->meta ?? [])))">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                </button>
                                            @endif
                                            @if(auth()->user()->canDeleteCustomers())
                                                <form method="POST" action="{{ route('customers.destroy', $c) }}"
                                                      onsubmit="return confirm('Delete this customer?')">
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
                            <tr><td colspan="{{ $tableFields->count() + ((auth()->user()->canSeeAllRecords() && ($pipeline ?? 'crm') !== 'call_crm') ? 1 : 0) + ((($pipeline ?? 'crm') === 'operations') ? 1 : 0) + 1 + ((auth()->user()->canEditCustomers() || auth()->user()->canDeleteCustomers()) ? 1 : 0) }}" class="px-4 py-10 text-center text-slate-400">{{ auth()->user()->canSeeAllRecords() ? 'No customers found' : 'No customers mapped to you' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($customers->hasPages())
                <div class="mt-3">{{ $customers->links() }}</div>
            @endif
        </div>

        <div class="desk-pane bg-slate-50/50 p-0 sm:p-4"
             x-show="selectedId"
             x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-x-8"
             x-transition:enter-end="opacity-100 translate-x-0"
             x-transition:leave="transition ease-in duration-220"
             x-transition:leave-start="opacity-100 translate-x-0"
             x-transition:leave-end="opacity-0 translate-x-6">
            <template x-if="selectedId">
                <div class="flex h-full min-h-0 sm:rounded-xl border-0 sm:border border-line bg-white overflow-hidden shadow-xs" :key="deskKey">
                    <section class="hidden md:flex w-[220px] lg:w-[245px] shrink-0 border-r border-line flex-col bg-teal-50/45 slide-list">
                        <div class="shrink-0 h-10 px-2.5 border-b border-line bg-amber-100/70 flex items-center gap-1 text-[11px]">
                            @php
                                $listRoute = $listRoute ?? 'customers.index';
                                $deskFilter = $filter === 'follow_up_today' ? 'follow_up_today' : 'all';
                            @endphp
                            <a href="{{ route($listRoute, array_filter(['q' => $q ?: null, 'assigned_to' => $assignedTo ?: null])) }}"
                               class="px-2 py-0.5 rounded font-semibold transition {{ $deskFilter === 'all' ? 'bg-brand-soft text-brand' : 'text-slate-600 hover:bg-white/60 hover:text-ink' }}">All</a>
                            <a href="{{ route($listRoute, array_filter(['q' => $q ?: null, 'assigned_to' => $assignedTo ?: null, 'filter' => 'follow_up_today'])) }}"
                               class="px-2 py-0.5 rounded font-semibold transition {{ $deskFilter === 'follow_up_today' ? 'bg-amber-200 text-amber-950' : 'text-slate-600 hover:bg-white/60 hover:text-ink' }}">Follow-up Today</a>
                        </div>
                        <div class="flex-1 overflow-y-auto crm-scroll divide-y divide-teal-100/80">
                            @foreach($customers as $c)
                                <button type="button"
                                        class="list-item w-full text-left px-2.5 py-1.5 leading-tight"
                                        :class="selectedId == {{ $c->id }} ? 'active' : ''"
                                        @click="selectCustomer({{ $c->id }})">
                                    <div class="flex items-center justify-between gap-1.5">
                                        <div class="font-semibold text-[12.5px] text-ink truncate">{{ $c->name }}</div>
                                        @if(($pipeline ?? 'crm') !== 'call_crm')
                                            <div class="text-[10px] text-slate-400 shrink-0 truncate max-w-[85px]" title="{{ $c->assignee?->name ?: 'Unassigned' }}">{{ $c->assignee?->name ?: 'Unassigned' }}</div>
                                        @endif
                                    </div>
                                    @if(($pipeline ?? 'crm') === 'operations' && $c->source_customer_id)
                                        <div class="text-[10px] text-brand font-semibold mt-0.5">CRM #{{ $c->source_customer_id }}</div>
                                    @endif
                                    @if($c->company)
                                        <div class="text-[10.5px] text-slate-500 truncate">{{ $c->company }}</div>
                                    @endif
                                    <div class="mt-0.5 space-y-0.5 text-[10.5px]" data-fu-list="{{ $c->id }}">
                                        {!! $c->listDateSummaryHtml() !!}
                                    </div>
                                    <div class="mt-1 flex items-center gap-1.5 min-w-0">
                                        <span class="text-[9.5px] font-semibold px-1.5 py-0.2 rounded capitalize shrink-0 {{ $statusTone[strtolower($c->status)] ?? ($statusTone[$c->status] ?? 'bg-mist text-slate-600') }}" data-status-list="{{ $c->id }}">{{ $c->status }}</span>
                                        <span class="text-[10px] text-slate-400 truncate">{{ trim(($c->source ?: '').' · '.($c->city ?: ''), ' ·') }}</span>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </section>

                    <section class="flex-1 min-w-0 flex flex-col bg-slate-50/40 slide-read">
                        <div class="shrink-0 h-10 px-3 sm:px-4 border-b border-line bg-violet-100/70 flex items-center justify-between gap-2">
                            <div class="min-w-0 flex items-baseline gap-2">
                                <div class="text-[15px] sm:text-[16px] font-semibold truncate text-violet-950" x-text="title">Customer</div>
                                <div class="text-[11px] text-violet-700/80 hidden sm:inline truncate">Chat, updates &amp; full details</div>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <button type="button" @click="closeDesk()" class="w-7 h-7 rounded hover:bg-white/60 text-slate-600 transition hover:rotate-90 duration-200 text-xs" title="Close">✕</button>
                            </div>
                        </div>
                        <div class="flex-1 overflow-y-auto crm-scroll" x-html="html"></div>
                    </section>
                </div>
            </template>
        </div>

        @if(auth()->user()->canSeeAllRecords() && ($pipeline ?? 'crm') !== 'call_crm' && $customers->isNotEmpty())
            <form method="POST" action="{{ route('customers.bulk-map') }}"
                  x-show="selectedIds.length > 0 && !selectedId"
                  x-cloak
                  x-transition:enter="transition ease-out duration-200"
                  x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                  x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                  x-transition:leave="transition ease-in duration-150"
                  x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                  x-transition:leave-end="opacity-0 translate-y-4 scale-95"
                  class="absolute bottom-3 sm:bottom-5 left-2 right-2 sm:left-1/2 sm:right-auto sm:-translate-x-1/2 z-30 flex flex-wrap sm:flex-nowrap items-center justify-center gap-2 rounded-2xl sm:rounded-full bg-white border border-line shadow-[0_16px_40px_rgba(32,31,30,.18)] px-2.5 py-2"
                  @submit="prepareBulkMap($event)">
                @csrf
                <span class="inline-flex items-center justify-center min-w-7 h-7 rounded-full bg-brand text-white text-[12px] font-bold px-2" x-text="selectedIds.length"></span>
                <span class="text-[12px] font-semibold text-slate-700 pr-1">Bulk map</span>
                <select name="assigned_to" required class="rounded-full border border-line px-3 py-1.5 text-[13px] bg-mist/50 min-w-[130px] sm:min-w-[150px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                    <option value="">Choose user</option>
                    @foreach($agents as $a)
                        <option value="{{ $a->id }}">{{ $a->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-full bg-brand text-white font-semibold px-3.5 py-1.5 text-[12px] hover:bg-brand-dark">
                    Map selected
                </button>

                <span class="w-px h-5 bg-line hidden sm:block"></span>

                <button type="button" @click="sendBulkWhatsapp()"
                        class="inline-flex items-center gap-1.5 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-3.5 py-1.5 text-[12px] shadow-sm transition shrink-0"
                        title="Send selected customer data to WhatsApp">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0012.04 2zm0 1.82c4.46 0 8.09 3.63 8.09 8.09s-3.63 8.09-8.09 8.09c-1.42 0-2.8-.37-4.01-1.06l-.29-.17-3.12.82.83-3.04-.19-.31a8.05 8.05 0 01-1.22-4.33c0-4.46 3.63-8.09 8.09-8.09zm4.57 10.38c-.18-.09-1.06-.52-1.22-.58-.16-.06-.28-.09-.4.09-.12.18-.46.58-.56.7-.1.12-.2.13-.38.04-.18-.09-.76-.28-1.45-.89-.54-.48-.9-1.07-1.01-1.25-.1-.18-.01-.28.08-.37.08-.08.18-.2.27-.3.09-.1.12-.18.18-.3.06-.12.03-.22-.02-.31-.04-.09-.4-.96-.55-1.31-.14-.34-.29-.3-.4-.3h-.34c-.12 0-.31.04-.47.22-.16.18-.62.61-.62 1.48s.64 1.72.73 1.84c.09.12 1.25 1.91 3.03 2.68.42.18.75.29 1.01.37.42.13.81.11 1.11.07.34-.05 1.06-.43 1.21-.85.15-.42.15-.78.1-.85-.04-.07-.16-.12-.34-.21z"/></svg>
                    <span>Send in WP</span>
                </button>

                <button type="button" @click="sendBulkEmail()"
                        class="inline-flex items-center gap-1.5 rounded-full bg-slate-800 hover:bg-slate-900 text-white font-semibold px-3.5 py-1.5 text-[12px] shadow-sm transition shrink-0"
                        title="Open Gmail with selected customers formatted in a table">
                    <svg class="w-3.5 h-3.5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <span>Send Email</span>
                </button>

                <button type="button" title="Clear" @click="clearSelection()"
                        class="w-8 h-8 rounded-full text-slate-400 hover:bg-mist hover:text-ink">✕</button>
            </form>
        @endif
    </div>

    {{-- Right side form drawer (dynamic fields from DB) --}}
    @php
        $crmFormFieldsJson = $formFields->map(function ($f) {
            return [
                'key' => $f->key,
                'label' => $f->label,
                'type' => $f->type,
                'required' => (bool) $f->required,
                'auto_select' => (bool) ($f->auto_select ?? false),
                'options' => $f->options ?? [],
            ];
        })->values();
    @endphp
    <script id="crm-form-fields-json" type="application/json">@json($crmFormFieldsJson)</script>
    <script>
        window.CRM_FORM_FIELDS = JSON.parse(document.getElementById('crm-form-fields-json')?.textContent || '[]');
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
                  id="customerDrawerForm"
                  class="flex-1 overflow-y-auto crm-scroll p-5 space-y-3"
                  :action="formMode === 'edit' ? `{{ url('/customers') }}/${form.id}` : `{{ route('customers.store') }}`">
                @csrf
                <input type="hidden" name="pipeline" value="{{ $pipeline ?? 'crm' }}">
                <template x-if="formMode === 'edit'">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                @foreach($formFields as $field)
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1">
                            {{ $field->label }}@if($field->required) * @endif
                        </label>
                        @if($field->key === 'assigned_to')
                            <select name="assigned_to" x-model="form['assigned_to']" class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20" @required($field->required)>
                                <option value="">Unassigned</option>
                                @foreach($agents as $a)
                                    <option value="{{ $a->id }}">{{ $a->name }}</option>
                                @endforeach
                            </select>
                        @elseif($field->type === 'textarea')
                            <textarea name="{{ $field->key }}" x-model="form['{{ $field->key }}']" rows="3" class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20" @required($field->required)></textarea>
                        @elseif($field->type === 'select')
                            <select name="{{ $field->key }}" x-model="form['{{ $field->key }}']" class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20" @required($field->required)>
                                @unless($field->auto_select && !empty($field->options))
                                    <option value="">Select</option>
                                @endunless
                                @foreach($field->options ?? [] as $opt)
                                    <option value="{{ $opt }}" :selected="String(form['{{ $field->key }}'] || '').trim().toLowerCase() === @js(strtolower(trim($opt)))">{{ ucfirst($opt) }}</option>
                                @endforeach
                            </select>
                        @elseif($field->type === 'phone')
                            <input
                                name="{{ $field->key }}"
                                x-model="form['{{ $field->key }}']"
                                type="tel"
                                inputmode="tel"
                                pattern="[0-9+\-\s()]*"
                                oninput="this.value = this.value.replace(/[^0-9+\-\s()]/g, '')"
                                class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20"
                                @required($field->required)
                            >
                        @elseif($field->key === 'pincode' || $field->key === 'pin')
                            <input
                                id="formFieldPincode"
                                name="{{ $field->key }}"
                                x-model="form['{{ $field->key }}']"
                                type="text"
                                inputmode="numeric"
                                list="pincodeDirectoryList"
                                @input="onPincodeInput($event.target.value)"
                                @change="onPincodeInput($event.target.value)"
                                @blur="onPincodeInput($event.target.value)"
                                placeholder="e.g. 751001"
                                class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20"
                                @required($field->required)
                            >
                        @elseif($field->key === 'area' || $field->key === 'ariya')
                            <input
                                id="formFieldArea"
                                name="{{ $field->key }}"
                                x-model="form['{{ $field->key }}']"
                                type="text"
                                list="areaDirectoryList"
                                placeholder="Area"
                                class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20"
                                @required($field->required)
                            >
                        @elseif($field->type === 'number')
                            <input
                                name="{{ $field->key }}"
                                x-model="form['{{ $field->key }}']"
                                type="number"
                                step="any"
                                inputmode="decimal"
                                oninput="this.value = this.value.replace(/[^0-9.\-]/g, '')"
                                class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20"
                                @required($field->required)
                            >
                        @elseif($field->type === 'date')
                            <div class="flex items-center gap-1.5">
                                <button type="button"
                                        @click="shiftFormDate('{{ $field->key }}', -1)"
                                        title="Previous date"
                                        class="h-9 px-2.5 inline-flex items-center justify-center rounded-lg border border-line bg-mist/60 hover:bg-brand-soft hover:border-brand hover:text-brand text-slate-700 font-bold text-[13px] transition shrink-0">
                                    ‹
                                </button>
                                <input
                                    name="{{ $field->key }}"
                                    x-model="form['{{ $field->key }}']"
                                    type="date"
                                    class="flex-1 min-w-0 rounded-lg border border-line px-3 py-2 text-[13px] font-medium focus:outline-none focus:ring-2 focus:ring-brand/20"
                                    @required($field->required)
                                >
                                <button type="button"
                                        @click="shiftFormDate('{{ $field->key }}', 1)"
                                        title="Next date"
                                        class="h-9 px-2.5 inline-flex items-center justify-center rounded-lg border border-line bg-mist/60 hover:bg-brand-soft hover:border-brand hover:text-brand text-slate-700 font-bold text-[13px] transition shrink-0">
                                    ›
                                </button>
                                <button type="button"
                                        @click="form['{{ $field->key }}'] = todayYmd()"
                                        title="Set to Today"
                                        class="h-9 px-2.5 inline-flex items-center justify-center rounded-lg border border-line bg-white hover:bg-mist text-brand font-semibold text-[11.5px] transition shrink-0">
                                    Today
                                </button>
                            </div>
                        @elseif($field->type === 'time')
                            <div class="flex items-center gap-1.5">
                                <button type="button"
                                        @click="shiftFormTime('{{ $field->key }}', -30)"
                                        title="Previous 30 min"
                                        class="h-9 px-2.5 inline-flex items-center justify-center rounded-lg border border-line bg-mist/60 hover:bg-brand-soft hover:border-brand hover:text-brand text-slate-700 font-bold text-[13px] transition shrink-0">
                                    ‹
                                </button>
                                <input
                                    name="{{ $field->key }}"
                                    x-model="form['{{ $field->key }}']"
                                    type="time"
                                    class="flex-1 min-w-0 rounded-lg border border-line px-3 py-2 text-[13px] font-medium focus:outline-none focus:ring-2 focus:ring-brand/20"
                                    @required($field->required)
                                >
                                <button type="button"
                                        @click="shiftFormTime('{{ $field->key }}', 30)"
                                        title="Next 30 min"
                                        class="h-9 px-2.5 inline-flex items-center justify-center rounded-lg border border-line bg-mist/60 hover:bg-brand-soft hover:border-brand hover:text-brand text-slate-700 font-bold text-[13px] transition shrink-0">
                                    ›
                                </button>
                            </div>
                        @else
                            <input
                                name="{{ $field->key }}"
                                x-model="form['{{ $field->key }}']"
                                type="{{ $field->type === 'email' ? 'email' : 'text' }}"
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

    {{-- Right side Excel Upload & Download Slide-Over Drawer --}}
    @if(($pipeline ?? 'crm') !== 'operations')
        <div class="fixed inset-0 z-50" x-show="excelOpen" x-cloak>
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
                            <div class="text-[11px] uppercase tracking-[0.14em] text-slate-400 font-bold">Import & Export</div>
                            <div class="text-[17px] font-semibold text-ink">Excel Upload & Download</div>
                        </div>
                    </div>
                    <button type="button" @click="excelOpen = false" class="w-8 h-8 rounded-lg hover:bg-mist text-slate-500 flex items-center justify-center">✕</button>
                </div>

                <div class="flex-1 overflow-y-auto crm-scroll p-5 space-y-5">
                    {{-- Section 1: Bulk Upload / Import --}}
                    <div class="rounded-xl border border-line bg-white p-4 space-y-3.5 shadow-2xs">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h3 class="text-[13.5px] font-bold text-ink">Bulk Upload Customers</h3>
                                <p class="text-[11px] text-slate-500 mt-0.5">Import customers from Excel / CSV using active fields.</p>
                            </div>
                            <a href="{{ route('upload.demo', ['pipeline' => $pipeline ?? 'crm']) }}"
                               class="shrink-0 inline-flex items-center gap-1 rounded-lg bg-brand-soft text-brand hover:bg-brand hover:text-white text-[11px] font-semibold px-2.5 py-1.5 transition">
                                ↓ Demo Excel
                            </a>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-[10.5px] font-bold uppercase tracking-wide text-slate-400">Active Excel Columns</span>
                                @if(auth()->user()->isAdmin())
                                    <a href="{{ route('settings.index', ['tab' => ($pipeline ?? 'crm') === 'call_crm' ? 'call-crm-fields' : 'fields']) }}" class="text-[11px] font-semibold text-brand hover:underline">Manage fields</a>
                                @endif
                            </div>
                            <div class="flex flex-wrap gap-1">
                                @forelse($excelFields ?? [] as $ef)
                                    <span class="inline-flex items-center gap-0.5 rounded bg-mist px-2 py-0.5 text-[10.5px] font-semibold text-ink">
                                        {{ $ef->key }}@if($ef->required)<span class="text-rose-500">*</span>@endif
                                    </span>
                                @empty
                                    <span class="text-[11px] text-slate-400">No Excel fields enabled.</span>
                                @endforelse
                            </div>
                        </div>

                        <form method="POST" action="{{ route('upload.store') }}" enctype="multipart/form-data" class="space-y-3">
                            @csrf
                            <input type="hidden" name="pipeline" value="{{ $pipeline ?? 'crm' }}">
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

                    {{-- Section 2: Download Customer Excel by Date Range & Status --}}
                    <div class="rounded-xl border border-line bg-white p-4 space-y-3.5 shadow-2xs">
                        <div>
                            <h3 class="text-[13.5px] font-bold text-ink">Download Customer Excel</h3>
                            <p class="text-[11px] text-slate-500 mt-0.5">Select date range and status to export customer records.</p>
                        </div>

                        <form method="GET" action="{{ route('upload.export') }}" class="space-y-3">
                            <input type="hidden" name="pipeline" value="{{ $pipeline ?? 'crm' }}">
                            <div class="grid grid-cols-2 gap-2.5">
                                <div>
                                    <label class="block text-[10.5px] font-bold uppercase tracking-wide text-slate-500 mb-1">From Date</label>
                                    <input type="date" name="from_date" value="{{ now()->startOfMonth()->toDateString() }}"
                                           class="w-full rounded-lg border border-line bg-white px-2.5 py-2 text-[12px] text-ink focus:outline-none focus:ring-2 focus:ring-brand/20">
                                </div>
                                <div>
                                    <label class="block text-[10.5px] font-bold uppercase tracking-wide text-slate-500 mb-1">To Date</label>
                                    <input type="date" name="to_date" value="{{ now()->toDateString() }}"
                                           class="w-full rounded-lg border border-line bg-white px-2.5 py-2 text-[12px] text-ink focus:outline-none focus:ring-2 focus:ring-brand/20">
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10.5px] font-bold uppercase tracking-wide text-slate-500 mb-1">Status</label>
                                <select name="status" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[12.5px] text-ink focus:outline-none focus:ring-2 focus:ring-brand/20">
                                    <option value="">All Status</option>
                                    @foreach($statusOptions ?? ['new','interested','contacted','converted','confirmed','lost','request'] as $st)
                                        <option value="{{ $st }}">{{ ucfirst($st) }}</option>
                                    @endforeach
                                </select>
                            </div>

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
    @endif
</div>
@endsection

@push('scripts')
<script>
window.PINCODE_DIRECTORY = @json($pincodeDirectory ?? []);
(function () {
    const form = document.getElementById('crmFilterForm');
    const input = document.getElementById('crmSearch');
    const status = document.getElementById('crmStatus');
    const area = document.getElementById('crmArea');
    const assigned = document.getElementById('crmAssigned');
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
    if (area) {
        area.addEventListener('change', () => {
            clearTimeout(timer);
            runSearch();
        });
    }
    if (assigned) {
        assigned.addEventListener('change', () => {
            clearTimeout(timer);
            runSearch();
        });
    }
})();
</script>
@endpush
