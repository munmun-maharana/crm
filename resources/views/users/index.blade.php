@extends('layouts.app')

@section('title', 'Users')
@section('heading', 'Users')
@section('subheading', 'Fields from Setting · User Fields tab')

@section('content')
@php
    $roleTone = [
        'admin' => 'bg-brand-soft text-brand',
        'coordinator' => 'bg-teal-50 text-teal-800',
        'front_office' => 'bg-sky-50 text-sky-800',
        'operation' => 'bg-amber-50 text-amber-800',
    ];
    $roleLabels = \App\Models\User::roleOptions();
    $usersJson = $users->getCollection()->map(function ($u) use ($formFields) {
        $row = [
            'id' => $u->id,
            'name' => $u->name,
            'is_active' => $u->isActive() ? '1' : '0',
            'is_admin' => $u->isAdmin(),
            'access_crm' => $u->appAccessCrm(),
            'access_call_crm' => $u->appAccessCallCrm(),
            'access_operations' => $u->appAccessOperations(),
            'access_pincodes' => $u->appAccessPincodes(),
            'access_inventory' => $u->appAccessInventory(),
            'can_edit' => $u->canEditCustomers(),
            'can_delete' => $u->canDeleteCustomers(),
        ];
        foreach ($formFields as $field) {
            if ($field->key === 'password') {
                $row['password'] = '';
                continue;
            }
            $row[$field->key] = $u->fieldValue($field->key) ?? '';
        }

        return $row;
    })->values();
@endphp

<div x-data="{
        editOpen: false,
        form: {},
        openEdit(user) {
            this.form = {
                ...user,
                password: '',
                is_active: user.is_active !== undefined ? String(user.is_active) : '1',
                access_crm: !!user.access_crm,
                access_call_crm: user.access_call_crm !== undefined ? !!user.access_call_crm : true,
                access_operations: !!user.access_operations,
                access_pincodes: user.access_pincodes !== undefined ? !!user.access_pincodes : true,
                access_inventory: !!user.access_inventory,
                can_edit: !!user.can_edit,
                can_delete: !!user.can_delete,
            };
            this.editOpen = true;
        },
        closeEdit() {
            this.editOpen = false;
            this.form = {};
        }
     }">

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <div class="rounded-2xl border border-sky-200/80 bg-sky-50/75 p-4 h-fit">
            <div class="flex items-center justify-between gap-2 mb-3">
                <h3 class="text-sm font-extrabold text-sky-950">Create user</h3>
                <a href="{{ route('settings.index', ['tab' => 'user-fields']) }}" class="text-[11px] font-semibold text-brand hover:underline">Manage fields</a>
            </div>
            <form method="POST" action="{{ route('users.store') }}" class="space-y-2.5">
                @csrf
                @forelse($formFields as $field)
                    @if($field->key === 'password')
                        <input name="password" type="password" @required($field->required)
                               placeholder="{{ $field->label }}{{ $field->required ? ' *' : '' }}"
                               class="w-full rounded-xl border border-line px-3 py-2 text-[13px]">
                    @elseif($field->key === 'role' || $field->type === 'select')
                        <select name="{{ $field->key }}" @required($field->required) class="w-full rounded-xl border border-line px-3 py-2 text-[13px]">
                            <option value="">{{ $field->label }}{{ $field->required ? ' *' : '' }}</option>
                            @if($field->key === 'role')
                                @foreach($roleLabels as $value => $label)
                                    <option value="{{ $value }}" @selected(old('role', 'front_office') === $value)>{{ $label }}</option>
                                @endforeach
                            @else
                                @foreach($field->options ?? [] as $opt)
                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                @endforeach
                            @endif
                        </select>
                    @elseif($field->type === 'textarea')
                        <textarea name="{{ $field->key }}" rows="2" @required($field->required)
                                  placeholder="{{ $field->label }}{{ $field->required ? ' *' : '' }}"
                                  class="w-full rounded-xl border border-line px-3 py-2 text-[13px]"></textarea>
                    @elseif($field->type === 'phone')
                        <input name="{{ $field->key }}" type="tel" inputmode="tel" pattern="[0-9+\-\s()]*"
                               oninput="this.value = this.value.replace(/[^0-9+\-\s()]/g, '')"
                               @required($field->required)
                               placeholder="{{ $field->label }}{{ $field->required ? ' *' : '' }}"
                               class="w-full rounded-xl border border-line px-3 py-2 text-[13px]">
                    @elseif($field->type === 'number')
                        <input name="{{ $field->key }}" type="number" step="any" inputmode="decimal"
                               oninput="this.value = this.value.replace(/[^0-9.\-]/g, '')"
                               @required($field->required)
                               placeholder="{{ $field->label }}{{ $field->required ? ' *' : '' }}"
                               class="w-full rounded-xl border border-line px-3 py-2 text-[13px]">
                    @elseif($field->type === 'date')
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 mb-0.5">{{ $field->label }}{{ $field->required ? ' *' : '' }}</label>
                            <input name="{{ $field->key }}" type="date" @required($field->required)
                                   class="w-full rounded-xl border border-line px-3 py-2 text-[13px]">
                        </div>
                    @elseif($field->type === 'time')
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 mb-0.5">{{ $field->label }}{{ $field->required ? ' *' : '' }}</label>
                            <input name="{{ $field->key }}" type="time" @required($field->required)
                                   class="w-full rounded-xl border border-line px-3 py-2 text-[13px]">
                        </div>
                    @else
                        <input name="{{ $field->key }}"
                               type="{{ $field->type === 'email' ? 'email' : 'text' }}"
                               @required($field->required)
                               placeholder="{{ $field->label }}{{ $field->required ? ' *' : '' }}"
                               class="w-full rounded-xl border border-line px-3 py-2 text-[13px]">
                    @endif
                @empty
                    <p class="text-[12px] text-slate-400">No form fields. Add them in Setting → User Fields.</p>
                @endforelse

                <div class="rounded-xl border border-sky-300/80 bg-white/90 p-3 space-y-1.5 shadow-2xs">
                    <div class="text-[11px] font-bold uppercase tracking-wide text-slate-600">Account status</div>
                    <div class="flex items-center gap-4 text-[13px] font-semibold text-ink pt-0.5">
                        <label class="inline-flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" name="is_active" value="1" class="text-emerald-600 focus:ring-emerald-500" @checked(old('is_active', '1') == '1')>
                            <span class="inline-flex items-center gap-1 text-emerald-700">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Active
                            </span>
                        </label>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" name="is_active" value="0" class="text-rose-600 focus:ring-rose-500" @checked(old('is_active') === '0')>
                            <span class="inline-flex items-center gap-1 text-rose-700">
                                <span class="w-2 h-2 rounded-full bg-rose-500"></span> Deactive
                            </span>
                        </label>
                    </div>
                </div>

                <div class="rounded-xl bg-indigo-100/60 p-3 space-y-2">
                    <div class="text-[11px] font-bold uppercase tracking-wide text-indigo-900">App access level</div>
                    <p class="text-[11px] text-indigo-700/80">Dashboard always on · tick tabs for mobile app</p>
                    <label class="flex items-center gap-2 text-[13px] font-semibold text-ink">
                        <input type="checkbox" name="access_crm" value="1" class="rounded border-line text-brand"
                               @checked(old('access_crm'))>
                        CRM
                    </label>
                    <label class="flex items-center gap-2 text-[13px] font-semibold text-ink">
                        <input type="checkbox" name="access_call_crm" value="1" class="rounded border-line text-brand"
                               @checked(old('access_call_crm', true))>
                        CALL CRM
                    </label>
                    <label class="flex items-center gap-2 text-[13px] font-semibold text-ink">
                        <input type="checkbox" name="access_operations" value="1" class="rounded border-line text-brand"
                               @checked(old('access_operations'))>
                        Operation
                    </label>
                    <label class="flex items-center gap-2 text-[13px] font-semibold text-ink">
                        <input type="checkbox" name="access_pincodes" value="1" class="rounded border-line text-brand"
                               @checked(old('access_pincodes', true))>
                        Pincode Manager
                    </label>
                    <div class="pt-2 border-t border-indigo-200/60 text-[11px] font-bold uppercase tracking-wide text-indigo-900">Record Actions</div>
                    <label class="flex items-center gap-2 text-[13px] font-semibold text-ink">
                        <input type="checkbox" name="can_edit" value="1" class="rounded border-line text-brand"
                               @checked(old('can_edit'))>
                        Allow Edit Customers
                    </label>
                    <label class="flex items-center gap-2 text-[13px] font-semibold text-ink">
                        <input type="checkbox" name="can_delete" value="1" class="rounded border-line text-brand"
                               @checked(old('can_delete'))>
                        Allow Delete Customers
                    </label>
                </div>

                @if($formFields->isNotEmpty())
                    <button type="submit" class="w-full rounded-xl bg-brand text-white font-bold py-2.5 text-[13px]">Create</button>
                @endif
            </form>
        </div>

        <div class="xl:col-span-2 rounded-2xl border border-emerald-200/80 bg-emerald-50/50 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="bg-emerald-100/75 text-emerald-950 text-[11px] uppercase tracking-wide border-b border-emerald-200/80">
                        <tr>
                            @foreach($tableFields as $field)
                                <th class="text-left font-bold px-4 py-2.5">{{ $field->label }}</th>
                            @endforeach
                            <th class="text-left font-bold px-4 py-2.5">Status</th>
                            <th class="text-left font-bold px-4 py-2.5">App access</th>
                            <th class="text-right font-bold px-4 py-2.5">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $u)
                            <tr class="border-t border-line hover:bg-mist/30">
                                @foreach($tableFields as $field)
                                    <td class="px-4 py-2.5 {{ $loop->first ? '' : 'text-slate-500' }}">
                                        @if($field->key === 'name')
                                            <div class="font-semibold text-ink">{{ $u->name }}</div>
                                            @if($tableFields->where('key', 'email')->isEmpty() && $u->email)
                                                <div class="text-[11px] text-slate-400">{{ $u->email }}</div>
                                            @endif
                                        @elseif($field->key === 'role')
                                            <span class="inline-flex text-[11px] font-bold px-2 py-1 rounded-md {{ $roleTone[$u->role] ?? 'bg-mist text-slate-600' }}">
                                                {{ $roleLabels[$u->role] ?? $u->roleLabel() }}
                                            </span>
                                        @elseif($field->key === 'password')
                                            <span class="text-slate-400">••••</span>
                                        @else
                                            @php $val = $u->fieldValue($field->key); @endphp
                                            {{ $val !== null && $val !== '' ? $val : '—' }}
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    @if($u->isAdmin())
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span> Active (Admin)
                                        </span>
                                    @elseif($u->id === auth()->id())
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300" title="Current logged-in account">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span> Active (You)
                                        </span>
                                    @else
                                        <form method="POST" action="{{ route('users.toggle-status', $u) }}" class="inline-block"
                                              onsubmit="return confirm('{{ $u->isActive() ? "Deactivate {$u->name}? If they are logged in, they will be logged out immediately." : "Activate {$u->name}?" }}')">
                                            @csrf @method('PATCH')
                                            <button type="submit"
                                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold transition shadow-2xs {{ $u->isActive() ? 'bg-emerald-100 text-emerald-800 hover:bg-rose-100 hover:text-rose-800 border border-emerald-300 hover:border-rose-300' : 'bg-rose-100 text-rose-800 hover:bg-emerald-100 hover:text-emerald-800 border border-rose-300 hover:border-emerald-300' }}"
                                                    title="Click to {{ $u->isActive() ? 'Deactivate' : 'Activate' }}">
                                                <span class="w-1.5 h-1.5 rounded-full {{ $u->isActive() ? 'bg-emerald-600' : 'bg-rose-600' }}"></span>
                                                <span>{{ $u->isActive() ? 'Active' : 'Deactive' }}</span>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex flex-wrap gap-1">
                                        <span class="inline-flex text-[10px] font-bold px-1.5 py-0.5 rounded bg-mist text-slate-600">Dashboard</span>
                                        @if($u->appAccessCrm())
                                            <span class="inline-flex text-[10px] font-bold px-1.5 py-0.5 rounded bg-sky-50 text-sky-800">CRM</span>
                                        @endif
                                        @if($u->appAccessCallCrm())
                                            <span class="inline-flex text-[10px] font-bold px-1.5 py-0.5 rounded bg-teal-50 text-teal-800">CALL CRM</span>
                                        @endif
                                        @if($u->appAccessOperations())
                                            <span class="inline-flex text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-50 text-amber-800">Operation</span>
                                        @endif
                                        @if($u->appAccessPincodes())
                                            <span class="inline-flex text-[10px] font-bold px-1.5 py-0.5 rounded bg-violet-50 text-violet-800">Pincode</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <div class="inline-flex items-center gap-1.5 justify-end">
                                        <button type="button" title="Edit"
                                                @click="openEdit(@js($usersJson[$loop->index]))"
                                                class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-line text-brand hover:bg-brand-soft transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        @if($u->id !== auth()->id())
                                            <form method="POST" action="{{ route('users.destroy', $u) }}"
                                                  onsubmit="return confirm('Delete user?')">
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
            <div class="p-3">{{ $users->links() }}</div>
        </div>
    </div>

    {{-- Right edit slider --}}
    <div class="fixed inset-0 z-[60]" x-show="editOpen" x-cloak>
        <div class="absolute inset-0 bg-ink/25" x-show="editOpen" x-transition.opacity.duration.200ms @click="closeEdit()"></div>
        <aside class="absolute right-0 top-0 h-full w-full max-w-[420px] bg-white border-l border-line shadow-xl flex flex-col"
               x-show="editOpen"
               x-transition:enter="transition ease-out duration-300"
               x-transition:enter-start="translate-x-full"
               x-transition:enter-end="translate-x-0"
               x-transition:leave="transition ease-in duration-200"
               x-transition:leave-start="translate-x-0"
               x-transition:leave-end="translate-x-full"
               @click.stop>
            <div class="px-5 py-4 border-b border-line flex items-center justify-between shrink-0">
                <div class="min-w-0">
                    <div class="text-[11px] uppercase tracking-[0.14em] text-slate-400 font-bold">Edit user</div>
                    <div class="text-[17px] font-semibold truncate" x-text="form.name || 'User'"></div>
                </div>
                <button type="button" @click="closeEdit()" class="w-8 h-8 rounded hover:bg-mist text-slate-500">✕</button>
            </div>

            <form method="POST" class="flex-1 flex flex-col min-h-0"
                  :action="`{{ url('/users') }}/${form.id}`">
                @csrf
                <input type="hidden" name="_method" value="PUT">
                <div class="flex-1 overflow-y-auto crm-scroll p-5 space-y-3">
                    @foreach($formFields as $field)
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 mb-1">
                                {{ $field->label }}@if($field->key !== 'password' && $field->required) * @endif
                                @if($field->key === 'password') <span class="font-normal text-slate-400">(optional)</span> @endif
                            </label>
                            @if($field->key === 'password')
                                <input name="password" type="password" x-model="form.password"
                                       placeholder="Leave blank to keep current"
                                       class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                            @elseif($field->key === 'role' || $field->type === 'select')
                                <select name="{{ $field->key }}" x-model="form['{{ $field->key }}']" @required($field->required)
                                        class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                                    <option value="">Select</option>
                                    @if($field->key === 'role')
                                        @foreach($roleLabels as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    @else
                                        @foreach($field->options ?? [] as $opt)
                                            <option value="{{ $opt }}">{{ $opt }}</option>
                                        @endforeach
                                    @endif
                                </select>
                            @elseif($field->type === 'textarea')
                                <textarea name="{{ $field->key }}" x-model="form['{{ $field->key }}']" rows="3" @required($field->required)
                                          class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20"></textarea>
                            @elseif($field->type === 'phone')
                                <input name="{{ $field->key }}" x-model="form['{{ $field->key }}']"
                                       type="tel" inputmode="tel" pattern="[0-9+\-\s()]*"
                                       oninput="this.value = this.value.replace(/[^0-9+\-\s()]/g, '')"
                                       @required($field->required)
                                       class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                            @elseif($field->type === 'number')
                                <input name="{{ $field->key }}" x-model="form['{{ $field->key }}']"
                                       type="number" step="any" inputmode="decimal"
                                       oninput="this.value = this.value.replace(/[^0-9.\-]/g, '')"
                                       @required($field->required)
                                       class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                            @elseif($field->type === 'date')
                                <input name="{{ $field->key }}" x-model="form['{{ $field->key }}']"
                                       type="date" @required($field->required)
                                       class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                            @elseif($field->type === 'time')
                                <input name="{{ $field->key }}" x-model="form['{{ $field->key }}']"
                                       type="time" @required($field->required)
                                       class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                            @else
                                <input name="{{ $field->key }}"
                                       x-model="form['{{ $field->key }}']"
                                       type="{{ $field->type === 'email' ? 'email' : 'text' }}"
                                       @required($field->required)
                                       class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                            @endif
                        </div>
                    @endforeach

                    <div class="rounded-xl border border-line bg-mist/50 p-3 space-y-1.5" x-show="form.role !== 'admin' && form.id !== {{ auth()->id() }}">
                        <div class="text-[11px] font-bold uppercase tracking-wide text-slate-600">Account status</div>
                        <div class="flex items-center gap-4 text-[13px] font-semibold text-ink pt-0.5">
                            <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                <input type="radio" name="is_active" value="1" class="text-emerald-600 focus:ring-emerald-500"
                                       :checked="form.is_active == '1'" @change="form.is_active = '1'">
                                <span class="inline-flex items-center gap-1 text-emerald-700">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Active
                                </span>
                            </label>
                            <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                <input type="radio" name="is_active" value="0" class="text-rose-600 focus:ring-rose-500"
                                       :checked="form.is_active == '0'" @change="form.is_active = '0'">
                                <span class="inline-flex items-center gap-1 text-rose-700">
                                    <span class="w-2 h-2 rounded-full bg-rose-500"></span> Deactive
                                </span>
                            </label>
                        </div>
                        <p class="text-[11px] text-slate-400">If deactivated, user will be auto logged out on their next action or refresh.</p>
                    </div>

                    <div class="rounded-xl border border-line bg-mist/40 p-3 space-y-2">
                        <div class="text-[11px] font-bold uppercase tracking-wide text-slate-500">App access level</div>
                        <p class="text-[11px] text-slate-400">Dashboard always on</p>
                        <label class="flex items-center gap-2 text-[13px] font-semibold text-ink">
                            <input type="checkbox" name="access_crm" value="1" class="rounded border-line text-brand"
                                   x-model="form.access_crm">
                            CRM
                        </label>
                        <label class="flex items-center gap-2 text-[13px] font-semibold text-ink">
                            <input type="checkbox" name="access_call_crm" value="1" class="rounded border-line text-brand"
                                   x-model="form.access_call_crm">
                            CALL CRM
                        </label>
                        <label class="flex items-center gap-2 text-[13px] font-semibold text-ink">
                            <input type="checkbox" name="access_operations" value="1" class="rounded border-line text-brand"
                                   x-model="form.access_operations">
                            Operation
                        </label>
                        <label class="flex items-center gap-2 text-[13px] font-semibold text-ink">
                            <input type="checkbox" name="access_pincodes" value="1" class="rounded border-line text-brand"
                                   x-model="form.access_pincodes">
                            Pincode Manager
                        </label>
                        <div class="pt-2 border-t border-line text-[11px] font-bold uppercase tracking-wide text-slate-500">Record Actions</div>
                        <label class="flex items-center gap-2 text-[13px] font-semibold text-ink">
                            <input type="checkbox" name="can_edit" value="1" class="rounded border-line text-brand"
                                   x-model="form.can_edit">
                            Allow Edit Customers
                        </label>
                        <label class="flex items-center gap-2 text-[13px] font-semibold text-ink">
                            <input type="checkbox" name="can_delete" value="1" class="rounded border-line text-brand"
                                   x-model="form.can_delete">
                            Allow Delete Customers
                        </label>
                    </div>
                </div>
                <div class="shrink-0 border-t border-line px-5 py-3 bg-white">
                    <button type="submit" class="w-full rounded-lg bg-brand hover:bg-brand-dark text-white font-semibold py-2.5 text-[13px]">
                        Update user
                    </button>
                </div>
            </form>
        </aside>
    </div>
</div>
@endsection
