@extends('layouts.app')

@section('title', 'Operation Setting')
@section('heading', 'Operation Team Setting')
@section('subheading', 'Defaults applied in CRM when you click Assign to Operation Team')

@section('content')
<div class="max-w-xl">
    <div class="rounded-xl border border-line bg-white p-5">
        <p class="text-[13px] text-slate-500 mb-4">
            Jab CRM me <span class="font-semibold text-ink">Assign to Operation Team</span> click hoga, customer ka status yahan wale status pe change hoga, aur ye title + note CRM history me auto add hoga.
        </p>
        <form method="POST" action="{{ route('operation-settings.update') }}" class="space-y-3">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-[11px] font-bold text-slate-500 mb-1">Status *</label>
                <select name="status" required class="w-full rounded-lg border border-line px-3 py-2 text-[13px]">
                    @foreach($statuses as $s)
                        <option value="{{ $s }}" @selected($setting->status === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-500 mb-1">Title *</label>
                <input name="title" required maxlength="120" value="{{ old('title', $setting->title) }}"
                       placeholder="e.g. Assigned to Operation Team"
                       class="w-full rounded-lg border border-line px-3 py-2 text-[13px]">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-500 mb-1">Note / description *</label>
                <textarea name="body" required rows="5" placeholder="This note is saved in CRM history"
                          class="w-full rounded-lg border border-line px-3 py-2 text-[13px]">{{ old('body', $setting->body) }}</textarea>
            </div>
            <button class="w-full rounded-lg bg-brand text-white font-semibold py-2.5 text-[13px] hover:bg-brand-dark">
                Save setting
            </button>
        </form>
    </div>
</div>
@endsection
