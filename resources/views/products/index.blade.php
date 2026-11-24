@extends('layouts.app')

@section('title', \App\Models\MenuSetting::getLabel('products', 'Product Management'))
@section('heading', \App\Models\MenuSetting::getLabel('products', 'Product Management'))
@section('subheading', 'Store and manage products. Fields, table columns & Excel auto-customize from Setting → Product Fields.')

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        <button type="button"
                @click="$dispatch('open-product-excel')"
                class="h-9 inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 px-3 text-[12px] font-semibold transition shadow-2xs">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm0 4h16M4 13h16M4 17h16M9 8v12M15 8v12"/></svg>
            <span>Excel Import / Export</span>
        </button>

        <button type="button"
                @click="$dispatch('open-add-product')"
                class="h-9 inline-flex items-center gap-1.5 rounded-lg bg-brand text-white hover:bg-brand-dark px-3.5 text-[12px] font-semibold transition shadow-2xs">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>+ Add Product</span>
        </button>

        @if(auth()->user()->isAdmin())
            <a href="{{ route('settings.index', ['tab' => 'product-fields']) }}"
               class="h-9 inline-flex items-center gap-1.5 rounded-lg border border-brand/30 bg-brand-soft text-brand hover:bg-brand hover:text-white px-3 text-[12px] font-semibold transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span>Product Fields</span>
            </a>
        @endif
    </div>
@endsection

@section('content')
@php
    $pageIds = $products->pluck('id')->map(fn ($id) => (int) $id)->values();

    $productsJson = $products->getCollection()->map(function ($p) use ($formFields) {
        $row = [
            'id' => $p->id,
            'name' => $p->name,
            'code' => $p->code ?? '',
            'price' => $p->price ? (string)$p->price : '0',
            'category' => $p->category ?? '',
            'unit' => $p->unit ?? '',
            'status' => $p->status ?? 'Active',
            'is_active' => (bool)$p->is_active,
            'description' => $p->description ?? '',
        ];
        foreach ($formFields as $field) {
            $val = $p->fieldValue($field->key);
            $row[$field->key] = is_array($val) ? implode(', ', $val) : (string) ($val ?? '');
        }
        return $row;
    })->values();

    $createDefaults = [];
    foreach ($formFields as $f) {
        $def = old($f->key, '');
        if ($def === '') {
            if ($f->type === 'select' && $f->auto_select && ! empty($f->options)) {
                $def = $f->options[0];
            } elseif ($f->key === 'status') {
                $def = 'Active';
            } elseif ($f->key === 'price') {
                $def = '0';
            }
        }
        $createDefaults[$f->key] = $def;
    }
@endphp

<div x-data="productManager({
        pageIds: @js($pageIds),
        products: @js($productsJson),
        createDefaults: @js($createDefaults)
     })"
     @open-product-excel.window="excelOpen = true"
     @open-add-product.window="openCreateModal()">

    {{-- Top Metrics Summary Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
        <a href="{{ route('products.index') }}"
           class="rounded-xl border p-3 flex items-center justify-between transition {{ $status === '' ? 'border-brand bg-brand-soft/60 shadow-xs' : 'border-line bg-white hover:border-slate-300' }}">
            <div>
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Products</div>
                <div class="text-xl font-extrabold text-ink mt-0.5">{{ number_format($allCount) }}</div>
            </div>
            <div class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
        </a>

        <a href="{{ route('products.index', ['status' => 'active']) }}"
           class="rounded-xl border p-3 flex items-center justify-between transition {{ strtolower($status) === 'active' ? 'border-emerald-500 bg-emerald-50/70 shadow-xs' : 'border-line bg-white hover:border-slate-300' }}">
            <div>
                <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Active</div>
                <div class="text-xl font-extrabold text-emerald-800 mt-0.5">{{ number_format($activeCount) }}</div>
            </div>
            <div class="w-9 h-9 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </a>

        <a href="{{ route('products.index', ['status' => 'inactive']) }}"
           class="rounded-xl border p-3 flex items-center justify-between transition {{ strtolower($status) === 'inactive' ? 'border-rose-400 bg-rose-50/70 shadow-xs' : 'border-line bg-white hover:border-slate-300' }}">
            <div>
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Inactive</div>
                <div class="text-xl font-extrabold text-slate-700 mt-0.5">{{ number_format($inactiveCount) }}</div>
            </div>
            <div class="w-9 h-9 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </a>

        <div class="rounded-xl border border-line bg-white p-3 flex items-center justify-between">
            <div>
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Categories</div>
                <div class="text-xl font-extrabold text-ink mt-0.5">{{ count($categories) }}</div>
            </div>
            <div class="w-9 h-9 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
            </div>
        </div>
    </div>

    {{-- Search & Filter Bar --}}
    <div class="mb-4 rounded-2xl border border-line bg-white p-3 shadow-2xs flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('products.index') }}" id="productFilterForm" class="flex flex-1 flex-wrap sm:flex-nowrap items-center gap-2 min-w-[260px]">
            <div class="crm-toolbar-group w-full">
                <label class="crm-toolbar-search">
                    <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"/></svg>
                    <input type="text" name="q" value="{{ $q }}"
                           placeholder="Search product name, code/SKU, category, description…"
                           autocomplete="off">
                </label>
                <span class="crm-toolbar-split"></span>
                <select name="category" onchange="this.form.submit()" class="crm-toolbar-select" title="Filter by Category">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" @selected($category === $cat)>{{ $cat }}</option>
                    @endforeach
                </select>
                <span class="crm-toolbar-split"></span>
                <select name="status" onchange="this.form.submit()" class="crm-toolbar-select" title="Filter by Status">
                    <option value="">All Status</option>
                    <option value="active" @selected(strtolower($status) === 'active')>Active</option>
                    <option value="inactive" @selected(strtolower($status) === 'inactive')>Inactive</option>
                </select>
            </div>
            <button type="submit" class="h-9 px-3.5 rounded-xl bg-brand hover:bg-brand-dark text-white text-[12px] font-semibold transition shrink-0">
                Search
            </button>
            @if($q !== '' || $status !== '' || $category !== '')
                <a href="{{ route('products.index') }}"
                   class="h-9 px-3 inline-flex items-center justify-center rounded-xl border border-line bg-mist/60 hover:bg-mist text-slate-700 text-[12px] font-semibold transition shrink-0">
                    Reset
                </a>
            @endif
        </form>

        <div class="flex items-center gap-2 text-[12px] text-slate-500 font-medium">
            <span>Showing <strong class="text-ink">{{ $products->total() }}</strong> product{{ $products->total() === 1 ? '' : 's' }}</span>
        </div>
    </div>

    {{-- Main Table Card --}}
    <div class="rounded-2xl border border-line bg-white shadow-2xs overflow-hidden relative">
        <div class="overflow-x-auto crm-scroll">
            <table class="w-full text-left text-[12.5px] border-collapse">
                <thead>
                    <tr class="bg-mist text-slate-600 uppercase tracking-wider text-[11px] font-bold border-b border-line">
                        <th class="w-10 px-3 py-3 text-center">
                            <input type="checkbox"
                                   @change="toggleSelectAll($event.target.checked)"
                                   :checked="isAllSelected()"
                                   class="rounded border-slate-300 text-brand focus:ring-brand/20">
                        </th>
                        @foreach($tableFields as $f)
                            <th class="px-3 py-3 border-r border-line/60 last:border-r-0 whitespace-nowrap">
                                {{ $f->label }}
                            </th>
                        @endforeach
                        <th class="w-24 px-3 py-3 text-center">Status</th>
                        <th class="w-24 px-3 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($products as $prod)
                        <tr class="hover:bg-slate-50/70 transition"
                            :class="selectedIds.includes({{ $prod->id }}) ? 'bg-brand-soft/30' : ''">
                            <td class="px-3 py-2.5 text-center" @click.stop>
                                <input type="checkbox"
                                       :value="{{ $prod->id }}"
                                       x-model="selectedIds"
                                       class="rounded border-slate-300 text-brand focus:ring-brand/20">
                            </td>

                            @foreach($tableFields as $f)
                                @php
                                    $val = $prod->fieldValue($f->key);
                                    if (is_array($val)) $val = implode(', ', $val);
                                @endphp
                                <td class="px-3 py-2.5 border-r border-slate-100 last:border-r-0 align-middle">
                                    @if($f->key === 'name')
                                        <div class="font-bold text-ink hover:text-brand cursor-pointer"
                                             @click="openEditModal({{ $prod->id }})">
                                            {{ $prod->name }}
                                        </div>
                                        @if($prod->code)
                                            <div class="text-[11px] text-slate-400 font-mono">SKU: {{ $prod->code }}</div>
                                        @endif
                                    @elseif($f->key === 'price')
                                        <span class="font-extrabold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200/80">
                                            ₹{{ number_format((float) ($val ?? 0), 2) }}
                                        </span>
                                    @elseif($f->key === 'category')
                                        @if($val)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                                {{ $val }}
                                            </span>
                                        @else
                                            <span class="text-slate-300">—</span>
                                        @endif
                                    @elseif($f->key === 'status')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold {{ strtolower((string)$val) === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                            {{ ucfirst((string)$val) }}
                                        </span>
                                    @else
                                        <span class="text-slate-700 truncate max-w-xs block" title="{{ (string) $val }}">
                                            {{ $val !== null && $val !== '' ? $val : '—' }}
                                        </span>
                                    @endif
                                </td>
                            @endforeach

                            <td class="px-3 py-2.5 text-center whitespace-nowrap align-middle" @click.stop>
                                <form method="POST" action="{{ route('products.update', $prod) }}" class="inline">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="quick_toggle_status" value="1">
                                    <button type="submit"
                                            title="Click to toggle status"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold transition shadow-2xs {{ $prod->is_active ? 'bg-emerald-100 hover:bg-emerald-200 text-emerald-800' : 'bg-slate-100 hover:bg-slate-200 text-slate-600' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $prod->is_active ? 'bg-emerald-600' : 'bg-slate-400' }}"></span>
                                        <span>{{ $prod->is_active ? 'Active' : 'Inactive' }}</span>
                                    </button>
                                </form>
                            </td>

                            <td class="px-3 py-2.5 text-right whitespace-nowrap align-middle" @click.stop>
                                <div class="inline-flex items-center gap-1.5">
                                    <button type="button"
                                            @click="openEditModal({{ $prod->id }})"
                                            title="Edit product"
                                            class="w-7 h-7 rounded-lg border border-line text-slate-600 hover:text-brand hover:bg-brand-soft inline-flex items-center justify-center transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>

                                    <form method="POST" action="{{ route('products.destroy', $prod) }}"
                                          onsubmit="return confirm('Delete this product permanently?');" class="inline">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                title="Delete product"
                                                class="w-7 h-7 rounded-lg border border-line text-slate-600 hover:text-rose-600 hover:bg-rose-50 inline-flex items-center justify-center transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($tableFields) + 3 }}" class="px-4 py-12 text-center text-slate-400">
                                <div class="max-w-xs mx-auto space-y-2">
                                    <div class="text-3xl text-slate-300">📦</div>
                                    <div class="font-semibold text-slate-600">No products found</div>
                                    <p class="text-[12px] text-slate-400">Click the button below to add your first product or import from Excel.</p>
                                    <button type="button" @click="openCreateModal()" class="mt-2 inline-flex items-center gap-1.5 rounded-lg bg-brand text-white px-3 py-1.5 text-xs font-semibold hover:bg-brand-dark transition">
                                        + Add Product
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($products->hasPages())
            <div class="p-3 border-t border-line bg-slate-50/50">
                {{ $products->links() }}
            </div>
        @endif

        {{-- Floating Bulk Actions Bar --}}
        <div x-show="selectedIds.length > 0"
             x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 translate-y-4"
             class="fixed bottom-6 left-1/2 -translate-x-1/2 z-40 bg-slate-900 text-white rounded-full shadow-2xl px-5 py-2.5 flex items-center gap-3 text-[12px]">
            <span class="font-bold text-amber-300" x-text="selectedIds.length + ' selected'"></span>
            <span class="h-4 w-px bg-slate-700"></span>

            <form method="POST" action="{{ route('products.bulk') }}" class="inline-flex items-center gap-2">
                @csrf
                <template x-for="id in selectedIds" :key="id">
                    <input type="hidden" name="ids[]" :value="id">
                </template>

                <button type="submit" name="action_type" value="active"
                        class="px-3 py-1 rounded-full bg-emerald-600 hover:bg-emerald-500 text-white font-semibold transition">
                    Mark Active
                </button>

                <button type="submit" name="action_type" value="inactive"
                        class="px-3 py-1 rounded-full bg-slate-700 hover:bg-slate-600 text-slate-200 font-semibold transition">
                    Mark Inactive
                </button>

                <button type="submit" name="action_type" value="delete"
                        onclick="return confirm('Delete selected products?');"
                        class="px-3 py-1 rounded-full bg-rose-600 hover:bg-rose-500 text-white font-semibold transition">
                    Delete
                </button>
            </form>

            <button type="button" @click="selectedIds = []" class="text-slate-400 hover:text-white ml-2 text-sm" title="Clear selection">✕</button>
        </div>
    </div>

    {{-- Add / Edit Product Slide-Over Modal --}}
    <div x-show="drawerOpen"
         x-cloak
         class="fixed inset-0 z-50 overflow-hidden"
         @keydown.escape.window="drawerOpen = false">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity"
             @click="drawerOpen = false"></div>

        <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
            <div class="w-screen max-w-md bg-white shadow-2xl flex flex-col">
                {{-- Drawer Header --}}
                <div class="px-5 py-4 bg-slate-50 border-b border-line flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-ink" x-text="isEditing ? 'Edit Product' : 'Add New Product'"></h3>
                        <p class="text-[11px] text-slate-500">Configure product details. Dynamic fields appear based on Settings.</p>
                    </div>
                    <button type="button" @click="drawerOpen = false" class="text-slate-400 hover:text-ink text-xl">✕</button>
                </div>

                {{-- Drawer Body / Form --}}
                <form :action="formAction" method="POST" class="flex-1 overflow-y-auto crm-scroll p-5 space-y-4">
                    @csrf
                    <template x-if="isEditing">
                        <input type="hidden" name="_method" value="PUT">
                    </template>

                    @foreach($formFields as $f)
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">
                                {{ $f->label }}
                                @if($f->required)
                                    <span class="text-rose-500">*</span>
                                @endif
                            </label>

                            @if($f->type === 'select')
                                <select name="{{ $f->key }}"
                                        x-model="formData['{{ $f->key }}']"
                                        @required($f->required)
                                        class="w-full rounded-xl border border-line bg-mist/50 hover:bg-white focus:bg-white focus:border-brand focus:ring-2 focus:ring-brand/20 px-3 py-2 text-[12.5px] outline-none transition">
                                    <option value="">Select {{ $f->label }}</option>
                                    @foreach($f->options ?? [] as $opt)
                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                    @endforeach
                                </select>
                            @elseif($f->type === 'textarea')
                                <textarea name="{{ $f->key }}"
                                          x-model="formData['{{ $f->key }}']"
                                          rows="3"
                                          @required($f->required)
                                          placeholder="Enter {{ strtolower($f->label) }}…"
                                          class="w-full rounded-xl border border-line bg-white px-3 py-2 text-[12.5px] outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition"></textarea>
                            @elseif($f->type === 'number')
                                <input type="number" step="any" inputmode="decimal"
                                       name="{{ $f->key }}"
                                       x-model="formData['{{ $f->key }}']"
                                       @required($f->required)
                                       placeholder="{{ $f->label }}"
                                       class="w-full rounded-xl border border-line bg-white px-3 py-2 text-[12.5px] outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">
                            @elseif($f->type === 'date')
                                <input type="date"
                                       name="{{ $f->key }}"
                                       x-model="formData['{{ $f->key }}']"
                                       @required($f->required)
                                       class="w-full rounded-xl border border-line bg-white px-3 py-2 text-[12.5px] outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">
                            @else
                                <input type="{{ $f->type === 'email' ? 'email' : ($f->type === 'phone' ? 'tel' : 'text') }}"
                                       name="{{ $f->key }}"
                                       x-model="formData['{{ $f->key }}']"
                                       @required($f->required)
                                       placeholder="Enter {{ strtolower($f->label) }}…"
                                       class="w-full rounded-xl border border-line bg-white px-3 py-2 text-[12.5px] outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">
                            @endif
                        </div>
                    @endforeach

                    <div class="pt-4 border-t border-line flex items-center justify-end gap-2">
                        <button type="button" @click="drawerOpen = false"
                                class="px-4 py-2 rounded-xl border border-line text-slate-700 hover:bg-mist text-[12.5px] font-semibold transition">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-5 py-2 rounded-xl bg-brand hover:bg-brand-dark text-white text-[12.5px] font-semibold transition shadow-xs">
                            <span x-text="isEditing ? 'Save Changes' : 'Create Product'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Excel Import / Export Modal --}}
    <div x-show="excelOpen"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="excelOpen = false">
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs" @click="excelOpen = false"></div>

        <div class="relative bg-white rounded-2xl shadow-2xl border border-line w-full max-w-lg p-6 space-y-5">
            <div class="flex items-center justify-between pb-3 border-b border-line">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm0 4h16M4 13h16M4 17h16M9 8v12M15 8v12"/></svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-ink">Products Excel Center</h3>
                        <p class="text-[11px] text-slate-500">Import products in bulk or export existing catalog.</p>
                    </div>
                </div>
                <button type="button" @click="excelOpen = false" class="text-slate-400 hover:text-ink text-xl">✕</button>
            </div>

            {{-- Export Section --}}
            <div class="p-4 rounded-xl border border-emerald-200 bg-emerald-50/50 space-y-2">
                <div class="font-bold text-[12.5px] text-emerald-950">Download Current Products</div>
                <p class="text-[11px] text-emerald-800">Export all products as an Excel spreadsheet with configured product fields.</p>
                <div class="pt-1 flex flex-wrap gap-2">
                    <a href="{{ route('products.export') }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-[12px] font-semibold transition shadow-2xs">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        <span>Download Excel (.xlsx)</span>
                    </a>

                    <a href="{{ route('products.demo') }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-emerald-300 bg-white text-emerald-800 hover:bg-emerald-50 text-[12px] font-semibold transition">
                        <span>Sample Template</span>
                    </a>
                </div>
            </div>

            {{-- Import Section --}}
            <form method="POST" action="{{ route('products.import') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <div class="font-bold text-[12.5px] text-ink">Upload Products File</div>
                <div class="border-2 border-dashed border-slate-300 hover:border-brand rounded-xl p-5 text-center transition cursor-pointer relative bg-mist/30">
                    <input type="file" name="excel_file" accept=".xlsx,.xls,.csv" required
                           class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                           @change="fileName = $event.target.files[0]?.name || ''">
                    <div class="space-y-1">
                        <div class="text-2xl text-slate-400">📄</div>
                        <div class="text-[12.5px] font-semibold text-slate-700" x-text="fileName || 'Click or drag Excel file here (.xlsx, .csv)'"></div>
                        <div class="text-[10.5px] text-slate-400">Products are matched by SKU/code or name. Existing records will be updated.</div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="excelOpen = false"
                            class="px-4 py-2 rounded-xl border border-line text-slate-700 hover:bg-mist text-[12px] font-semibold transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-5 py-2 rounded-xl bg-brand hover:bg-brand-dark text-white text-[12px] font-semibold transition shadow-xs">
                        Start Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function productManager(config) {
    return {
        selectedIds: [],
        pageIds: config.pageIds || [],
        products: config.products || [],
        createDefaults: config.createDefaults || {},
        drawerOpen: false,
        excelOpen: false,
        isEditing: false,
        formAction: '{{ route("products.store") }}',
        formData: {},
        fileName: '',

        toggleSelectAll(checked) {
            if (checked) {
                this.selectedIds = [...new Set([...this.selectedIds, ...this.pageIds])];
            } else {
                this.selectedIds = this.selectedIds.filter(id => !this.pageIds.includes(id));
            }
        },

        isAllSelected() {
            return this.pageIds.length > 0 && this.pageIds.every(id => this.selectedIds.includes(id));
        },

        openCreateModal() {
            this.isEditing = false;
            this.formAction = '{{ route("products.store") }}';
            this.formData = Object.assign({}, this.createDefaults);
            this.drawerOpen = true;
        },

        openEditModal(id) {
            const p = this.products.find(item => item.id === id);
            if (!p) return;
            this.isEditing = true;
            this.formAction = '{{ url("/products") }}/' + id;
            this.formData = Object.assign({}, p);
            this.drawerOpen = true;
        }
    };
}
</script>
@endsection
