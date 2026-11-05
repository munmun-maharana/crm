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
<div class="max-w-2xl space-y-4">
    <div class="rounded-xl border border-line bg-white p-5">
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
            <button class="w-full rounded-lg bg-brand text-white font-semibold py-2.5 text-[13px]">Upload & import</button>
        </form>
    </div>
</div>
@endsection
