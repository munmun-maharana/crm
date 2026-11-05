@extends('layouts.app')

@section('title', 'Excel Upload')
@section('heading', 'Bulk upload')
@section('subheading', 'Import customers from Excel / CSV using active fields')

@section('actions')
<a href="{{ route('upload.demo') }}"
   class="rounded-lg bg-brand text-white text-[12px] font-semibold px-3 py-2 hover:bg-brand-dark">
    Download demo Excel
</a>
@endsection

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 max-w-5xl items-start">
    {{-- Left Card: Bulk Upload / Import --}}
    <div class="rounded-xl border border-line bg-white p-5 shadow-xs">
        <div class="flex items-start justify-between gap-3 mb-4">
            <div>
                <h3 class="text-sm font-semibold">Excel columns (from Fields)</h3>
                <p class="text-[12px] text-slate-500 mt-1">Demo file headers match these active Excel fields.</p>
            </div>
            <a href="{{ route('settings.index', ['tab' => 'fields']) }}" class="text-[12px] font-semibold text-brand hover:underline">Manage fields</a>
        </div>
        <div class="flex flex-wrap gap-1.5 mb-5">
            @forelse($excelFields as $f)
                <span class="inline-flex items-center gap-1 rounded-md bg-mist px-2 py-1 text-[11px] font-semibold text-ink">
                    {{ $f->key }}
                    @if($f->required)<span class="text-rose-500">*</span>@endif
                </span>
            @empty
                <span class="text-[12px] text-slate-400">No Excel fields enabled. Enable some in Fields.</span>
            @endforelse
        </div>

        <form method="POST" action="{{ route('upload.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <label class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-line bg-mist/40 px-4 sm:px-6 py-8 sm:py-10 cursor-pointer hover:border-brand/40 transition text-center">
                <span class="text-sm font-semibold">Drop Excel file or click</span>
                <span class="text-[12px] text-slate-400">.xlsx · .xls · .csv (max 10MB)</span>
                <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="text-[12px] mt-2 max-w-full">
            </label>
            <button class="w-full rounded-lg bg-brand hover:bg-brand-dark text-white font-semibold py-2.5 text-[13px] transition">
                Upload & import
            </button>
        </form>
    </div>

    {{-- Right Card: Excel Download (Date Range & Status Filter) --}}
    <div class="rounded-xl border border-line bg-white p-5 shadow-xs space-y-4">
        <div>
            <h3 class="text-sm font-semibold text-ink">Download Customer Excel</h3>
            <p class="text-[12px] text-slate-500 mt-1">Select a date range and status to export customer records into Excel (.xlsx).</p>
        </div>

        <form method="GET" action="{{ route('upload.export') }}" class="space-y-3.5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wide text-slate-500 mb-1">From Date</label>
                    <input type="date" name="from_date" value="{{ request('from_date', now()->startOfMonth()->toDateString()) }}"
                           class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink focus:outline-none focus:ring-2 focus:ring-brand/20">
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wide text-slate-500 mb-1">To Date</label>
                    <input type="date" name="to_date" value="{{ request('to_date', now()->toDateString()) }}"
                           class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink focus:outline-none focus:ring-2 focus:ring-brand/20">
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wide text-slate-500 mb-1">Customer Status</label>
                <select name="status" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink focus:outline-none focus:ring-2 focus:ring-brand/20">
                    <option value="">All Status</option>
                    @foreach($statusOptions ?? ['new', 'interested', 'contacted', 'converted', 'confirmed', 'lost', 'request'] as $st)
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
@endsection
