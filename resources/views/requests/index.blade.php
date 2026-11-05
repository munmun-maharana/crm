@extends('layouts.app')

@section('title', 'Requests')

@section('split')
@php
    $statusTone = [
        'interested' => 'text-teal-700 bg-teal-50',
        'request' => 'text-amber-800 bg-amber-50',
    ];
@endphp

<div class="h-full flex flex-col">
    <div class="shrink-0 border-b border-line px-3 py-2 flex items-center gap-2 bg-white">
        <div class="text-[15px] font-semibold">Requests</div>
        <div class="text-[12px] text-slate-500 hidden sm:block">Interested &amp; request leads</div>
        <button type="button" x-show="selectedId" x-cloak
                x-transition.opacity.duration.200ms
                @click="closeDesk()"
                class="ml-auto rounded border border-line px-3 py-1.5 text-[12px] font-semibold hover:bg-mist">← Back to list</button>
    </div>

    <div class="flex-1 min-h-0 desk-stage">
        <div class="desk-pane overflow-y-auto crm-scroll bg-mist/30 p-2 sm:p-4"
             x-show="!selectedId" x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-x-4 scale-[0.985]"
             x-transition:enter-end="opacity-100 translate-x-0 scale-100"
             x-transition:leave="transition ease-in duration-220"
             x-transition:leave-start="opacity-100 translate-x-0 scale-100"
             x-transition:leave-end="opacity-0 -translate-x-6 scale-[0.985]">
            <div class="rounded-xl border border-line bg-white overflow-x-auto shadow-sm">
                <table class="w-full text-[13px]">
                    <thead class="bg-mist text-slate-500 text-[11px] uppercase tracking-wide">
                        <tr>
                            <th class="text-left font-bold px-4 py-2.5">Customer</th>
                            <th class="text-left font-bold px-4 py-2.5">Phone</th>
                            <th class="text-left font-bold px-4 py-2.5">Status</th>
                            <th class="text-left font-bold px-4 py-2.5">Source</th>
                            <th class="text-right font-bold px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customers as $c)
                            <tr class="border-t border-line hover:bg-mist/70 table-row-press" @click="selectCustomer({{ $c->id }})">
                                <td class="px-4 py-2.5 font-semibold">{{ $c->name }}</td>
                                <td class="px-4 py-2.5 text-slate-500">{{ $c->phone ?: '—' }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="inline-flex px-2 py-0.5 rounded text-[11px] font-bold capitalize {{ $statusTone[$c->status] ?? 'bg-mist' }}">{{ $c->status }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-slate-500">{{ $c->source ?: '—' }}</td>
                                <td class="px-4 py-2.5 text-right text-brand font-semibold text-[12px]">Open →</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">No interested requests</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($customers->hasPages())
                <div class="mt-3">{{ $customers->links() }}</div>
            @endif
        </div>

        <div class="desk-pane bg-mist/30 p-0 sm:p-4"
             x-show="selectedId" x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-x-8"
             x-transition:enter-end="opacity-100 translate-x-0"
             x-transition:leave="transition ease-in duration-220"
             x-transition:leave-start="opacity-100 translate-x-0"
             x-transition:leave-end="opacity-0 translate-x-6">
            <template x-if="selectedId">
                <div class="flex h-full min-h-0 sm:rounded-xl border-0 sm:border border-line bg-white overflow-hidden shadow-sm" :key="deskKey">
                    <section class="hidden md:flex w-[280px] lg:w-[340px] shrink-0 border-r border-line flex-col bg-white slide-list">
                        <div class="shrink-0 px-3 py-2.5 border-b border-line flex items-center justify-between text-[12px] text-slate-500">
                            <span class="font-semibold text-brand border-b-2 border-brand pb-1">All</span>
                            <span>By date ▾</span>
                        </div>
                        <div class="flex-1 overflow-y-auto crm-scroll">
                            @foreach($customers as $c)
                                <button type="button"
                                        class="list-item w-full text-left px-3 py-2.5 border-b border-line"
                                        :class="selectedId == {{ $c->id }} ? 'active' : ''"
                                        @click="selectCustomer({{ $c->id }})">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="font-semibold text-[14px] truncate">{{ $c->name }}</div>
                                        <div class="text-[11px] text-slate-500 shrink-0 truncate max-w-[110px]">{{ $c->assignee?->name ?: 'Unassigned' }}</div>
                                    </div>
                                    <div class="text-[12px] text-slate-500 truncate mt-0.5">{{ $c->company ?: ($c->phone ?: '—') }}</div>
                                    <div class="mt-1.5 flex items-center gap-2">
                                        <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded capitalize {{ $statusTone[$c->status] ?? 'bg-mist' }}">{{ $c->status }}</span>
                                        <span class="text-[11px] text-slate-400">{{ $c->source }}</span>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </section>
                    <section class="flex-1 min-w-0 flex flex-col bg-white slide-read">
                        <div class="shrink-0 px-3 sm:px-5 py-3 border-b border-line flex items-center justify-between gap-2">
                            <div class="text-[17px] sm:text-[20px] font-semibold truncate min-w-0" x-text="title"></div>
                            <button type="button" @click="closeDesk()" class="w-8 h-8 rounded hover:bg-mist text-slate-500 transition hover:rotate-90 duration-200">✕</button>
                        </div>
                        <div class="flex-1 overflow-y-auto crm-scroll" x-html="html"></div>
                    </section>
                </div>
            </template>
        </div>
    </div>
</div>
@endsection
