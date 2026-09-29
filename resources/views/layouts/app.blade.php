<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CRM')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Segoe UI"', '"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: {
                        ink: '#201f1e',
                        mist: '#f3f2f1',
                        line: '#edebe9',
                        brand: { DEFAULT: '#0f6cbd', soft: '#deecf9', dark: '#115ea3' },
                        accent: '#0f766e'
                    }
                }
            }
        }
    </script>
    <style>
        [x-cloak]{display:none!important}
        html,body{height:100%;overflow:hidden}
        .crm-scroll::-webkit-scrollbar{width:8px;height:8px}
        .crm-scroll::-webkit-scrollbar-thumb{background:#c8c6c4;border-radius:8px}
        .crm-scroll::-webkit-scrollbar-thumb:hover{background:#a19f9d}
        .nav-item{border-radius:4px}
        .nav-item.active{background:#deecf9;color:#0f6cbd;font-weight:600}
        .list-item{border-left:3px solid transparent; transition: background .18s ease, box-shadow .18s ease, border-color .18s ease}
        .list-item:hover{background:#f5f5f5}
        .list-item.active{background:#deecf9;border-left-color:#0f6cbd;box-shadow:inset 0 0 0 1px #201f1e}
        .stat-card{transition:transform .15s ease}
        .stat-card:hover{transform:translateY(-1px)}
        .desk-stage{position:relative}
        .desk-pane{position:absolute; inset:0}
        .slide-list{animation:slideList .38s cubic-bezier(.22,1,.36,1) both}
        .slide-read{animation:slideRead .42s cubic-bezier(.22,1,.36,1) .06s both}
        @keyframes slideList{
            from{opacity:0; transform:translateX(-28px)}
            to{opacity:1; transform:translateX(0)}
        }
        @keyframes slideRead{
            from{opacity:0; transform:translateX(36px)}
            to{opacity:1; transform:translateX(0)}
        }
        .table-row-press{transition: background .15s ease, transform .15s ease}
        .table-row-press:active{transform:scale(.995)}
        tr.table-row-press{cursor:pointer}
        .crm-check{width:18px;height:18px;border-radius:4px;border:1.5px solid #c8c6c4;background:#fff;display:inline-flex;align-items:center;justify-content:center;padding:0;cursor:pointer}
        .crm-check.on{background:#0f6cbd;border-color:#0f6cbd}
        .crm-check svg{width:12px;height:12px;color:#fff;opacity:0}
        .crm-check.on svg{opacity:1}
        .crm-toolbar-group{display:flex;align-items:center;flex:1;min-width:0;max-width:760px;height:38px;background:#f3f2f1;border:1px solid #edebe9;border-radius:10px;overflow:hidden}
        .crm-toolbar-group:focus-within{background:#fff;border-color:#0f6cbd;box-shadow:0 0 0 3px rgba(15,108,189,.12)}
        .crm-toolbar-search{flex:1;min-width:140px;display:flex;align-items:center;gap:8px;padding:0 12px;height:100%}
        .crm-toolbar-search input{flex:1;width:100%;background:transparent;border:0;outline:none;font-size:13px;color:#201f1e}
        .crm-toolbar-search input::placeholder{color:#a19f9d}
        .crm-toolbar-split{width:1px;height:18px;background:#e1dfdd;flex-shrink:0}
        .crm-toolbar-select{border:0;background:transparent url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23605e5c' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E") no-repeat right 10px center;font-size:12.5px;font-weight:600;color:#201f1e;padding:0 28px 0 12px;height:100%;outline:none;appearance:none;-webkit-appearance:none;cursor:pointer;max-width:150px}
    </style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    @stack('head')
</head>
<body class="font-sans text-ink antialiased bg-white" x-data="crmShell()" x-cloak>
<div class="h-screen flex">
    {{-- Left nav — Outlook style --}}
    <aside class="w-[200px] shrink-0 border-r border-line bg-white flex flex-col h-full">
        <div class="px-4 py-4 border-b border-line">
            <div class="text-[18px] font-bold tracking-tight text-ink">Pulse<span class="text-brand">CRM</span></div>
        </div>
        <nav class="p-2 flex-1 overflow-y-auto crm-scroll text-[13px]">
            @php
                $navUser = auth()->user();
                $links = [
                    ['dashboard', 'Dashboard'],
                ];
                if ($navUser->canAccessCrm()) {
                    $links[] = ['customers.index', 'CRM'];
                }
                if ($navUser->canAccessOperations()) {
                    $links[] = ['operations.index', 'TEAM OPS'];
                }
                if ($navUser->canAccessInventory()) {
                    $links[] = ['inventory.index', 'Inventory'];
                }
                if ($navUser->canSeeAllRecords()) {
                    $links[] = ['activation.index', 'Activation'];
                }
                if ($navUser->isAdmin()) {
                    $links = array_merge($links, [
                        ['users.index', 'Users'],
                    ]);
                }
            @endphp
            <div class="px-2 pt-1 pb-2 text-[11px] font-semibold text-slate-500">Favorites</div>
            @foreach($links as [$route, $label])
                <a href="{{ route($route) }}"
                   class="nav-item flex items-center gap-2 px-3 py-1.5 mb-0.5 {{ request()->routeIs($route) || request()->routeIs(str_replace('.index','.*',$route)) || ($route === 'settings.index' && request()->routeIs('fields.*', 'note-templates.*', 'operation-settings.*', 'inventory-types.*')) ? 'active' : 'text-ink hover:bg-mist' }}">
                    <span class="uppercase tracking-wide">{{ $label }}</span>
                </a>
            @endforeach

            <div class="px-2 pt-4 pb-2 text-[11px] font-semibold text-slate-500">Automations</div>
            <div class="px-3 py-1.5 text-[12px] text-ink">Auto Call</div>
            <div class="px-3 py-1.5 text-[12px] text-ink">Auto WhatsApp</div>
            <div class="px-3 py-1.5 text-[12px] text-ink">Auto Email</div>
            <div class="px-3 py-1.5 text-[12px] text-ink">Auto SMS</div>
        </nav>
        <div class="p-3 border-t border-line text-[12px]">
            <div class="flex items-center justify-between gap-2">
                <div class="font-semibold truncate min-w-0">{{ auth()->user()->name }}</div>
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('settings.index') }}" title="Setting"
                       class="w-7 h-7 shrink-0 inline-flex items-center justify-center rounded-lg border border-line text-slate-500 hover:bg-brand-soft hover:text-brand hover:border-brand/30 transition {{ request()->routeIs('settings.*', 'fields.*', 'note-templates.*', 'operation-settings.*') ? 'bg-brand-soft text-brand border-brand/30' : '' }}">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </a>
                @endif
            </div>
            <div class="flex items-center justify-between gap-2 mt-0.5">
                <span class="text-slate-500 truncate">{{ auth()->user()->username }} · {{ auth()->user()->roleLabel() }}</span>
                <form method="POST" action="{{ route('logout') }}" onsubmit="return confirm('Logout karna hai?')">@csrf
                    <button class="text-brand font-semibold hover:underline">Logout</button>
                </form>
            </div>
        </div>
    </aside>

    <div class="flex-1 min-w-0 h-full flex flex-col">
        @hasSection('split')
            @yield('split')
        @else
            <header class="shrink-0 border-b border-line bg-white px-5 py-3 flex items-center justify-between gap-3">
                <div>
                    <h1 class="text-[18px] font-semibold">@yield('heading', 'Dashboard')</h1>
                    <p class="text-[12px] text-slate-500">@yield('subheading')</p>
                </div>
                <div class="flex items-center gap-2">@yield('actions')</div>
            </header>
            <div class="flex-1 overflow-y-auto crm-scroll bg-mist/40 p-5">
                @if(session('success'))
                    <div class="mb-3 rounded border border-emerald-200 bg-emerald-50 text-emerald-800 px-3 py-2 text-[13px]">{{ session('success') }}</div>
                @endif
                @if($errors->any())
                    <div class="mb-3 rounded border border-rose-200 bg-rose-50 text-rose-800 px-3 py-2 text-[13px]">{{ $errors->first() }}</div>
                @endif
                @yield('content')
            </div>
        @endif
    </div>
</div>

{{-- Right compose drawer: WhatsApp / Email / SMS --}}
<div class="fixed inset-0 z-[60]" x-show="composeOpen" x-cloak>
    <div class="absolute inset-0 bg-ink/25" x-show="composeOpen" x-transition.opacity.duration.200ms @click="closeCompose()"></div>
    <aside class="absolute right-0 top-0 h-full w-full max-w-[440px] bg-white border-l border-line shadow-xl flex flex-col"
           x-show="composeOpen"
           x-transition:enter="transition ease-out duration-300"
           x-transition:enter-start="translate-x-full"
           x-transition:enter-end="translate-x-0"
           x-transition:leave="transition ease-in duration-200"
           x-transition:leave-start="translate-x-0"
           x-transition:leave-end="translate-x-full"
           @click.stop>
        <div class="px-5 py-4 border-b border-line flex items-center justify-between shrink-0">
            <div class="min-w-0">
                <div class="text-[11px] uppercase tracking-[0.14em] text-slate-400 font-bold" x-text="compose.channel"></div>
                <div class="text-[17px] font-semibold truncate" x-text="'Send to ' + (compose.name || 'customer')"></div>
                <div class="text-[12px] text-slate-500 truncate"
                     x-text="compose.channel === 'email' ? (compose.email || '—') : (compose.phone || '—')"></div>
            </div>
            <button type="button" @click="closeCompose()" class="w-8 h-8 rounded hover:bg-mist text-slate-500">✕</button>
        </div>

        <form class="flex-1 flex flex-col min-h-0" @submit.prevent="submitCompose($el)">
            <div class="flex-1 overflow-y-auto crm-scroll p-5 space-y-3">
                <template x-if="compose.channel === 'email'">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1">Subject</label>
                        <input type="text" x-model="compose.subject"
                               class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                    </div>
                </template>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Message *</label>
                    <textarea x-model="compose.message" rows="6" required placeholder="Type your message…"
                              class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20 resize-y"></textarea>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Link (optional)</label>
                    <input type="url" x-model="compose.link" placeholder="https://…"
                           class="w-full rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand/20">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Attach files</label>
                    <input type="file" name="files[]" id="composeFiles" multiple
                           accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,image/*,application/pdf"
                           class="block w-full text-[12px] text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-soft file:text-brand file:px-3 file:py-1.5 file:font-semibold file:cursor-pointer">
                    <p class="text-[11px] text-slate-400 mt-1">PDF, images, docs — max 5 files, 10MB each. File links add to the message.</p>
                </div>
            </div>

            <div class="shrink-0 border-t border-line px-5 py-3 bg-white">
                <button type="submit"
                        class="w-full rounded-lg bg-brand hover:bg-brand-dark text-white font-semibold py-2.5 text-[13px] disabled:opacity-60"
                        :disabled="composeSending"
                        x-text="composeSending ? 'Sending…' : ('Send via ' + (compose.channel === 'whatsapp' ? 'WhatsApp' : compose.channel === 'email' ? 'Email' : 'SMS'))"></button>
            </div>
        </form>
    </aside>
</div>

{{-- Send Leads to Gmail Modal with Real Cyan Table Preview --}}
<div class="fixed inset-0 z-[70] flex items-center justify-center p-3 sm:p-5"
     x-show="emailModalOpen"
     x-cloak>
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
         x-show="emailModalOpen"
         x-transition.opacity.duration.200ms
         @click="emailModalOpen = false"></div>

    <div class="relative w-full max-w-4xl max-h-[92vh] bg-white rounded-2xl shadow-2xl flex flex-col overflow-hidden border border-slate-200 z-10"
         x-show="emailModalOpen"
         x-transition:enter="transition ease-out duration-250"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         @click.stop>

        {{-- Modal Header --}}
        <div class="px-6 py-4 border-b border-line flex items-center justify-between bg-slate-50 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-rose-50 border border-rose-200 flex items-center justify-center text-rose-600 shadow-xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-800">Send Customer Leads via Gmail</h3>
                    <p class="text-[12px] text-slate-500" x-text="emailModalCount + ' customers selected • Excel-style cyan header & borders'"></p>
                </div>
            </div>
            <button type="button" @click="emailModalOpen = false"
                    class="w-8 h-8 rounded-lg hover:bg-slate-200/70 text-slate-400 hover:text-slate-700 flex items-center justify-center transition">✕</button>
        </div>

        {{-- Recipient Email Input & Quick Copy --}}
        <div class="px-6 py-3.5 bg-slate-50/70 border-b border-line shrink-0 space-y-2">
            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600">
                Recipient Email (Enter Manually)
            </label>
            <div class="flex items-center gap-2">
                <div class="relative flex-1">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.206"/></svg>
                    </div>
                    <input type="email" x-model="emailModalRecipient"
                           placeholder="Type recipient email address (e.g. boss@gmail.com, partner@company.com)..."
                           class="w-full rounded-xl border border-line pl-9 pr-3.5 py-2 text-[13px] bg-white focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition font-medium">
                </div>
                <button type="button" @click="copyEmailTableToClipboard()"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-[12px] font-semibold transition shrink-0 shadow-xs"
                        :class="emailModalCopied ? 'border-emerald-400 text-emerald-700 bg-emerald-50' : ''">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <span x-text="emailModalCopied ? 'Copied to Clipboard!' : 'Copy Table'"></span>
                </button>
            </div>
            <p class="text-[11px] text-slate-400">You can enter the email address above, or leave it blank to type it in Gmail.</p>
        </div>

        {{-- Live Table Preview --}}
        <div class="flex-1 overflow-y-auto crm-scroll p-6 bg-slate-100/60 min-h-[220px]">
            <div class="flex items-center justify-between mb-2.5">
                <div class="flex items-center gap-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-600">Live Table Preview</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-[#8fe3eb]/40 text-slate-800 border border-[#8fe3eb]">
                        Cyan Header (#8fe3eb) &amp; Black Borders
                    </span>
                </div>
                <span class="text-[11px] text-slate-400" x-text="'Showing ' + emailModalCount + ' rows'"></span>
            </div>

            <div x-show="emailModalLoading" class="py-12 text-center text-slate-400 text-sm animate-pulse">
                Loading table data…
            </div>

            <div x-show="!emailModalLoading" class="border border-slate-400 rounded-lg overflow-x-auto shadow-sm bg-white p-1">
                <div x-html="emailModalHtml"></div>
            </div>
        </div>

        {{-- Instructions Notice --}}
        <div class="px-6 py-3 bg-amber-50/90 border-t border-amber-200/80 flex items-center justify-between gap-3 text-[12px] text-amber-900 shrink-0">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="w-6 h-6 rounded-full bg-amber-200/80 text-amber-800 font-bold flex items-center justify-center text-[12px] shrink-0">ℹ</span>
                <span class="truncate">
                    In Gmail compose: press <kbd class="px-1.5 py-0.5 rounded bg-white border border-amber-300 font-mono text-[11px] font-bold shadow-xs text-amber-900">Ctrl + V</kbd> to paste this exact colored Excel table!
                </span>
            </div>
            <span x-show="emailModalCopied" class="text-emerald-700 font-bold text-[12px] shrink-0 animate-pulse">
                ✓ Table copied to clipboard!
            </span>
        </div>

        {{-- Footer --}}
        <div class="px-6 py-4 border-t border-line bg-white flex items-center justify-between gap-3 shrink-0">
            <button type="button" @click="emailModalOpen = false"
                    class="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-100 text-[13px] font-semibold transition">
                Close
            </button>
            <div class="flex items-center gap-2.5">
                <button type="button" @click="copyEmailTableToClipboard()"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-[13px] font-semibold shadow-xs transition">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <span x-text="emailModalCopied ? 'Table Copied!' : 'Copy Table Only'"></span>
                </button>
                <button type="button" @click="openGmailFromModal()"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-black text-white text-[13px] font-bold shadow-lg shadow-slate-900/20 hover:scale-[1.01] active:scale-[0.99] transition">
                    <svg class="w-4 h-4 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <span>Open in Gmail Web</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function crmShell() {
    return {
        selectedId: null,
        title: '',
        html: '',
        loading: false,
        deskKey: 0,
        formOpen: false,
        formMode: 'create',
        form: { id: null },
        composeOpen: false,
        composeSending: false,
        compose: { channel: 'whatsapp', customerId: null, name: '', phone: '', email: '', message: '', subject: '', link: '' },
        emailModalOpen: false,
        emailModalLoading: false,
        emailModalCopied: false,
        emailModalRecipient: '',
        emailModalSubject: '',
        emailModalHtml: '',
        emailModalCount: 0,
        emailModalIds: [],
        emailModalData: null,
        selectedIds: [],
        lastSelectId: null,
        isSelected(id) {
            return this.selectedIds.map(Number).includes(Number(id));
        },
        pageSelected(ids) {
            const list = (ids || []).map(Number);
            const current = this.selectedIds.map(Number);
            return list.length > 0 && list.every(id => current.includes(id));
        },
        toggleSelect(id, event) {
            id = Number(id);
            const pageIds = (window.CRM_PAGE_IDS || []).map(Number);
            const current = this.selectedIds.map(Number);

            if (event?.shiftKey && this.lastSelectId != null) {
                event.preventDefault();
                const from = pageIds.indexOf(Number(this.lastSelectId));
                const to = pageIds.indexOf(id);
                if (from !== -1 && to !== -1) {
                    const range = pageIds.slice(Math.min(from, to), Math.max(from, to) + 1);
                    this.selectedIds = [...new Set([...current, ...range])];
                    return;
                }
            }

            this.selectedIds = current.includes(id)
                ? current.filter(x => x !== id)
                : [...current, id];
            this.lastSelectId = id;
        },
        togglePage(ids) {
            const list = (ids || []).map(Number);
            const current = this.selectedIds.map(Number);
            this.selectedIds = this.pageSelected(list)
                ? current.filter(id => !list.includes(id))
                : [...new Set([...current, ...list])];
            this.lastSelectId = list[0] ?? null;
        },
        clearSelection() {
            this.selectedIds = [];
            this.lastSelectId = null;
        },
        prepareBulkMap(event) {
            if (!this.selectedIds.length) {
                event.preventDefault();
                alert('Select customers to map.');
                return;
            }
            const form = event.target;
            form.querySelectorAll('input[data-bulk-id]').forEach(el => el.remove());
            this.selectedIds.map(Number).forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = String(id);
                input.setAttribute('data-bulk-id', '1');
                form.appendChild(input);
            });
        },
        async sendBulkWhatsapp() {
            if (!this.selectedIds.length) {
                alert('Please select at least one customer.');
                return;
            }
            try {
                const res = await fetch(`{{ route('customers.bulk-whatsapp') }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    },
                    body: JSON.stringify({ ids: this.selectedIds })
                });
                const data = await res.json();
                if (!res.ok) {
                    if (data.settings_url && confirm((data.message || 'WhatsApp number not set.') + '\n\nDo you want to open Settings now?')) {
                        window.location.href = data.settings_url;
                    } else {
                        alert(data.message || 'Could not prepare WhatsApp message');
                    }
                    return;
                }
                if (data.url) {
                    window.open(data.url, '_blank');
                }
            } catch (e) {
                alert('Failed to send WhatsApp message.');
            }
        },
        async openEmailModal(ids = null) {
            const targetIds = (ids && ids.length) ? ids : this.selectedIds;
            if (!targetIds || !targetIds.length) {
                alert('Please select at least one customer.');
                return;
            }
            this.emailModalIds = targetIds.map(Number);
            this.emailModalLoading = true;
            this.emailModalCopied = false;
            this.emailModalRecipient = '';
            this.emailModalOpen = true;

            try {
                const res = await fetch(`{{ route('customers.bulk-email') }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    },
                    body: JSON.stringify({ ids: this.emailModalIds, recipient: '' })
                });
                const data = await res.json();
                if (!res.ok) {
                    alert(data.message || 'Could not load customer email data.');
                    this.emailModalOpen = false;
                    return;
                }
                this.emailModalData = data;
                this.emailModalHtml = data.html_table || '';
                this.emailModalSubject = data.subject || '';
                this.emailModalCount = data.count || this.emailModalIds.length;
            } catch (e) {
                alert('Failed to load customer email data.');
                this.emailModalOpen = false;
            } finally {
                this.emailModalLoading = false;
            }
        },
        async copyEmailTableToClipboard() {
            if (!this.emailModalHtml) return;
            const ok = await copyHtmlTableToClipboard(this.emailModalHtml, this.emailModalData?.subject || '');
            if (ok) {
                this.emailModalCopied = true;
                setTimeout(() => { this.emailModalCopied = false; }, 3000);
            }
        },
        async openGmailFromModal() {
            if (!this.emailModalHtml) return;
            // 1. Copy styled table to clipboard right now (during active click gesture)
            await copyHtmlTableToClipboard(this.emailModalHtml, this.emailModalData?.subject || '');
            this.emailModalCopied = true;

            // 2. Insert user entered recipient into Gmail URL
            let recipient = (this.emailModalRecipient || '').trim();
            let url = this.emailModalData?.gmail_url || '';
            if (recipient !== '') {
                url = url.replace(/(&to=)[^&]*/, '$1' + encodeURIComponent(recipient));
            }

            // 3. Open Gmail
            if (url) {
                window.open(url, '_blank');
            }
        },
        async sendBulkEmail() {
            if (!this.selectedIds.length) {
                alert('Please select at least one customer.');
                return;
            }
            await this.openEmailModal(this.selectedIds);
        },
        async selectCustomer(id) {
            if (!id) return;
            const switching = this.selectedId && this.selectedId !== id;
            this.selectedId = id;
            if (!switching) this.deskKey++;
            this.loading = true;
            this.html = '<div class="p-8 text-center text-slate-400 text-sm animate-pulse">Loading details…</div>';
            try {
                const res = await fetch(`{{ url('/customers') }}/${id}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!res.ok) {
                    this.html = '<div class="p-8 text-center text-rose-600 text-sm">This customer is not mapped to you.</div>';
                    return;
                }
                const data = await res.json();
                this.title = data.customer.name;
                this.html = data.html;
            } finally {
                this.loading = false;
            }
        },
        closeDesk() {
            this.selectedId = null;
            this.title = '';
            this.html = '';
        },
        openCreateForm() {
            this.formMode = 'create';
            const newForm = { id: null };
            (window.CRM_FORM_FIELDS || []).forEach(f => {
                let defaultVal = '';
                if (f.key === 'status') {
                    if (Array.isArray(f.options) && f.options.length > 0) {
                        const newOpt = f.options.find(o => String(o).trim().toLowerCase() === 'new');
                        defaultVal = newOpt ?? f.options[0];
                    } else {
                        defaultVal = 'New';
                    }
                }
                newForm[f.key] = defaultVal;
            });
            this.form = newForm;
            this.formOpen = true;
            this.$nextTick(() => {
                const drawer = document.getElementById('customerDrawerForm');
                if (drawer) {
                    const sel = drawer.querySelector('select[name="status"]');
                    if (sel && this.form.status) {
                        const valStr = String(this.form.status).trim().toLowerCase();
                        const matchedOption = Array.from(sel.options).find(o => 
                            o.value.trim().toLowerCase() === valStr
                        );
                        if (matchedOption) {
                            sel.value = matchedOption.value;
                            this.form.status = matchedOption.value;
                        }
                    }
                }
            });
        },
        openEditForm(c) {
            this.formMode = 'edit';
            const newForm = { id: c.id };
            (window.CRM_FORM_FIELDS || []).forEach(f => {
                let val = c[f.key];
                if ((val === undefined || val === null) && c.meta) val = c.meta[f.key];
                if (val !== undefined && val !== null && (f.type === 'select' || f.key === 'status') && Array.isArray(f.options)) {
                    const match = f.options.find(opt => String(opt).trim().toLowerCase() === String(val).trim().toLowerCase());
                    if (match !== undefined) {
                        val = match;
                    }
                }
                newForm[f.key] = val ?? '';
            });
            this.form = newForm;
            this.formOpen = true;
            this.$nextTick(() => {
                const drawer = document.getElementById('customerDrawerForm');
                if (drawer) {
                    (window.CRM_FORM_FIELDS || []).forEach(f => {
                        if (f.type === 'select' || f.key === 'status') {
                            const sel = drawer.querySelector(`select[name="${f.key}"]`);
                            if (sel && this.form[f.key]) {
                                const valStr = String(this.form[f.key]).trim().toLowerCase();
                                const matchedOption = Array.from(sel.options).find(o => 
                                    o.value.trim().toLowerCase() === valStr
                                );
                                if (matchedOption) {
                                    sel.value = matchedOption.value;
                                    this.form[f.key] = matchedOption.value;
                                } else {
                                    const newOpt = new Option(this.form[f.key], this.form[f.key], true, true);
                                    sel.add(newOpt);
                                    sel.value = this.form[f.key];
                                }
                            }
                        }
                    });
                }
            });
        },
        closeForm() {
            this.formOpen = false;
        },
        async openCompose(customerId, channel) {
            if (!customerId) {
                alert('Pehle customer select / open karein');
                return;
            }
            if (channel === 'call') {
                return runAutoAction(customerId, 'call');
            }
            try {
                const res = await fetch(`{{ url('/customers') }}/${customerId}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                const c = data.customer;
                if (channel !== 'email' && !(c.phone || '').trim()) {
                    alert('Phone number missing.');
                    return;
                }
                if (channel === 'email' && !(c.email || '').trim()) {
                    alert('Email missing.');
                    return;
                }
                this.compose = {
                    channel,
                    customerId: c.id,
                    name: c.name || '',
                    phone: c.phone || '',
                    email: c.email || '',
                    message: '',
                    subject: channel === 'email' ? ('Hello ' + (c.name || '')) : '',
                    link: ''
                };
                this.composeOpen = true;
                this.$nextTick(() => {
                    const input = document.getElementById('composeFiles');
                    if (input) input.value = '';
                });
            } catch (e) {
                alert('Could not open composer');
            }
        },
        closeCompose() {
            this.composeOpen = false;
            this.composeSending = false;
        },
        async submitCompose(form) {
            if (this.composeSending) return;
            const msg = (this.compose.message || '').trim();
            if (!msg) {
                alert('Please write a message.');
                return;
            }
            this.composeSending = true;
            try {
                const fd = new FormData(form);
                fd.set('channel', this.compose.channel);
                fd.set('message', this.compose.message);
                fd.set('subject', this.compose.subject || '');
                fd.set('link', this.compose.link || '');
                const res = await fetch(`{{ url('/customers') }}/${this.compose.customerId}/compose`, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    },
                    body: fd
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    alert(data.message || (data.errors && Object.values(data.errors).flat()[0]) || 'Send failed');
                    return;
                }
                if (data.html) this.html = data.html;
                const channel = this.compose.channel;
                this.closeCompose();
                if (data.open_url) openCrmChannel(data.open_url, channel);
            } catch (e) {
                alert('Could not send');
            } finally {
                this.composeSending = false;
            }
        },
        async postActivity(form) {
            const followCheck = form.querySelector('[name="follow_up"]');
            const followDate = form.querySelector('[name="follow_up_date"]');
            const timeOn = form.querySelector('[name="follow_up_time_on"]');
            const followTime = form.querySelector('[name="follow_up_time"]');
            if (followCheck?.checked && !followDate?.value) {
                alert('Follow-up date required when Follow up is checked.');
                followDate?.focus();
                return;
            }
            if (followCheck?.checked && timeOn?.checked && !followTime?.value) {
                alert('Select a follow-up time, or uncheck Time.');
                followTime?.focus();
                return;
            }
            const fd = new FormData(form);
            const res = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: fd
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                alert(data.message || 'Could not save activity');
                return;
            }
            if (data.html) this.html = data.html;
            if (data.customer_id != null) {
                const cell = document.querySelector(`[data-fu-cell="${data.customer_id}"]`);
                if (cell && data.follow_up_html != null) cell.innerHTML = data.follow_up_html;
                const listBadge = document.querySelector(`[data-fu-list="${data.customer_id}"]`);
                if (listBadge) {
                    if (data.follow_up && data.follow_up_label) {
                        listBadge.textContent = data.follow_up_label;
                        listBadge.classList.remove('hidden');
                    } else {
                        listBadge.textContent = '';
                        listBadge.classList.add('hidden');
                    }
                }
                if (data.status != null) {
                    const statusCell = document.querySelector(`[data-status-cell="${data.customer_id}"]`);
                    if (statusCell) {
                        statusCell.textContent = data.status;
                        if (data.status_tone_class) {
                            statusCell.className = `inline-flex px-2 py-0.5 rounded text-[11px] font-bold capitalize ${data.status_tone_class}`;
                        }
                    }
                    const statusList = document.querySelector(`[data-status-list="${data.customer_id}"]`);
                    if (statusList) {
                        statusList.textContent = data.status;
                        if (data.status_tone_class) {
                            statusList.className = `text-[10px] font-semibold px-1.5 py-0.5 rounded capitalize shrink-0 ${data.status_tone_class}`;
                        }
                    }
                }
            }
        }
    }
}

function pad2(n) { return String(n).padStart(2, '0'); }
function nextDayYmd() {
    const d = new Date();
    d.setDate(d.getDate() + 1);
    return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
}
function currentHm() {
    const d = new Date();
    return pad2(d.getHours()) + ':' + pad2(d.getMinutes());
}
function toggleFollowUpFields(id, on) {
    const wrap = document.getElementById('followUpFields-' + id);
    const dateEl = document.getElementById('followUpDate-' + id);
    if (!wrap) return;
    wrap.style.display = on ? 'flex' : 'none';
    if (on && dateEl && !dateEl.value) dateEl.value = nextDayYmd();
}
function toggleFollowUpTime(id, on) {
    const timeEl = document.getElementById('followUpTime-' + id);
    if (!timeEl) return;
    timeEl.style.display = on ? 'inline-block' : 'none';
    if (on && !timeEl.value) timeEl.value = currentHm();
}
function applyNoteTemplate(form, title, body) {
    if (!form) return;
    const bodyEl = form.querySelector('[name="body"]');
    const titleEl = form.querySelector('[name="title"]');
    if (bodyEl) {
        bodyEl.value = body || '';
        bodyEl.focus();
    }
    if (titleEl && title) titleEl.value = title;
}

function openCrmChannel(url, channel) {
    if (!url) return;
    const names = { whatsapp: 'crmWhatsApp', email: 'crmEmail', sms: 'crmSms', call: 'crmCall' };
    const win = window.open(url, names[channel] || 'crmCompose');
    if (win) {
        try { win.focus(); } catch (e) {}
    }
}

async function assignToOperations(customerId) {
    if (!confirm('Assign this customer to Operation Team?')) return;
    try {
        const res = await fetch(`{{ url('/customers') }}/${customerId}/assign-operations`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
            }
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            alert(data.message || 'Could not assign to Operation Team');
            return;
        }
        const root = Alpine.$data(document.body);
        if (root && data.html) root.html = data.html;
        if (data.customer_id != null) {
            const cell = document.querySelector(`[data-fu-cell="${data.customer_id}"]`);
            if (cell && data.follow_up_html != null) cell.innerHTML = data.follow_up_html;
            const listBadge = document.querySelector(`[data-fu-list="${data.customer_id}"]`);
            if (listBadge) {
                listBadge.textContent = '';
                listBadge.classList.add('hidden');
            }
        }
    } catch (e) {
        alert('Could not assign to Operation Team');
    }
}

async function submitActivationItemRequest(form, customerId) {
    const itemIds = Array.from(form.querySelectorAll('select[name="inventory_item_ids[]"]'))
        .map(el => el.value)
        .filter(Boolean)
        .map(Number);
    const notes = form.querySelector('[name="notes"]')?.value || '';
    const activationRequestId = form.querySelector('[name="activation_request_id"]')?.value || null;
    if (!itemIds.length) {
        alert('Select at least one device from a category.');
        return;
    }
    try {
        const payload = { inventory_item_ids: itemIds, notes };
        if (activationRequestId) payload.activation_request_id = Number(activationRequestId);

        const res = await fetch(`{{ url('/customers') }}/${customerId}/activation-requests`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
            },
            body: JSON.stringify(payload)
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            alert(data.message || Object.values(data.errors || {})?.[0]?.[0] || 'Could not submit request');
            return;
        }
        const root = Alpine.$data(document.body);
        if (root?.selectCustomer) {
            await root.selectCustomer(customerId);
        } else {
            alert(data.message || 'Activation request submitted.');
        }
    } catch (e) {
        alert('Could not submit activation request');
    }
}

async function startActivationRequest(customerId) {
    try {
        const res = await fetch(`{{ url('/customers') }}/${customerId}/activation-requests/start`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
            }
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            alert(data.message || 'Could not start activation request');
            return;
        }
        const root = Alpine.$data(document.body);
        if (root?.selectCustomer) await root.selectCustomer(customerId);
    } catch (e) {
        alert('Could not start activation request');
    }
}

function toggleActivationForm(requestId) {
    const form = document.getElementById('act-form-' + requestId);
    if (form) form.classList.toggle('hidden');
}


async function runAutoAction(customerId, channel) {
    if (channel !== 'call') {
        const root = Alpine.$data(document.body);
        if (root?.openCompose) return root.openCompose(customerId, channel);
    }
    try {
        const res = await fetch(`{{ url('/customers') }}/${customerId}/auto/${channel}`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
            }
        });
        const data = await res.json();
        if (!res.ok) {
            alert(data.message || 'Action failed');
            return;
        }
        const root = Alpine.$data(document.body);
        if (root && data.html) root.html = data.html;
        if (data.open_url) openCrmChannel(data.open_url, channel);
    } catch (e) {
        alert('Could not run automation');
    }
}

async function sendSingleCustomerWhatsapp(customerId) {
    if (!customerId) return;
    try {
        const res = await fetch(`{{ route('customers.bulk-whatsapp') }}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
            },
            body: JSON.stringify({ ids: [customerId] })
        });
        const data = await res.json();
        if (!res.ok) {
            if (data.settings_url && confirm((data.message || 'WhatsApp number not set.') + '\n\nDo you want to open Settings now?')) {
                window.location.href = data.settings_url;
            } else {
                alert(data.message || 'Could not prepare WhatsApp message');
            }
            return;
        }
        if (data.url) {
            window.open(data.url, '_blank');
        }
    } catch (e) {
        alert('Failed to send WhatsApp message.');
    }
}

async function sendSingleCustomerEmail(customerId) {
    if (!customerId) return;
    try {
        const root = Alpine.$data(document.body);
        if (root && typeof root.openEmailModal === 'function') {
            await root.openEmailModal([customerId]);
            return;
        }
    } catch (e) {
        console.warn('Could not open email modal via Alpine:', e);
    }
}

async function copyHtmlTableToClipboard(htmlString, plainString) {
    if (!htmlString) return false;
    let copied = false;

    // 1. Try modern navigator.clipboard with ClipboardItem
    if (navigator.clipboard && window.ClipboardItem) {
        try {
            const blobHtml = new Blob([htmlString], { type: 'text/html' });
            const blobText = new Blob([plainString || ''], { type: 'text/plain' });
            await navigator.clipboard.write([
                new ClipboardItem({
                    'text/html': blobHtml,
                    'text/plain': blobText
                })
            ]);
            copied = true;
        } catch (e) {
            console.warn('navigator.clipboard.write failed, trying fallback:', e);
        }
    }

    // 2. Fallback using invisible DOM element + execCommand
    if (!copied) {
        try {
            const container = document.createElement('div');
            container.innerHTML = htmlString;
            container.style.position = 'fixed';
            container.style.pointerEvents = 'none';
            container.style.opacity = '0';
            container.style.left = '-9999px';
            document.body.appendChild(container);

            const range = document.createRange();
            range.selectNode(container);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);

            copied = document.execCommand('copy');
            selection.removeAllRanges();
            document.body.removeChild(container);
        } catch (e2) {
            console.warn('execCommand copy fallback failed:', e2);
        }
    }

    return copied;
}
</script>
@stack('scripts')
</body>
</html>
