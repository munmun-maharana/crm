@extends('layouts.app')

@section('title', 'Note Templates')
@section('heading', 'Note Templates')
@section('subheading', 'Create default notes — click a chip in CRM to fill the note box')

@section('content')
<div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
    <div class="rounded-xl border border-line bg-white p-4 h-fit shadow-sm">
        <h3 class="text-sm font-semibold mb-1">Add template</h3>
        <p class="text-[11px] text-slate-400 mb-3">Title shows as chip · body goes into the note</p>
        <form method="POST" action="{{ route('note-templates.store') }}" class="space-y-2.5">
            @csrf
            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wide mb-1">Title *</label>
                <input name="title" required maxlength="120" placeholder="e.g. Call Back Later"
                       class="w-full rounded-lg border border-line px-3 py-2 text-[13px]">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wide mb-1">Set Status (Optional)</label>
                <select name="status" class="w-full rounded-lg border border-line px-3 py-2 text-[13px] bg-white">
                    <option value="">-- No Status Change --</option>
                    @foreach(['Follow Up', 'Cool Follow Up', 'Pending', 'Interested', 'Converted', 'Not Interested', 'Hold', 'RNR', 'Not Connected', 'Completed', 'Engaged', 'Existing'] as $optSt)
                        <option value="{{ $optSt }}">{{ $optSt }}</option>
                    @endforeach
                </select>
                <p class="text-[10px] text-slate-400 mt-0.5">Clicking this quick note will also set the customer's status</p>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wide mb-1">Note Body *</label>
                <textarea name="body" required rows="3" placeholder="e.g. Customer requested to call back later"
                          class="w-full rounded-lg border border-line px-3 py-2 text-[13px]"></textarea>
            </div>
            <button class="w-full rounded-lg bg-brand hover:bg-brand-dark text-white font-semibold py-2 text-[13px] transition cursor-pointer">
                Save template
            </button>
        </form>
    </div>

    <div class="xl:col-span-2 rounded-xl border border-line bg-white overflow-hidden shadow-sm">
        <div class="px-4 py-3 border-b border-line text-[13px] text-slate-500">
            {{ $templates->count() }} template{{ $templates->count() === 1 ? '' : 's' }}
        </div>

        @forelse($templates as $t)
            <div class="border-b border-line last:border-b-0 px-4 py-3 hover:bg-mist/30">
                <form method="POST" action="{{ route('note-templates.update', $t) }}" class="space-y-2">
                    @csrf @method('PUT')
                    <div class="flex items-start gap-2">
                        <div class="flex-1 min-w-0 space-y-2">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <input name="title" value="{{ $t->title }}" required placeholder="Title"
                                       class="w-full rounded-lg border border-line px-3 py-1.5 text-[13px] font-semibold">
                                <select name="status" class="w-full rounded-lg border border-line px-3 py-1.5 text-[12px] bg-white">
                                    <option value="">-- No Status Change --</option>
                                    @foreach(['Follow Up', 'Cool Follow Up', 'Pending', 'Interested', 'Converted', 'Not Interested', 'Hold', 'RNR', 'Not Connected', 'Completed', 'Engaged', 'Existing'] as $optSt)
                                        <option value="{{ $optSt }}" @selected(strtolower(trim((string)$t->status)) === strtolower(trim($optSt)))>
                                            Status: {{ $optSt }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <textarea name="body" required rows="2" placeholder="Note body"
                                      class="w-full rounded-lg border border-line px-3 py-1.5 text-[13px]">{{ $t->body }}</textarea>
                            <label class="inline-flex items-center gap-2 text-[12px] text-slate-600">
                                <input type="checkbox" name="is_active" value="1" @checked($t->is_active)
                                       class="rounded border-line text-brand">
                                Show in CRM
                            </label>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0 pt-0.5">
                            <button type="submit" title="Save"
                                    class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-line text-brand hover:bg-brand-soft cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </button>
                        </div>
                    </div>
                </form>
                <form method="POST" action="{{ route('note-templates.destroy', $t) }}" class="mt-2"
                      onsubmit="return confirm('Delete this template?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-[11px] font-semibold text-rose-600 hover:underline">Delete</button>
                </form>
            </div>
        @empty
            <div class="px-4 py-12 text-center text-slate-400 text-sm">
                No templates yet. Add one on the left — example: <span class="font-semibold text-ink">Not at Sir</span>
            </div>
        @endforelse
    </div>
</div>
@endsection
