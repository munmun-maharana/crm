@extends('layouts.app')

@section('title', 'Fields')
@section('heading', 'CRM Fields')
@section('subheading', 'Drag rows to reorder · Save each field with icon buttons')

@section('content')
<div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
    <div class="rounded-xl border border-line bg-white p-4 h-fit">
        <h3 class="text-sm font-semibold mb-3">Add new field</h3>
        <form method="POST" action="{{ route('fields.store') }}" class="space-y-2.5">
            @csrf
            <input name="label" required placeholder="Field title *" class="w-full rounded-lg border border-line px-3 py-2 text-[13px]">
            <select name="type" class="w-full rounded-lg border border-line px-3 py-2 text-[13px]">
                <option value="text">Text</option>
                <option value="email">Email</option>
                <option value="phone">Phone</option>
                <option value="textarea">Textarea</option>
                <option value="select">Select (dropdown)</option>
            </select>
            <input name="options" placeholder="Select options (comma separated)" class="w-full rounded-lg border border-line px-3 py-2 text-[13px]">
            <label class="flex items-center gap-2 text-[12px]"><input type="checkbox" name="required" value="1"> Required</label>
            <label class="flex items-center gap-2 text-[12px]"><input type="checkbox" name="in_form" value="1" checked> Show in form</label>
            <label class="flex items-center gap-2 text-[12px]"><input type="checkbox" name="in_table" value="1"> Show in table</label>
            <label class="flex items-center gap-2 text-[12px]"><input type="checkbox" name="in_excel" value="1" checked> Use in Excel</label>
            <button class="w-full rounded-lg bg-brand text-white font-semibold py-2 text-[13px]">Add field</button>
        </form>
    </div>

    <div class="xl:col-span-2 rounded-xl border border-line bg-white overflow-hidden">
        <div class="px-4 py-3 border-b border-line text-[13px] text-slate-500 flex items-center justify-between gap-2">
            <span>Drag the <span class="font-semibold text-ink">☰</span> handle to move fields up / down</span>
            <span id="reorderStatus" class="text-[11px] text-emerald-600 font-semibold hidden">Order saved</span>
        </div>

        @foreach($fields as $field)
            <form method="POST" action="{{ route('fields.update', $field) }}" id="field-form-{{ $field->id }}" class="hidden">
                @csrf @method('PUT')
            </form>
        @endforeach

        <div class="overflow-x-auto">
            <table class="w-full text-[12px] border-collapse">
                <thead>
                    <tr class="bg-mist text-slate-500 uppercase tracking-wide text-[11px]">
                        <th class="text-center font-bold px-2 py-2.5 border-b border-r border-line w-12">Sort</th>
                        <th class="text-left font-bold px-3 py-2.5 border-b border-r border-line">Title</th>
                        <th class="text-left font-bold px-3 py-2.5 border-b border-r border-line">Type</th>
                        <th class="text-center font-bold px-2 py-2.5 border-b border-r border-line">Req</th>
                        <th class="text-center font-bold px-2 py-2.5 border-b border-r border-line">Form</th>
                        <th class="text-center font-bold px-2 py-2.5 border-b border-r border-line">Table</th>
                        <th class="text-center font-bold px-2 py-2.5 border-b border-r border-line">Excel</th>
                        <th class="text-center font-bold px-2 py-2.5 border-b border-r border-line">On</th>
                        <th class="text-center font-bold px-3 py-2.5 border-b border-line">Action</th>
                    </tr>
                </thead>
                <tbody id="fieldsSortable">
                    @foreach($fields as $field)
                        @php $fid = 'field-form-'.$field->id; @endphp
                        <tr class="border-b border-line hover:bg-mist/40 bg-white" data-id="{{ $field->id }}">
                            <td class="px-2 py-2.5 border-r border-line text-center align-middle">
                                <button type="button" class="drag-handle cursor-grab active:cursor-grabbing w-8 h-8 inline-flex items-center justify-center rounded-lg border border-line text-slate-500 hover:bg-mist hover:text-ink" title="Drag to reorder">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M9 5h2v2H9V5zm4 0h2v2h-2V5zM9 9h2v2H9V9zm4 0h2v2h-2V9zm-4 4h2v2H9v-2zm4 0h2v2h-2v-2zm-4 4h2v2H9v-2zm4 0h2v2h-2v-2z"/></svg>
                                </button>
                            </td>

                            <td class="px-3 py-2.5 border-r border-line align-top">
                                <input form="{{ $fid }}" name="label" value="{{ $field->label }}"
                                       class="w-full min-w-[140px] rounded border border-line px-2 py-1.5 text-[12px] font-semibold bg-white">
                                <div class="text-[10px] text-slate-400 mt-0.5">key: {{ $field->key }}@if($field->is_system) · system @endif</div>
                                @if($field->type === 'select' || $field->key === 'status')
                                    <input form="{{ $fid }}" name="options"
                                           value="{{ implode(', ', $field->options ?? []) }}"
                                           class="w-full mt-1 rounded border border-line px-2 py-1 text-[11px]"
                                           placeholder="options: a, b, c">
                                @endif
                            </td>

                            <td class="px-3 py-2.5 border-r border-line align-middle">
                                @if($field->is_system)
                                    <input form="{{ $fid }}" type="hidden" name="type" value="{{ $field->type }}">
                                @endif
                                <select form="{{ $fid }}" name="type"
                                        class="w-full min-w-[100px] rounded border border-line px-2 py-1.5 text-[12px] bg-white"
                                        @disabled($field->is_system)>
                                    @foreach(['text','email','phone','textarea','select'] as $t)
                                        <option value="{{ $t }}" @selected($field->type === $t)>{{ $t }}</option>
                                    @endforeach
                                </select>
                            </td>

                            <td class="px-2 py-2.5 border-r border-line text-center align-middle bg-slate-50/50">
                                @if($field->key === 'name')
                                    <input form="{{ $fid }}" type="hidden" name="required" value="1">
                                    <input type="checkbox" checked disabled>
                                @else
                                    <input form="{{ $fid }}" type="checkbox" name="required" value="1" @checked($field->required)>
                                @endif
                            </td>

                            <td class="px-2 py-2.5 border-r border-line text-center align-middle">
                                @if($field->key === 'name')
                                    <input form="{{ $fid }}" type="hidden" name="in_form" value="1">
                                    <input type="checkbox" checked disabled>
                                @else
                                    <input form="{{ $fid }}" type="checkbox" name="in_form" value="1" @checked($field->in_form)>
                                @endif
                            </td>

                            <td class="px-2 py-2.5 border-r border-line text-center align-middle bg-slate-50/50">
                                <input form="{{ $fid }}" type="checkbox" name="in_table" value="1" @checked($field->in_table)>
                            </td>

                            <td class="px-2 py-2.5 border-r border-line text-center align-middle">
                                <input form="{{ $fid }}" type="checkbox" name="in_excel" value="1" @checked($field->in_excel)>
                            </td>

                            <td class="px-2 py-2.5 border-r border-line text-center align-middle bg-slate-50/50">
                                @if($field->key === 'name')
                                    <input form="{{ $fid }}" type="hidden" name="active" value="1">
                                    <input type="checkbox" checked disabled>
                                @else
                                    <input form="{{ $fid }}" type="checkbox" name="active" value="1" @checked($field->active)>
                                @endif
                            </td>

                            <td class="px-3 py-2.5 text-center align-middle whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <button form="{{ $fid }}" type="submit" title="Save"
                                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-line text-brand hover:bg-brand-soft transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                    @unless($field->is_system)
                                        <form method="POST" action="{{ route('fields.destroy', $field) }}"
                                              onsubmit="return confirm('Delete field?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" title="Delete"
                                                    class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-line text-rose-600 hover:bg-rose-50 transition">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
const el = document.getElementById('fieldsSortable');
if (el && window.Sortable) {
    Sortable.create(el, {
        handle: '.drag-handle',
        animation: 180,
        ghostClass: 'bg-brand-soft',
        onEnd() {
            const order = [...el.querySelectorAll('tr[data-id]')].map(row => Number(row.dataset.id));
            fetch(`{{ route('fields.reorder') }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: JSON.stringify({ order })
            }).then(r => r.json()).then(() => {
                const status = document.getElementById('reorderStatus');
                if (!status) return;
                status.classList.remove('hidden');
                setTimeout(() => status.classList.add('hidden'), 1500);
            });
        }
    });
}
</script>
@endpush
