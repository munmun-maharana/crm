@extends('layouts.app')

@section('title', 'Activation')
@section('heading', 'Activation')
@section('subheading', 'Activation requests from Team Ops')

@section('content')
@php
    $statusTone = [
        'pending' => 'bg-amber-50 text-amber-800',
        'approved' => 'bg-emerald-50 text-emerald-800',
        'rejected' => 'bg-rose-50 text-rose-700',
        'fulfilled' => 'bg-sky-50 text-sky-800',
    ];
@endphp

<form method="get" class="mb-4 flex flex-wrap items-end gap-2">
    <div>
        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Search</label>
        <input type="text" name="q" value="{{ $q }}" placeholder="Customer / phone / company"
               class="rounded-lg border border-line px-3 py-2 text-[13px] w-56">
    </div>
    <div>
        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Status</label>
        <select name="status" class="rounded-lg border border-line px-3 py-2 text-[13px]">
            <option value="">All</option>
            @foreach($statuses as $key => $label)
                <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Category</label>
        <select name="type" class="rounded-lg border border-line px-3 py-2 text-[13px]">
            <option value="">All</option>
            @foreach($types as $type)
                <option value="{{ $type->id }}" @selected((int) $typeId === (int) $type->id)>{{ $type->name }}</option>
            @endforeach
        </select>
    </div>
    <button class="rounded-lg bg-brand text-white px-4 py-2 text-[13px] font-semibold">Filter</button>
</form>

<div class="space-y-3">
    @forelse($rows as $row)
        @php $lines = $row->itemLines(); @endphp
        <div class="rounded-xl border border-line bg-white shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-line flex items-start gap-3 flex-wrap bg-mist/30">
                <div class="min-w-0 flex-1">
                    <div class="font-semibold text-[15px] text-ink">{{ $row->customer?->name ?? '—' }}</div>
                    <div class="text-[12px] text-slate-500 mt-0.5">
                        {{ $row->customer?->phone ?: '—' }}
                        @if($row->customer?->source_customer_id)
                            · CRM #{{ $row->customer->source_customer_id }}
                        @endif
                        · {{ $row->requester?->name ?? '—' }}
                    </div>
                </div>
                <span class="inline-flex px-2 py-0.5 rounded text-[11px] font-bold {{ $statusTone[$row->status] ?? 'bg-mist text-slate-600' }}">
                    {{ $row->statusLabel() }}
                </span>
                <div class="text-[12px] text-slate-500 shrink-0">{{ $row->created_at?->format('d M Y · h:i A') }}</div>
            </div>
            <div class="px-4 py-3 grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-3">
                <div class="space-y-1.5">
                    @foreach($lines as $line)
                        <div class="flex items-start gap-2 text-[13px]">
                            <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-brand-soft text-brand shrink-0">{{ $line['type'] }}</span>
                            <span class="font-semibold">{{ $line['label'] }}</span>
                        </div>
                    @endforeach
                    @if($row->notes)
                        <div class="text-[12px] text-slate-500 pt-1">{{ $row->notes }}</div>
                    @endif
                </div>
                <form method="post" action="{{ route('activation.update', $row) }}" class="flex items-center gap-2 lg:justify-end">
                    @csrf
                    @method('PUT')
                    <select name="status" class="rounded-lg border border-line px-2 py-1.5 text-[12px]">
                        @foreach($statuses as $key => $label)
                            <option value="{{ $key }}" @selected($row->status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-lg border border-line px-2.5 py-1.5 text-[12px] font-semibold hover:bg-mist">Save</button>
                </form>
            </div>
        </div>
    @empty
        <div class="rounded-xl border border-line bg-white px-4 py-10 text-center text-slate-400">No activation requests yet</div>
    @endforelse
</div>

<div class="mt-4">{{ $rows->links() }}</div>
@endsection
