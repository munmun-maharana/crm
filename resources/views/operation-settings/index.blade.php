@extends('layouts.app')

@section('title', 'Operation Setting')
@section('heading', 'Operation Team Setting')
@section('subheading', 'Assign defaults, Operation fields, and CRM field mapping')

@section('content')
<div x-data="{ tab: '{{ $tab }}' }">
    <div class="crm-tabs mb-4 inline-flex rounded-xl border border-line bg-white p-1">
        <button type="button" @click="tab = 'assign'"
                class="px-3.5 py-1.5 rounded-lg text-[13px] font-semibold"
                :class="tab === 'assign' ? 'bg-brand-soft text-brand' : 'text-slate-500 hover:bg-mist'">
            Assign defaults
        </button>
        <button type="button" @click="tab = 'fields'"
                class="px-3.5 py-1.5 rounded-lg text-[13px] font-semibold"
                :class="tab === 'fields' ? 'bg-brand-soft text-brand' : 'text-slate-500 hover:bg-mist'">
            Fields & mapping
        </button>
    </div>

    <div x-show="tab === 'assign'" x-cloak class="max-w-xl">
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

    <div x-show="tab === 'fields'" x-cloak>
        <p class="text-[13px] text-slate-500 mb-4 max-w-3xl">
            Operation fields CRM se alag hain. Har Operation field ko ek CRM field se connect karo.
            Assign pe sirf connected fields ka data copy hota hai, aur CRM ID Operation record me save hota hai.
            Example: CRM <span class="font-semibold text-ink">Customer name</span> → Operation <span class="font-semibold text-ink">Name</span>.
        </p>
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <div class="rounded-xl border border-line bg-white p-4 h-fit">
                <h3 class="text-sm font-semibold mb-3">Add Operation field</h3>
                <form method="POST" action="{{ route('fields.store') }}" class="space-y-2.5" x-data="{ newType: 'text' }">
                    @csrf
                    <input type="hidden" name="pipeline" value="operations">
                    <input name="label" required placeholder="Field title * e.g. Name" class="w-full rounded-lg border border-line px-3 py-2 text-[13px]">
                    <select name="type" x-model="newType" class="w-full rounded-lg border border-line px-3 py-2 text-[13px]">
                        @foreach(\App\Models\CrmField::TYPES as $typeKey => $typeLabel)
                            <option value="{{ $typeKey }}">{{ $typeLabel }}</option>
                        @endforeach
                    </select>
                    <div x-show="newType === 'select'" x-cloak>
                        <input name="options" placeholder="Select options (comma separated)" class="w-full rounded-lg border border-line px-3 py-2 text-[13px]">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1">Map from CRM field</label>
                        <select name="maps_from" class="w-full rounded-lg border border-line px-3 py-2 text-[13px]">
                            <option value="">— not mapped —</option>
                            @foreach($crmFields as $crmField)
                                <option value="{{ $crmField->key }}">{{ $crmField->label }} ({{ $crmField->key }})</option>
                            @endforeach
                        </select>
                    </div>
                    <label class="flex items-center gap-2 text-[12px]"><input type="checkbox" name="required" value="1"> Required</label>
                    <label class="flex items-center gap-2 text-[12px]"><input type="checkbox" name="in_form" value="1" checked> Show in form</label>
                    <label class="flex items-center gap-2 text-[12px]"><input type="checkbox" name="in_table" value="1"> Show in table</label>
                    <button class="w-full rounded-lg bg-brand text-white font-semibold py-2 text-[13px]">Add field</button>
                </form>
            </div>

            <div class="xl:col-span-2 rounded-xl border border-line bg-white overflow-hidden">
                <div class="px-4 py-3 border-b border-line text-[13px] text-slate-500 flex items-center justify-between gap-2">
                    <span>Connect each Operation field to a CRM field, then save the row</span>
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
                                <th class="text-left font-bold px-3 py-2.5 border-b border-r border-line">Operation field</th>
                                <th class="text-left font-bold px-3 py-2.5 border-b border-r border-line">CRM field</th>
                                <th class="text-left font-bold px-3 py-2.5 border-b border-r border-line">Type</th>
                                <th class="text-center font-bold px-2 py-2.5 border-b border-r border-line">Req</th>
                                <th class="text-center font-bold px-2 py-2.5 border-b border-r border-line">Form</th>
                                <th class="text-center font-bold px-2 py-2.5 border-b border-r border-line">On</th>
                                <th class="text-center font-bold px-3 py-2.5 border-b border-line">Action</th>
                            </tr>
                        </thead>
                        <tbody id="fieldsSortable">
                            @foreach($fields as $field)
                                @php $fid = 'field-form-'.$field->id; @endphp
                                <tr class="border-b border-line hover:bg-mist/40 bg-white" data-id="{{ $field->id }}" x-data="{ rowType: '{{ $field->type }}' }">
                                    <td class="px-2 py-2.5 border-r border-line text-center align-middle">
                                        <button type="button" class="drag-handle cursor-grab active:cursor-grabbing w-8 h-8 inline-flex items-center justify-center rounded-lg border border-line text-slate-500 hover:bg-mist hover:text-ink" title="Drag to reorder">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M9 5h2v2H9V5zm4 0h2v2h-2V5zM9 9h2v2H9V9zm4 0h2v2h-2V9zm-4 4h2v2H9v-2zm4 0h2v2h-2v-2zm-4 4h2v2H9v-2zm4 0h2v2h-2v-2z"/></svg>
                                        </button>
                                    </td>
                                    <td class="px-3 py-2.5 border-r border-line align-top">
                                        <input form="{{ $fid }}" name="label" value="{{ $field->label }}"
                                               class="w-full min-w-[140px] rounded border border-line px-2 py-1.5 text-[12px] font-semibold bg-white">
                                        <div class="text-[10px] text-slate-400 mt-0.5">key: {{ $field->key }}@if($field->is_system) · system @endif</div>
                                        <input form="{{ $fid }}" name="options"
                                               x-show="rowType === 'select' || '{{ $field->key }}' === 'status'" x-cloak
                                               value="{{ implode(', ', $field->options ?? []) }}"
                                               class="w-full mt-1 rounded border border-line px-2 py-1 text-[11px]"
                                               placeholder="options: a, b, c">
                                    </td>
                                    <td class="px-3 py-2.5 border-r border-line align-middle">
                                        <select form="{{ $fid }}" name="maps_from"
                                                class="w-full min-w-[160px] rounded border border-line px-2 py-1.5 text-[12px] bg-white">
                                            <option value="">— not mapped —</option>
                                            @foreach($crmFields as $crmField)
                                                <option value="{{ $crmField->key }}" @selected($field->maps_from === $crmField->key)>
                                                    {{ $crmField->label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-3 py-2.5 border-r border-line align-middle">
                                        @if($field->is_system)
                                            <input form="{{ $fid }}" type="hidden" name="type" value="{{ $field->type }}">
                                        @endif
                                        <select form="{{ $fid }}" name="type" x-model="rowType"
                                                class="w-full min-w-[100px] rounded border border-line px-2 py-1.5 text-[12px] bg-white"
                                                @disabled($field->is_system)>
                                            @foreach(\App\Models\CrmField::TYPES as $t => $tLabel)
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
                                            @if($field->key === 'name')
                                                <button type="button" disabled title="The Name field is required by the system and cannot be deleted"
                                                        class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-line text-slate-300 cursor-not-allowed">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            @else
                                                <form method="POST" action="{{ route('fields.destroy', $field) }}"
                                                      onsubmit="return confirm('Delete field \'{{ $field->label }}\'?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" title="Delete"
                                                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-line text-rose-600 hover:bg-rose-50 transition">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
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
