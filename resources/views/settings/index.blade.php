@extends('layouts.app')

@section('title', 'Setting')
@section('heading', 'Setting')
@section('subheading', 'CRM fields, note templates, inventory types, and Operation Team settings')

@section('content')
<div x-data="{ tab: '{{ $tab }}' }">
    <div class="mb-4 inline-flex flex-wrap rounded-xl border border-line bg-white p-1 gap-0.5">
        <button type="button" @click="tab = 'fields'"
                class="px-3.5 py-1.5 rounded-lg text-[13px] font-semibold"
                :class="tab === 'fields' ? 'bg-brand-soft text-brand' : 'text-slate-500 hover:bg-mist'">
            CRM Fields
        </button>
        <button type="button" @click="tab = 'notes'"
                class="px-3.5 py-1.5 rounded-lg text-[13px] font-semibold"
                :class="tab === 'notes' ? 'bg-brand-soft text-brand' : 'text-slate-500 hover:bg-mist'">
            Note Templates
        </button>
        <button type="button" @click="tab = 'whatsapp'"
                class="px-3.5 py-1.5 rounded-lg text-[13px] font-semibold inline-flex items-center gap-1.5"
                :class="tab === 'whatsapp' ? 'bg-emerald-50 text-emerald-700 font-bold' : 'text-slate-500 hover:bg-mist'">
            <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0012.04 2zm0 1.82c4.46 0 8.09 3.63 8.09 8.09s-3.63 8.09-8.09 8.09c-1.42 0-2.8-.37-4.01-1.06l-.29-.17-3.12.82.83-3.04-.19-.31a8.05 8.05 0 01-1.22-4.33c0-4.46 3.63-8.09 8.09-8.09zm4.57 10.38c-.18-.09-1.06-.52-1.22-.58-.16-.06-.28-.09-.4.09-.12.18-.46.58-.56.7-.1.12-.2.13-.38.04-.18-.09-.76-.28-1.45-.89-.54-.48-.9-1.07-1.01-1.25-.1-.18-.01-.28.08-.37.08-.08.18-.2.27-.3.09-.1.12-.18.18-.3.06-.12.03-.22-.02-.31-.04-.09-.4-.96-.55-1.31-.14-.34-.29-.3-.4-.3h-.34c-.12 0-.31.04-.47.22-.16.18-.62.61-.62 1.48s.64 1.72.73 1.84c.09.12 1.25 1.91 3.03 2.68.42.18.75.29 1.01.37.42.13.81.11 1.11.07.34-.05 1.06-.43 1.21-.85.15-.42.15-.78.1-.85-.04-.07-.16-.12-.34-.21z"/></svg>
            <span>WhatsApp</span>
        </button>
        <button type="button" @click="tab = 'op-assign'"
                class="px-3.5 py-1.5 rounded-lg text-[13px] font-semibold"
                :class="tab === 'op-assign' ? 'bg-brand-soft text-brand' : 'text-slate-500 hover:bg-mist'">
            Op Assign
        </button>
        <button type="button" @click="tab = 'op-fields'"
                class="px-3.5 py-1.5 rounded-lg text-[13px] font-semibold"
                :class="tab === 'op-fields' ? 'bg-brand-soft text-brand' : 'text-slate-500 hover:bg-mist'">
            Op Fields
        </button>
        <button type="button" @click="tab = 'user-fields'"
                class="px-3.5 py-1.5 rounded-lg text-[13px] font-semibold"
                :class="tab === 'user-fields' ? 'bg-brand-soft text-brand' : 'text-slate-500 hover:bg-mist'">
            User Fields
        </button>
        <button type="button" @click="tab = 'inventory'"
                class="px-3.5 py-1.5 rounded-lg text-[13px] font-semibold"
                :class="tab === 'inventory' ? 'bg-brand-soft text-brand' : 'text-slate-500 hover:bg-mist'">
            Inventory
        </button>
    </div>

    <div x-show="tab === 'fields'" x-cloak>
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <div class="rounded-xl border border-line bg-white p-4 h-fit">
                <h3 class="text-sm font-semibold mb-3">Add new field</h3>
                <form method="POST" action="{{ route('fields.store') }}" class="space-y-2.5">
                    @csrf
                    <input type="hidden" name="pipeline" value="crm">
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
                    <span id="reorderStatusCrm" class="text-[11px] text-emerald-600 font-semibold hidden">Order saved</span>
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
                        <tbody id="fieldsSortableCrm">
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

    <div x-show="tab === 'notes'" x-cloak>
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <div class="rounded-xl border border-line bg-white p-4 h-fit shadow-sm">
                <h3 class="text-sm font-semibold mb-1">Add template</h3>
                <p class="text-[11px] text-slate-400 mb-3">Title shows as chip · body goes into the note</p>
                <form method="POST" action="{{ route('note-templates.store') }}" class="space-y-2.5">
                    @csrf
                    <input name="title" required maxlength="120" placeholder="Title * e.g. Not at Sir"
                           class="w-full rounded-lg border border-line px-3 py-2 text-[13px]">
                    <textarea name="body" required rows="4" placeholder="Note text * e.g. Customer not responding the call"
                              class="w-full rounded-lg border border-line px-3 py-2 text-[13px]"></textarea>
                    <button class="w-full rounded-lg bg-brand hover:bg-brand-dark text-white font-semibold py-2 text-[13px] transition">
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
                                    <input name="title" value="{{ $t->title }}" required
                                           class="w-full rounded-lg border border-line px-3 py-1.5 text-[13px] font-semibold">
                                    <textarea name="body" required rows="2"
                                              class="w-full rounded-lg border border-line px-3 py-1.5 text-[13px]">{{ $t->body }}</textarea>
                                    <label class="inline-flex items-center gap-2 text-[12px] text-slate-600">
                                        <input type="checkbox" name="is_active" value="1" @checked($t->is_active)
                                               class="rounded border-line text-brand">
                                        Show in CRM
                                    </label>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0 pt-0.5">
                                    <button type="submit" title="Save"
                                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-line text-brand hover:bg-brand-soft">
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
    </div>

    <div x-show="tab === 'whatsapp'" x-cloak class="max-w-2xl">
        <div class="rounded-xl border border-line bg-white p-5 shadow-sm space-y-4">
            <div class="flex items-center gap-3 pb-3 border-b border-line">
                <span class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 inline-flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0012.04 2zm0 1.82c4.46 0 8.09 3.63 8.09 8.09s-3.63 8.09-8.09 8.09c-1.42 0-2.8-.37-4.01-1.06l-.29-.17-3.12.82.83-3.04-.19-.31a8.05 8.05 0 01-1.22-4.33c0-4.46 3.63-8.09 8.09-8.09zm4.57 10.38c-.18-.09-1.06-.52-1.22-.58-.16-.06-.28-.09-.4.09-.12.18-.46.58-.56.7-.1.12-.2.13-.38.04-.18-.09-.76-.28-1.45-.89-.54-.48-.9-1.07-1.01-1.25-.1-.18-.01-.28.08-.37.08-.08.18-.2.27-.3.09-.1.12-.18.18-.3.06-.12.03-.22-.02-.31-.04-.09-.4-.96-.55-1.31-.14-.34-.29-.3-.4-.3h-.34c-.12 0-.31.04-.47.22-.16.18-.62.61-.62 1.48s.64 1.72.73 1.84c.09.12 1.25 1.91 3.03 2.68.42.18.75.29 1.01.37.42.13.81.11 1.11.07.34-.05 1.06-.43 1.21-.85.15-.42.15-.78.1-.85-.04-.07-.16-.12-.34-.21z"/></svg>
                </span>
                <div>
                    <h3 class="text-sm font-bold text-ink">WhatsApp Data Send Settings</h3>
                    <p class="text-[12px] text-slate-500">Jab table me customers select karke "Send to WhatsApp" click karenge, lead details is number pe send hongi.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('whatsapp-settings.update') }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wide mb-1.5">Destination WhatsApp Phone Number *</label>
                    <div class="relative">
                        <input type="text" name="phone" required
                               value="{{ old('phone', $whatsappSetting?->phone) }}"
                               placeholder="e.g. 919876543210 or 9876543210"
                               class="w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-[13px] font-medium outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Country code ke sath enter karein (e.g. <strong>919876543210</strong>). Agar 10 digit number likhenge to automatic <strong>+91</strong> (India) lag jayega.</p>
                </div>

                <div class="rounded-lg bg-mist/40 border border-line p-3 space-y-1.5">
                    <div class="text-[11px] font-bold text-slate-600">Message Preview:</div>
                    <div class="text-[12px] text-slate-600 font-mono bg-white rounded p-2.5 border border-line whitespace-pre-line leading-relaxed">📋 *CUSTOMER LEAD DATA*
👤 *CUSTOMER:* *Customer Name*
• *Phone No:* 9776101485
• *City:* Cuttuck
• *Vehicle Type:* Sedan
• *Model:* Honda Mmaze
• *Service Details:* Full Car Detailing
• *Seal Price:* ₹1199
• *Status:* New</div>
                    <p class="text-[11px] text-slate-400">Sabhi active CRM fields customer details ke sath automatically is template me format ho kar WhatsApp me pre-fill ho jayengi.</p>
                </div>

                <div class="pt-2">
                    <button type="submit" class="rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-2.5 text-[13px] shadow-sm transition">
                        Save WhatsApp Settings
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div x-show="tab === 'op-assign'" x-cloak class="max-w-xl">
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

    <div x-show="tab === 'op-fields'" x-cloak>
        <p class="text-[13px] text-slate-500 mb-4 max-w-3xl">
            Operation fields CRM se alag hain. Har Operation field ko ek CRM field se connect karo.
            Assign pe sirf connected fields ka data copy hota hai.
        </p>
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <div class="rounded-xl border border-line bg-white p-4 h-fit">
                <h3 class="text-sm font-semibold mb-3">Add Operation field</h3>
                <form method="POST" action="{{ route('fields.store') }}" class="space-y-2.5">
                    @csrf
                    <input type="hidden" name="pipeline" value="operations">
                    <input name="label" required placeholder="Field title * e.g. Name" class="w-full rounded-lg border border-line px-3 py-2 text-[13px]">
                    <select name="type" class="w-full rounded-lg border border-line px-3 py-2 text-[13px]">
                        <option value="text">Text</option>
                        <option value="email">Email</option>
                        <option value="phone">Phone</option>
                        <option value="textarea">Textarea</option>
                        <option value="select">Select (dropdown)</option>
                    </select>
                    <input name="options" placeholder="Select options (comma separated)" class="w-full rounded-lg border border-line px-3 py-2 text-[13px]">
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
                    <span id="reorderStatusOp" class="text-[11px] text-emerald-600 font-semibold hidden">Order saved</span>
                </div>

                @foreach($opFields as $field)
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
                        <tbody id="fieldsSortableOp">
                            @foreach($opFields as $field)
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

    <div x-show="tab === 'user-fields'" x-cloak>
        <p class="text-[13px] text-slate-500 mb-4 max-w-3xl">
            Users page form aur table inhi fields se banega. Naya field add karo — Create user form pe auto show hoga.
        </p>
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <div class="rounded-xl border border-line bg-white p-4 h-fit">
                <h3 class="text-sm font-semibold mb-3">Add user field</h3>
                <form method="POST" action="{{ route('fields.store') }}" class="space-y-2.5">
                    @csrf
                    <input type="hidden" name="pipeline" value="users">
                    <input name="label" required placeholder="Field title * e.g. Phone" class="w-full rounded-lg border border-line px-3 py-2 text-[13px]">
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
                    <label class="flex items-center gap-2 text-[12px]"><input type="checkbox" name="in_table" value="1" checked> Show in table</label>
                    <button class="w-full rounded-lg bg-brand text-white font-semibold py-2 text-[13px]">Add field</button>
                </form>
            </div>

            <div class="xl:col-span-2 rounded-xl border border-line bg-white overflow-hidden">
                <div class="px-4 py-3 border-b border-line text-[13px] text-slate-500 flex items-center justify-between gap-2">
                    <span>Drag to reorder · Form / Table checkboxes control Users page</span>
                    <span id="reorderStatusUser" class="text-[11px] text-emerald-600 font-semibold hidden">Order saved</span>
                </div>

                @foreach($userFields as $field)
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
                                <th class="text-center font-bold px-2 py-2.5 border-b border-r border-line">On</th>
                                <th class="text-center font-bold px-3 py-2.5 border-b border-line">Action</th>
                            </tr>
                        </thead>
                        <tbody id="fieldsSortableUser">
                            @foreach($userFields as $field)
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
                                        @if($field->type === 'select' || $field->key === 'role')
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
                                        @if(in_array($field->key, ['name','username','email','password','role'], true))
                                            <input form="{{ $fid }}" type="hidden" name="required" value="1">
                                            <input type="checkbox" checked disabled>
                                        @else
                                            <input form="{{ $fid }}" type="checkbox" name="required" value="1" @checked($field->required)>
                                        @endif
                                    </td>
                                    <td class="px-2 py-2.5 border-r border-line text-center align-middle">
                                        <input form="{{ $fid }}" type="checkbox" name="in_form" value="1" @checked($field->in_form)>
                                    </td>
                                    <td class="px-2 py-2.5 border-r border-line text-center align-middle bg-slate-50/50">
                                        @if($field->key === 'password')
                                            <input form="{{ $fid }}" type="hidden" name="in_table" value="0">
                                            <input type="checkbox" disabled>
                                        @else
                                            <input form="{{ $fid }}" type="checkbox" name="in_table" value="1" @checked($field->in_table)>
                                        @endif
                                    </td>
                                    <td class="px-2 py-2.5 border-r border-line text-center align-middle">
                                        @if(in_array($field->key, ['name','username','email','password','role'], true))
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
                                            @if(in_array($field->key, ['name', 'username', 'email', 'password', 'role'], true))
                                                <button type="button" disabled title="Core user field cannot be deleted"
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

    <div x-show="tab === 'inventory'" x-cloak>
        <p class="text-[13px] text-slate-500 mb-4 max-w-3xl">
            Inventory page tabs inhi types se banenge. Default: GPS device, SIM, License key, Fuel sensor.
            Naya type add karo — Inventory pe auto show hoga. Limitless.
        </p>
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <div class="rounded-xl border border-line bg-white p-4 h-fit shadow-sm">
                <h3 class="text-sm font-semibold mb-1">Add inventory type</h3>
                <p class="text-[11px] text-slate-400 mb-3">e.g. Antenna, Cable, Mount kit</p>
                <form method="POST" action="{{ route('inventory-types.store') }}" class="space-y-2.5">
                    @csrf
                    <input name="name" required maxlength="120" placeholder="Type name *"
                           class="w-full rounded-lg border border-line px-3 py-2 text-[13px]">
                    <button class="w-full rounded-lg bg-brand hover:bg-brand-dark text-white font-semibold py-2 text-[13px] transition">
                        Add type
                    </button>
                </form>
            </div>

            <div class="xl:col-span-2 rounded-xl border border-line bg-white overflow-hidden shadow-sm">
                <div class="px-4 py-3 border-b border-line text-[13px] text-slate-500">
                    {{ $inventoryTypes->count() }} type{{ $inventoryTypes->count() === 1 ? '' : 's' }} · system types cannot be deleted (deactivate instead)
                </div>
                @forelse($inventoryTypes as $type)
                    <div class="border-b border-line last:border-b-0 px-4 py-3 hover:bg-mist/30">
                        <form method="POST" action="{{ route('inventory-types.update', $type) }}" class="space-y-2">
                            @csrf @method('PUT')
                            <div class="flex items-start gap-2">
                                <div class="flex-1 min-w-0 space-y-2">
                                    <input name="name" value="{{ $type->name }}" required
                                           class="w-full rounded-lg border border-line px-3 py-1.5 text-[13px] font-semibold">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <input name="sort_order" type="number" min="0" value="{{ $type->sort_order }}"
                                               class="w-24 rounded-lg border border-line px-2 py-1 text-[12px]" title="Sort order">
                                        <label class="inline-flex items-center gap-2 text-[12px] text-slate-600">
                                            <input type="checkbox" name="is_active" value="1" @checked($type->is_active)
                                                   class="rounded border-line text-brand">
                                            Active on Inventory
                                        </label>
                                        @if($type->is_system)
                                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">System</span>
                                        @endif
                                        <span class="text-[10px] text-slate-400">key: {{ $type->key }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0 pt-0.5">
                                    <button type="submit" title="Save"
                                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-line text-brand hover:bg-brand-soft">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                </div>
                            </div>
                        </form>
                        @unless($type->is_system)
                            <form method="POST" action="{{ route('inventory-types.destroy', $type) }}" class="mt-2"
                                  onsubmit="return confirm('Delete this inventory type? Items under it will also be removed.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-[11px] font-semibold text-rose-600 hover:underline">Delete</button>
                            </form>
                        @endunless
                    </div>
                @empty
                    <div class="px-4 py-12 text-center text-slate-400 text-sm">
                        No inventory types yet. Add one on the left.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
function bindFieldSort(elId, statusId) {
    const el = document.getElementById(elId);
    if (!el || !window.Sortable) return;
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
                const status = document.getElementById(statusId);
                if (!status) return;
                status.classList.remove('hidden');
                setTimeout(() => status.classList.add('hidden'), 1500);
            });
        }
    });
}
bindFieldSort('fieldsSortableCrm', 'reorderStatusCrm');
bindFieldSort('fieldsSortableOp', 'reorderStatusOp');
bindFieldSort('fieldsSortableUser', 'reorderStatusUser');
</script>
@endpush
