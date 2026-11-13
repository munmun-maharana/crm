<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
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
        .nav-item{border-radius:8px;transition:all .15s ease}
        .nav-item.active{background:#0f6cbd;color:#ffffff;font-weight:700;border-color:#0f6cbd;box-shadow:0 2px 6px rgba(15,108,189,.25)}
        .list-item{border-left:3px solid transparent; transition: background .18s ease, border-color .18s ease}
        .list-item:hover{background:rgba(255,255,255,0.75)}
        .list-item.active{background:#dbeafe;border-left-color:#0f6cbd}
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
        .crm-toolbar-group{display:flex;align-items:center;flex:1;min-width:0;max-width:760px;height:38px;background:#ffe4e6;border:1.5px solid #ef4444;border-radius:10px;overflow:hidden}
        .crm-toolbar-group:focus-within{background:#fff1f2;border-color:#dc2626;box-shadow:0 0 0 3px rgba(239,68,68,.18)}
        .crm-toolbar-search{flex:1;min-width:140px;display:flex;align-items:center;gap:8px;padding:0 12px;height:100%}
        .crm-toolbar-search input{flex:1;width:100%;background:transparent;border:0;outline:none;font-size:13px;color:#881337;font-weight:500}
        .crm-toolbar-search input::placeholder{color:#e11d48;opacity:.7}
        .crm-toolbar-split{width:1px;height:18px;background:#fca5a5;flex-shrink:0}
        .crm-toolbar-select{border:0;background:transparent url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23be123c' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E") no-repeat right 10px center;font-size:12.5px;font-weight:600;color:#881337;padding:0 28px 0 12px;height:100%;outline:none;appearance:none;-webkit-appearance:none;cursor:pointer;max-width:150px}

        /* ---------- Mobile / tablet responsiveness ---------- */
        .crm-scroll{-webkit-overflow-scrolling:touch}
        table th{white-space:nowrap}
        @media (max-width:1023px){
            /* any card that clips a table should scroll it horizontally instead */
            .crm-main .overflow-hidden:has(> table){overflow-x:auto}
            .crm-main table{min-width:560px}
            .crm-main .no-min-table table, .crm-main table.no-min{min-width:0}
        }
        @media (max-width:639px){
            .crm-tabs{display:flex!important;flex-wrap:nowrap!important;overflow-x:auto;max-width:100%;scrollbar-width:none}
            .crm-tabs::-webkit-scrollbar{display:none}
            .crm-tabs > *{flex-shrink:0;white-space:nowrap}
            .crm-toolbar-group{max-width:none}
            .crm-toolbar-search{min-width:0;padding:0 8px}
            .crm-toolbar-select{max-width:105px;padding:0 22px 0 8px;font-size:12px;background-position:right 6px center}
        }
    </style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    @stack('head')
</head>
<body class="font-sans text-ink antialiased bg-white" x-data="crmShell()" x-cloak>
<div class="h-full flex" @keydown.escape.window="navOpen = false">
    {{-- Mobile nav backdrop --}}
    <div class="fixed inset-0 z-[55] bg-ink/30 lg:hidden" x-show="navOpen" x-cloak
         x-transition.opacity.duration.200ms @click="navOpen = false"></div>

    {{-- Left nav — Outlook style (off-canvas on mobile) --}}
    <aside class="fixed inset-y-0 left-0 z-[56] w-[250px] lg:w-[200px] lg:static lg:z-auto lg:translate-x-0 shrink-0 border-r border-line bg-indigo-50/60 flex flex-col h-full transition-transform duration-300 ease-out shadow-xl lg:shadow-none"
           :class="navOpen ? 'translate-x-0' : '-translate-x-full'">
        <div class="h-14 shrink-0 px-4 border-b border-line bg-sky-100/75 flex items-center justify-between">
            <div class="text-[18px] font-bold tracking-tight text-ink">Pulse<span class="text-brand">CRM</span></div>
            <button type="button" class="lg:hidden w-8 h-8 rounded hover:bg-white/60 text-slate-500" @click="navOpen = false" aria-label="Close menu">✕</button>
        </div>
        <nav class="p-2 flex-1 overflow-y-auto crm-scroll text-[13px] space-y-1">
            @php
                $navUser = auth()->user();
                $links = [];
                if ($navUser->canAccessDashboard()) {
                    $links[] = ['dashboard', 'Dashboard'];
                }
                if ($navUser->canAccessCrm()) {
                    $links[] = ['customers.index', 'CRM'];
                }
                if ($navUser->canAccessCallCrm()) {
                    $links[] = ['call-crm.index', 'CALL CRM'];
                }
                if ($navUser->canAccessOperations()) {
                    $links[] = ['operations.index', 'TEAM OPS'];
                }
                if ($navUser->canAccessPincodes()) {
                    $links[] = ['pincodes.index', 'Pincode Manager'];
                }
                if ($navUser->isAdmin()) {
                    $links = array_merge($links, [
                        ['users.index', 'Users'],
                    ]);
                }
            @endphp
            <div class="px-2 pt-1 pb-1.5 text-[11px] font-semibold text-indigo-500">Favorites</div>
            @foreach($links as [$route, $label])
                <a href="{{ route($route) }}"
                   class="nav-item flex items-center gap-2 px-3 py-1.5 border {{ request()->routeIs($route) || request()->routeIs(str_replace('.index','.*',$route)) || ($route === 'settings.index' && request()->routeIs('fields.*', 'note-templates.*', 'operation-settings.*', 'inventory-types.*')) ? 'active' : 'bg-white/85 hover:bg-white text-slate-700 border-indigo-200/70' }}">
                    <span class="uppercase tracking-wide">{{ $label }}</span>
                </a>
            @endforeach
        </nav>
        <div class="p-3 border-t border-line bg-teal-100/60 text-[12px]">
            <div class="flex items-center justify-between gap-2">
                <div class="font-semibold truncate min-w-0 text-teal-950">{{ auth()->user()->name }}</div>
                <div class="flex items-center gap-1 shrink-0">
                    <button type="button" @click="changePasswordOpen = true" title="Change Password"
                       class="w-7 h-7 inline-flex items-center justify-center rounded-lg border border-teal-200 bg-white/80 text-slate-600 hover:bg-brand-soft hover:text-brand transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                        </svg>
                    </button>
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('settings.index') }}" title="Setting"
                           class="w-7 h-7 shrink-0 inline-flex items-center justify-center rounded-lg border border-teal-200 bg-white/80 text-slate-600 hover:bg-brand-soft hover:text-brand transition {{ request()->routeIs('settings.*', 'fields.*', 'note-templates.*', 'operation-settings.*') ? 'bg-brand-soft text-brand border-brand/30' : '' }}">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </a>
                    @endif
                </div>
            </div>
            <div class="flex items-center justify-between gap-2 mt-1">
                <span class="text-teal-800/80 truncate">{{ auth()->user()->username }} · {{ auth()->user()->roleLabel() }}</span>
                <div class="flex items-center gap-1.5 shrink-0">
                    <form method="POST" action="{{ route('logout') }}" onsubmit="return confirm('Logout karna hai?')">@csrf
                        <button class="text-brand font-semibold hover:underline">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </aside>

    <div class="crm-main flex-1 min-w-0 h-full flex flex-col">
        {{-- Mobile top bar --}}
        <div class="lg:hidden shrink-0 h-12 border-b border-line bg-sky-100/75 px-2 flex items-center gap-2">
            <button type="button" @click="navOpen = true" aria-label="Open menu"
                    class="w-9 h-9 inline-flex items-center justify-center rounded-lg text-slate-600 hover:bg-white/60">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <div class="text-[16px] font-bold tracking-tight text-ink">Pulse<span class="text-brand">CRM</span></div>
            <div class="ml-auto text-[12px] text-slate-600 truncate max-w-[45%]">{{ auth()->user()->name }}</div>
        </div>
        @hasSection('split')
            @yield('split')
        @else
            <header class="shrink-0 min-h-14 lg:h-14 border-b border-line bg-gradient-to-r from-sky-100/80 via-indigo-50/70 to-violet-100/70 px-3 sm:px-5 py-1.5 flex flex-wrap items-center justify-between gap-2 sm:gap-3">
                <div class="min-w-0 leading-tight">
                    <h1 class="text-[15px] sm:text-[17px] font-bold truncate text-indigo-950">@yield('heading', 'Dashboard')</h1>
                    <p class="text-[11px] text-slate-600 truncate">@yield('subheading')</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">@yield('actions')</div>
            </header>
            <div class="flex-1 overflow-y-auto crm-scroll bg-slate-50/60 p-3 sm:p-5">
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
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <label class="block text-[11px] font-bold text-slate-500">Message *</label>
                        <div class="flex flex-wrap items-center gap-1">
                            <button type="button"
                                    @click="applyComposeTemplate('booking_confirmed')"
                                    class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 px-2.5 py-0.5 text-[10.5px] font-bold transition">
                                🎉 Booking Confirmed!
                            </button>
                            <button type="button"
                                    @click="applyComposeTemplate('follow_up')"
                                    class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 hover:bg-amber-100 text-amber-800 px-2 py-0.5 text-[10.5px] font-semibold transition">
                                ⏰ Follow-up
                            </button>
                        </div>
                    </div>
                    <textarea x-model="compose.message" rows="12" required placeholder="Type your message…"
                              class="w-full rounded-lg border border-line px-3 py-2 text-[13px] leading-relaxed focus:outline-none focus:ring-2 focus:ring-brand/20 resize-y"></textarea>
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

{{-- Change Password Modal --}}
<div class="fixed inset-0 z-[80] flex items-center justify-center p-4"
     x-show="changePasswordOpen"
     x-cloak>
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
         x-show="changePasswordOpen"
         x-transition.opacity.duration.200ms
         @click="changePasswordOpen = false"></div>

    <div class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden border border-line z-10"
         x-show="changePasswordOpen"
         x-transition:enter="transition ease-out duration-250"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         @click.stop
         x-data="{ showCurrent: false, showNew: false, showConfirm: false }">

        <div class="px-6 py-4 border-b border-line flex items-center justify-between bg-slate-50">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-brand-soft text-brand flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-ink">Change Password</h3>
                    <p class="text-[11px] text-slate-500">Update password for {{ auth()->user()->username }}</p>
                </div>
            </div>
            <button type="button" @click="changePasswordOpen = false" class="w-8 h-8 rounded-lg hover:bg-slate-200/60 text-slate-400 hover:text-slate-600 flex items-center justify-center text-sm font-bold">✕</button>
        </div>

        <form method="POST" action="{{ route('password.update') }}" class="p-6 space-y-4">
            @csrf
            @method('PUT')

            @if($errors->updatePassword->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-[12px] text-rose-700 space-y-1">
                    @foreach($errors->updatePassword->all() as $err)
                        <div class="flex items-center gap-1.5 font-medium">
                            <span>⚠</span>
                            <span>{{ $err }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Current Password</label>
                <div class="relative">
                    <input :type="showCurrent ? 'text' : 'password'"
                           name="current_password"
                           required
                           placeholder="Enter your current password"
                           class="w-full rounded-xl border border-line bg-white px-3.5 py-2.5 text-[13px] text-ink focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand pr-10">
                    <button type="button"
                            @click="showCurrent = !showCurrent"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-1">
                        <svg class="w-4 h-4" x-show="!showCurrent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <svg class="w-4 h-4" x-show="showCurrent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                    </button>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">New Password</label>
                <div class="relative">
                    <input :type="showNew ? 'text' : 'password'"
                           name="password"
                           required
                           minlength="6"
                           placeholder="At least 6 characters"
                           class="w-full rounded-xl border border-line bg-white px-3.5 py-2.5 text-[13px] text-ink focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand pr-10">
                    <button type="button"
                            @click="showNew = !showNew"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-1">
                        <svg class="w-4 h-4" x-show="!showNew" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <svg class="w-4 h-4" x-show="showCurrent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                    </button>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Confirm New Password</label>
                <div class="relative">
                    <input :type="showConfirm ? 'text' : 'password'"
                           name="password_confirmation"
                           required
                           minlength="6"
                           placeholder="Re-enter new password"
                           class="w-full rounded-xl border border-line bg-white px-3.5 py-2.5 text-[13px] text-ink focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand pr-10">
                    <button type="button"
                            @click="showConfirm = !showConfirm"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-1">
                        <svg class="w-4 h-4" x-show="!showConfirm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <svg class="w-4 h-4" x-show="showConfirm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                    </button>
                </div>
            </div>

            <div class="pt-2 flex items-center justify-end gap-2.5 border-t border-line">
                <button type="button"
                        @click="changePasswordOpen = false"
                        class="px-4 py-2 rounded-xl border border-line text-slate-600 hover:bg-slate-100 text-[13px] font-semibold transition">
                    Cancel
                </button>
                <button type="submit"
                        class="px-5 py-2 rounded-xl bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition shadow-sm shadow-brand/20">
                    Update Password
                </button>
            </div>
        </form>
    </div>
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
        <div class="px-4 sm:px-6 py-4 border-b border-line flex items-center justify-between bg-slate-50 shrink-0">
            <div class="flex items-center gap-3 min-w-0">
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
        <div class="px-4 sm:px-6 py-3.5 bg-slate-50/70 border-b border-line shrink-0 space-y-2">
            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600">
                Recipient Email (Enter Manually)
            </label>
            <div class="flex flex-wrap sm:flex-nowrap items-center gap-2">
                <div class="relative flex-1 min-w-[200px]">
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
        <div class="flex-1 overflow-y-auto crm-scroll p-3 sm:p-6 bg-slate-100/60 min-h-[220px]">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2.5">
                <div class="flex flex-wrap items-center gap-2">
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
        <div class="px-4 sm:px-6 py-3 bg-amber-50/90 border-t border-amber-200/80 flex items-center justify-between gap-3 text-[12px] text-amber-900 shrink-0">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="w-6 h-6 rounded-full bg-amber-200/80 text-amber-800 font-bold flex items-center justify-center text-[12px] shrink-0">ℹ</span>
                <span class="sm:truncate">
                    In Gmail compose: press <kbd class="px-1.5 py-0.5 rounded bg-white border border-amber-300 font-mono text-[11px] font-bold shadow-xs text-amber-900">Ctrl + V</kbd> to paste this exact colored Excel table!
                </span>
            </div>
            <span x-show="emailModalCopied" class="text-emerald-700 font-bold text-[12px] shrink-0 animate-pulse">
                ✓ Table copied to clipboard!
            </span>
        </div>

        {{-- Footer --}}
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-t border-line bg-white flex flex-wrap items-center justify-between gap-2 sm:gap-3 shrink-0">
            <button type="button" @click="emailModalOpen = false"
                    class="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-100 text-[13px] font-semibold transition">
                Close
            </button>
            <div class="flex flex-wrap items-center gap-2 sm:gap-2.5">
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

{{-- Two-Step Booking Slot Popup Modal --}}
<div class="fixed inset-0 z-[85] flex items-center justify-center p-4"
     x-show="bookingModalOpen"
     x-cloak>
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
         x-show="bookingModalOpen"
         x-transition.opacity.duration.200ms
         @click="if (!bookingModalSubmitting) bookingModalOpen = false"></div>

    <div class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden border border-line z-10"
         x-show="bookingModalOpen"
         x-transition:enter="transition ease-out duration-250"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         @click.stop>

        {{-- Header --}}
        <div class="px-5 py-4 border-b border-line flex items-center justify-between bg-slate-50">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-brand-soft text-brand flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-ink" x-text="bookingModalStep === 1 ? 'Book Time Slot' : 'Confirm Slot Booking'"></h3>
                    <p class="text-[11px] text-slate-500" x-text="bookingModalStep === 1 ? 'Enter customer name to reserve this slot' : 'Please review and confirm booking details'"></p>
                </div>
            </div>
            <button type="button" @click="bookingModalOpen = false" :disabled="bookingModalSubmitting" class="w-8 h-8 rounded-lg hover:bg-slate-200/60 text-slate-400 hover:text-slate-600 flex items-center justify-center text-sm font-bold">✕</button>
        </div>

        {{-- Error Alert --}}
        <div x-show="bookingModalError" x-cloak class="mx-5 mt-4 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[12px] text-rose-700 font-medium flex items-center gap-2">
            <span>⚠</span>
            <span x-text="bookingModalError"></span>
        </div>

        {{-- Step 1: Enter Customer Name --}}
        <div x-show="bookingModalStep === 1" class="p-5 space-y-4">
            <div class="rounded-xl bg-mist/70 border border-line p-3.5 flex items-center justify-between gap-3">
                <div>
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Selected Slot</div>
                    <div class="text-[13px] font-bold text-ink mt-0.5" x-text="bookingModalSlot?.label || ''"></div>
                </div>
                <div class="text-right">
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Date</div>
                    <div class="text-[13px] font-bold text-brand mt-0.5" x-text="bookingSlotsDateFormatted"></div>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Customer Name <span class="text-rose-500">*</span></label>
                <input type="text"
                       id="bookingCustomerNameInput"
                       x-model="bookingModalCustomerName"
                       @keydown.enter.prevent="proceedToBookingConfirm()"
                       list="crmCustomerNamesDatalist"
                       placeholder="Enter customer name…"
                       class="w-full rounded-xl border border-line bg-white px-3.5 py-2.5 text-[13px] text-ink focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand">
            </div>

            <div class="pt-2 flex items-center justify-end gap-2.5 border-t border-line">
                <button type="button"
                        @click="bookingModalOpen = false"
                        class="px-4 py-2 rounded-xl border border-line text-slate-600 hover:bg-slate-100 text-[13px] font-semibold transition">
                    Cancel
                </button>
                <button type="button"
                        @click="proceedToBookingConfirm()"
                        class="px-5 py-2 rounded-xl bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition shadow-sm shadow-brand/20">
                    Continue →
                </button>
            </div>
        </div>

        {{-- Step 2: Confirmation Popup --}}
        <div x-show="bookingModalStep === 2" x-cloak class="p-5 space-y-4">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 space-y-3">
                <div class="flex items-center justify-between border-b border-emerald-200/60 pb-2.5">
                    <span class="text-[12px] font-semibold text-slate-500">Customer Name</span>
                    <span class="text-[14px] font-bold text-ink" x-text="bookingModalCustomerName"></span>
                </div>
                <div class="flex items-center justify-between border-b border-emerald-200/60 pb-2.5">
                    <span class="text-[12px] font-semibold text-slate-500">Selected Date</span>
                    <span class="text-[13px] font-bold text-slate-800" x-text="bookingSlotsDateFormatted + ' (' + bookingSlotsDate + ')'"></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-[12px] font-semibold text-slate-500">Booking Time</span>
                    <span class="text-[13px] font-bold text-emerald-700 bg-emerald-100 px-2.5 py-0.5 rounded-md" x-text="bookingModalSlot?.label || ''"></span>
                </div>
            </div>

            <p class="text-[12px] text-slate-500 text-center">
                Once confirmed, this slot will be assigned to <strong class="text-ink" x-text="bookingModalCustomerName"></strong> and marked as <strong class="text-rose-600">Booked</strong>.
            </p>

            <div class="pt-2 flex items-center justify-between gap-2.5 border-t border-line">
                <button type="button"
                        @click="bookingModalStep = 1; bookingModalError = ''"
                        :disabled="bookingModalSubmitting"
                        class="px-4 py-2 rounded-xl border border-line text-slate-600 hover:bg-slate-100 text-[13px] font-semibold transition">
                    ← Back
                </button>
                <button type="button"
                        @click="confirmSlotBooking()"
                        :disabled="bookingModalSubmitting"
                        class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-[13px] font-bold transition shadow-sm shadow-emerald-600/20 disabled:opacity-60">
                    <span x-text="bookingModalSubmitting ? 'Booking…' : '✓ Confirm Booking'"></span>
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Confirm Post Update Modal --}}
<div x-show="postUpdateModalOpen" x-cloak
     @keydown.escape.window="if (postUpdateModalOpen && !postUpdateSubmitting) postUpdateModalOpen = false"
     class="fixed inset-0 z-[75] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
    <div @click.outside="if (!postUpdateSubmitting) postUpdateModalOpen = false"
         class="w-full max-w-md rounded-2xl bg-white shadow-2xl border border-line overflow-hidden">
        <div class="px-5 py-3.5 bg-gradient-to-r from-brand to-brand-dark text-white flex items-center justify-between">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="w-8 h-8 rounded-xl bg-white/15 inline-flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <div class="min-w-0">
                    <div class="text-[10.5px] font-bold uppercase tracking-wider text-blue-200">Confirm Update</div>
                    <div class="text-[14px] font-bold leading-tight truncate" x-text="postUpdateCustomerName || 'Customer Update'"></div>
                </div>
            </div>
            <button type="button" @click="if (!postUpdateSubmitting) postUpdateModalOpen = false"
                    class="w-7 h-7 rounded-lg bg-white/10 hover:bg-white/20 inline-flex items-center justify-center text-white text-sm">✕</button>
        </div>

        <div class="p-4 max-h-[65vh] overflow-y-auto space-y-2.5">
            <div class="text-[11.5px] text-slate-500 font-medium">Please review all changes before posting this update:</div>

            <div class="rounded-xl border border-line bg-mist/30 divide-y divide-line overflow-hidden">
                <template x-for="(item, idx) in postUpdateChanges" :key="idx">
                    <div class="px-3.5 py-2.5 flex items-start justify-between gap-3 text-[12px]">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <span class="text-[10.5px] font-bold uppercase tracking-wider text-slate-400" x-text="item.label"></span>
                                <span x-show="item.changed"
                                      class="inline-flex items-center rounded-full bg-amber-100 text-amber-800 px-1.5 py-0.2 text-[9.5px] font-bold">Changed</span>
                            </div>
                            <div class="mt-0.5 flex flex-wrap items-center gap-1.5 break-words">
                                <template x-if="item.from !== null && item.from !== undefined">
                                    <span class="inline-flex items-center gap-1.5 flex-wrap">
                                        <span class="line-through text-slate-400 text-[11.5px]" x-text="item.from || 'Empty'"></span>
                                        <span class="text-slate-400 font-bold">→</span>
                                        <span class="font-bold text-emerald-700 bg-emerald-50 border border-emerald-200/80 rounded px-1.5 py-0.5 text-[11.5px]" x-text="item.to || 'Empty'"></span>
                                    </span>
                                </template>
                                <template x-if="item.from === null || item.from === undefined">
                                    <span class="font-semibold text-ink whitespace-pre-line text-[12px]" x-text="item.to"></span>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div class="px-5 py-3 bg-mist/40 border-t border-line flex items-center justify-end gap-2">
            <button type="button"
                    @click="postUpdateModalOpen = false"
                    :disabled="postUpdateSubmitting"
                    class="px-4 py-2 rounded-xl border border-line bg-white hover:bg-mist text-slate-700 text-[12.5px] font-semibold transition disabled:opacity-50">
                Cancel
            </button>
            <button type="button"
                    @click="confirmPostUpdate()"
                    :disabled="postUpdateSubmitting"
                    class="inline-flex items-center gap-1.5 px-5 py-2 rounded-xl bg-brand hover:bg-brand-dark text-white text-[12.5px] font-bold transition shadow-sm shadow-brand/20 disabled:opacity-60">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                <span x-text="postUpdateSubmitting ? 'Posting…' : 'Confirm & Post Update'"></span>
            </button>
        </div>
    </div>
</div>

<script>
function crmShell() {
    return {
        navOpen: false,
        changePasswordOpen: "{{ $errors->updatePassword->any() ? '1' : '0' }}" === "1",
        selectedId: null,
        title: '',
        html: '',
        loading: false,
        deskKey: 0,
        formOpen: {{ $errors->any() && ! $errors->updatePassword->any() ? 'true' : 'false' }},
        formMode: 'create',
        form: @json(old() ? array_merge(['id' => null], old()) : ['id' => null]),
        composeOpen: false,
        composeSending: false,
        compose: { channel: 'whatsapp', customerId: null, customer: null, name: '', phone: '', email: '', message: '', subject: '', link: '' },
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
        excelOpen: false,
        bookingSlotsOpen: false,
        bookingSlotsDate: '{{ now()->toDateString() }}',
        bookingSlotsDateFormatted: '{{ now()->format("d M Y") }}',
        bookingSlotsLoading: false,
        bookingSlotsList: [],
        bookingSlotsCounts: { total: 0, available: 0, booked: 0, cancelled: 0 },
        bookingModalOpen: false,
        bookingModalStep: 1,
        bookingModalSlot: null,
        bookingModalCustomerName: '',
        bookingModalSubmitting: false,
        bookingModalError: '',
        postUpdateModalOpen: false,
        postUpdateSubmitting: false,
        postUpdateFormEl: null,
        postUpdateCustomerName: '',
        postUpdateChanges: [],
        async toggleBookingSlots() {
            this.bookingSlotsOpen = !this.bookingSlotsOpen;
            if (this.bookingSlotsOpen) {
                await this.loadBookingSlots(this.bookingSlotsDate);
            }
        },
        async loadBookingSlots(date) {
            if (date) this.bookingSlotsDate = date;
            this.bookingSlotsLoading = true;
            try {
                const res = await fetch(`{{ route('booking-slots.index') }}?date=${encodeURIComponent(this.bookingSlotsDate)}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                if (res.ok) {
                    this.bookingSlotsDate = data.date || this.bookingSlotsDate;
                    this.bookingSlotsDateFormatted = data.formatted_date || this.bookingSlotsDate;
                    this.bookingSlotsList = data.slots || [];
                    this.bookingSlotsCounts = data.counts || { total: 0, available: 0, booked: 0, cancelled: 0 };
                }
            } catch (e) {
                console.error('Failed to load booking slots', e);
            } finally {
                this.bookingSlotsLoading = false;
            }
        },
        shiftBookingSlotsDate(days) {
            const parts = (this.bookingSlotsDate || '').split('-').map(Number);
            const d = parts.length === 3 ? new Date(parts[0], parts[1] - 1, parts[2]) : new Date();
            d.setDate(d.getDate() + days);
            const ymd = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            this.loadBookingSlots(ymd);
        },
        scrollBookingSlider(dir) {
            const el = document.getElementById('bookingSlotsSlider');
            if (el) {
                el.scrollBy({ left: dir * 280, behavior: 'smooth' });
            }
        },
        openBookSlotModal(slot) {
            if (!slot || slot.status !== 'available') return;
            this.bookingModalSlot = slot;
            if (slot.slot_date) {
                this.bookingSlotsDate = slot.slot_date;
            }
            this.bookingModalCustomerName = this.title || '';
            this.bookingModalStep = 1;
            this.bookingModalError = '';
            this.bookingModalOpen = true;
            this.$nextTick(() => {
                const input = document.getElementById('bookingCustomerNameInput');
                if (input) input.focus();
            });
        },
        proceedToBookingConfirm() {
            const name = (this.bookingModalCustomerName || '').trim();
            if (!name) {
                this.bookingModalError = 'Please enter the customer name.';
                return;
            }
            this.bookingModalCustomerName = name;
            this.bookingModalError = '';
            this.bookingModalStep = 2;
        },
        async confirmSlotBooking() {
            if (!this.bookingModalSlot || this.bookingModalSubmitting) return;
            const name = (this.bookingModalCustomerName || '').trim();
            if (!name) {
                this.bookingModalStep = 1;
                this.bookingModalError = 'Please enter the customer name.';
                return;
            }
            this.bookingModalSubmitting = true;
            this.bookingModalError = '';
            try {
                const res = await fetch(`{{ url('/booking-slots') }}/${this.bookingModalSlot.id}/book`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    },
                    body: JSON.stringify({
                        customer_name: name,
                        customer_id: this.selectedId || null
                    })
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    this.bookingModalError = data.message || 'Could not book this slot. It may already be booked.';
                    await this.loadBookingSlots(this.bookingSlotsDate);
                    return;
                }
                this.bookingModalOpen = false;
                await this.loadBookingSlots(this.bookingSlotsDate);
                window.dispatchEvent(new CustomEvent('booking-slots-updated', { detail: { date: this.bookingSlotsDate } }));
                if (data.customer_id != null) {
                    const fuCell = document.querySelector(`[data-fu-cell="${data.customer_id}"]`);
                    if (fuCell && data.follow_up_html != null) {
                        fuCell.innerHTML = data.follow_up_html;
                    }
                    const fuList = document.querySelector(`[data-fu-list="${data.customer_id}"]`);
                    if (fuList && data.list_date_html != null) {
                        fuList.innerHTML = data.list_date_html;
                    }
                    if (data.customer_status != null) {
                        const statusCell = document.querySelector(`[data-status-cell="${data.customer_id}"]`);
                        if (statusCell) {
                            statusCell.textContent = data.customer_status;
                            if (data.status_tone_class) {
                                statusCell.className = `inline-flex px-2 py-0.5 rounded text-[11px] font-bold capitalize ${data.status_tone_class}`;
                            }
                        }
                        const statusList = document.querySelector(`[data-status-list="${data.customer_id}"]`);
                        if (statusList) {
                            statusList.textContent = data.customer_status;
                            if (data.status_tone_class) {
                                statusList.className = `text-[10px] font-semibold px-1.5 py-0.5 rounded capitalize shrink-0 ${data.status_tone_class}`;
                            }
                        }
                    }
                    if (Number(this.selectedId) === Number(data.customer_id)) {
                        await this.selectCustomer(data.customer_id);
                    }
                }
            } catch (e) {
                this.bookingModalError = 'Network error while booking slot.';
            } finally {
                this.bookingModalSubmitting = false;
            }
        },
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
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    const msg = res.status === 403
                        ? 'This customer is not mapped to you.'
                        : (data.message || `Could not load customer details (HTTP ${res.status}).`);
                    this.html = `<div class="p-8 text-center text-rose-600 text-sm">${msg}</div>`;
                    return;
                }
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
        todayYmd() {
            const d = new Date();
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        },
        shiftFormDate(key, deltaDays) {
            const raw = (this.form && this.form[key]) ? String(this.form[key]).trim() : '';
            let base = new Date();
            if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
                const parts = raw.split('-').map(Number);
                base = new Date(parts[0], parts[1] - 1, parts[2]);
            }
            base.setDate(base.getDate() + Number(deltaDays || 0));
            const nextStr = base.getFullYear() + '-' + String(base.getMonth() + 1).padStart(2, '0') + '-' + String(base.getDate()).padStart(2, '0');
            this.form[key] = nextStr;
        },
        shiftFormTime(key, deltaMinutes) {
            const raw = (this.form && this.form[key]) ? String(this.form[key]).trim() : '';
            const now = new Date();
            let hours = now.getHours();
            let mins = now.getMinutes();
            if (/^\d{1,2}:\d{2}/.test(raw)) {
                const parts = raw.split(':').map(Number);
                hours = parts[0];
                mins = parts[1];
            }
            let totalMins = (hours * 60 + mins + Number(deltaMinutes || 0)) % 1440;
            if (totalMins < 0) totalMins += 1440;
            const nextH = Math.floor(totalMins / 60);
            const nextM = totalMins % 60;
            this.form[key] = String(nextH).padStart(2, '0') + ':' + String(nextM).padStart(2, '0');
        },
        async onPincodeInput(pin) {
            const clean = String(pin || '').replace(/\D/g, '').trim();
            if (!clean) return;
            const dir = window.PINCODE_DIRECTORY || [];
            const match = dir.find(p => String(p.pincode).trim() === clean);
            if (match && match.area) {
                this.setAreaValue(match.area, match.city);
                return;
            }
            if (clean.length === 6) {
                try {
                    const res = await fetch(`https://api.postalpincode.in/pincode/${encodeURIComponent(clean)}`);
                    const data = await res.json();
                    const first = Array.isArray(data) ? data[0] : null;
                    const offices = first && Array.isArray(first.PostOffice) ? first.PostOffice : [];
                    if (offices.length > 0 && offices[0].Name) {
                        this.setAreaValue(offices[0].Name, offices[0].District);
                    }
                } catch (e) {}
            }
        },
        setAreaValue(areaName, cityName) {
            if (!areaName) return;
            this.form['area'] = areaName;
            this.form = { ...this.form, area: areaName };
            const areaInputs = document.querySelectorAll('input[name="area"], input[name="ariya"], #formFieldArea');
            areaInputs.forEach(el => {
                el.value = areaName;
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
            });
            if (cityName && (!this.form['city'] || this.form['city'] === '')) {
                this.form['city'] = cityName;
                this.form = { ...this.form, city: cityName };
                const cityInputs = document.querySelectorAll('input[name="city"]');
                cityInputs.forEach(el => {
                    el.value = cityName;
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                });
            }
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
                } else if (f.type === 'select' && f.auto_select && Array.isArray(f.options) && f.options.length > 0) {
                    defaultVal = f.options[0];
                } else if (f.type === 'date') {
                    defaultVal = this.todayYmd();
                }
                newForm[f.key] = defaultVal;
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
                                }
                            }
                        }
                    });
                }
            });
        },
        openEditForm(c) {
            this.formMode = 'edit';
            const newForm = { id: c.id };
            (window.CRM_FORM_FIELDS || []).forEach(f => {
                let val = c[f.key];
                if ((val === undefined || val === null) && c.meta) val = c.meta[f.key];
                if (val === undefined || val === null || String(val).trim() === '') {
                    if (f.type === 'select' && f.auto_select && Array.isArray(f.options) && f.options.length > 0) {
                        val = f.options[0];
                    } else if (f.type === 'date') {
                        val = this.todayYmd();
                    }
                }
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
        buildComposeTemplate(type, c) {
            if (!c) return '';
            const meta = (c.meta && typeof c.meta === 'object') ? c.meta : {};
            const pick = (keys) => {
                for (const k of keys) {
                    const val = meta[k] ?? c[k];
                    if (val !== undefined && val !== null && String(val).trim() !== '') {
                        return Array.isArray(val) ? val.join(', ') : String(val).trim();
                    }
                }
                return '';
            };

            const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            const formatLongDate = (raw) => {
                if (!raw) return '';
                const d = new Date(raw);
                if (isNaN(d.getTime())) return String(raw);
                return String(d.getDate()).padStart(2, '0') + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
            };

            const customerName = c.name || 'Customer';
            const bookingDate = formatLongDate(c.follow_up_at || meta.date || new Date());
            let slotTime = pick(['booking_slot_time', 'booking_time']);
            if (!slotTime && c.follow_up_has_time && c.follow_up_at) {
                const dt = new Date(c.follow_up_at);
                if (!isNaN(dt.getTime())) {
                    slotTime = dt.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
                }
            }
            slotTime = slotTime ? slotTime.replace(/\s+to\s+/i, ' – ') : 'As Scheduled';

            const carModel = pick(['car_model', 'model', 'vehicle_model', 'vehicle_type', 'vehicle']);
            const serviceDetails = pick(['service_details', 'service_type', 'service', 'package']);
            let vehicleBullets = [];
            if (carModel) {
                vehicleBullets = carModel.split(/[\n,]+/).map(s => s.trim()).filter(Boolean).map(s => `• ${s}`);
            }
            if (serviceDetails) {
                if (vehicleBullets.length === 1) {
                    vehicleBullets[0] = `${vehicleBullets[0]} (${serviceDetails})`;
                } else {
                    vehicleBullets.push(`• Service: ${serviceDetails}`);
                }
            }
            if (vehicleBullets.length === 0) {
                vehicleBullets.push('• Vehicle Cleaning');
            }

            const rawAmount = pick(['seal_price', 'sale_price', 'selling_price', 'price', 'mrp', 'amount']);
            let formattedAmount = '—';
            if (rawAmount !== '') {
                const num = Number(String(rawAmount).replace(/[^0-9.]/g, ''));
                formattedAmount = !isNaN(num) && num > 0
                    ? '₹' + num.toLocaleString('en-IN')
                    : (String(rawAmount).startsWith('₹') ? rawAmount : '₹' + rawAmount);
            }

            const rawPay = pick(['payment_status', 'payment']).toLowerCase();
            let paymentStatus = 'Pending';
            if (rawPay === 'yes' || rawPay === 'paid' || rawPay === 'done' || rawPay === 'completed') {
                paymentStatus = 'Paid';
            } else if (rawPay && rawPay !== 'no' && rawPay !== 'unpaid' && rawPay !== 'pending') {
                paymentStatus = pick(['payment_status', 'payment']);
            }

            const location = c.city || pick(['city', 'location', 'address']) || 'Bhubaneswar';

            if (type === 'follow_up') {
                return [
                    `Hello ${customerName},`,
                    ``,
                    `This is a quick follow-up regarding your vehicle cleaning enquiry.`,
                    `🚗 *Vehicle Details:*`,
                    ...vehicleBullets,
                    `💰 *Estimated Amount:* ${formattedAmount}`,
                    `📍 *Location:* ${location}`,
                    ``,
                    `Please let us know your preferred date and time slot so we can confirm your booking.`,
                    ``,
                    `*Thank you!*`
                ].join('\n');
            }

            return [
                `🎉 *Booking Confirmed!*`,
                ``,
                `Dear ${customerName},`,
                ``,
                `Your vehicle cleaning booking has been confirmed!`,
                ``,
                `📅 *Booking Date:* ${bookingDate}`,
                `⏰ *Time:* ${slotTime}`,
                `🚗 *Vehicle Details:*`,
                ...vehicleBullets,
                ``,
                `💰 *Total Amount:* ${formattedAmount}`,
                `💳 *Payment Status:* ${paymentStatus}`,
                ``,
                `📍 *Location:* ${location}`,
                ``,
                `Thank you for choosing our service! We look forward to serving you.`,
                ``,
                `*For any queries or changes, please contact us.*`
            ].join('\n');
        },
        applyComposeTemplate(type) {
            if (!this.compose.customer) return;
            this.compose.message = this.buildComposeTemplate(type, this.compose.customer);
            if (this.compose.channel === 'email') {
                this.compose.subject = type === 'booking_confirmed'
                    ? ('Booking Confirmed! - ' + (this.compose.name || ''))
                    : ('Follow-up - ' + (this.compose.name || ''));
            }
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
                const defaultMsg = this.buildComposeTemplate('booking_confirmed', c);
                this.compose = {
                    channel,
                    customerId: c.id,
                    customer: c,
                    name: c.name || '',
                    phone: c.phone || '',
                    email: c.email || '',
                    message: defaultMsg,
                    subject: channel === 'email' ? ('Booking Confirmed! - ' + (c.name || '')) : '',
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
        openPostUpdateConfirm(form) {
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

            const changes = [];
            const initStatus = (form.dataset.initialStatus || '').trim();
            const statusEl = form.querySelector('[name="status_change"]');
            const newStatus = (statusEl ? statusEl.value : '').trim();
            if (newStatus && newStatus.toLowerCase() !== initStatus.toLowerCase()) {
                changes.push({
                    label: 'Status',
                    from: initStatus ? initStatus : 'None',
                    to: newStatus,
                    changed: true
                });
            } else if (newStatus) {
                changes.push({
                    label: 'Status',
                    from: null,
                    to: newStatus,
                    changed: false
                });
            }

            const titleEl = form.querySelector('[name="title"]');
            const titleVal = (titleEl ? titleEl.value : '').trim();
            if (titleVal) {
                changes.push({
                    label: 'Internal Message / Title',
                    from: null,
                    to: titleVal,
                    changed: true
                });
            }

            const bodyEl = form.querySelector('[name="body"]');
            const bodyVal = (bodyEl ? bodyEl.value : '').trim();
            if (bodyVal) {
                changes.push({
                    label: 'Note / Feedback',
                    from: null,
                    to: bodyVal,
                    changed: true
                });
            }

            const imgInput = form.querySelector('input[type="file"][name="images[]"]');
            if (imgInput && imgInput.files && imgInput.files.length > 0) {
                const names = Array.from(imgInput.files).map(f => f.name).join(', ');
                changes.push({
                    label: 'Uploaded Images',
                    from: null,
                    to: `${imgInput.files.length} file(s): ${names}`,
                    changed: true
                });
            }

            const ufEls = form.querySelectorAll('[name^="update_fields["]');
            ufEls.forEach(el => {
                const label = el.dataset.fieldLabel || el.getAttribute('placeholder') || el.name;
                const initVal = (el.dataset.initialValue || '').trim();
                const curVal = (el.value || '').trim();
                if (curVal !== initVal) {
                    changes.push({
                        label: label,
                        from: initVal || 'Empty',
                        to: curVal || 'Empty',
                        changed: true
                    });
                } else if (curVal !== '') {
                    changes.push({
                        label: label,
                        from: null,
                        to: curVal,
                        changed: false
                    });
                }
            });

            if (followCheck) {
                const initFu = form.dataset.initialFu === '1';
                const initFuDate = (form.dataset.initialFuDate || '').trim();
                const initFuTime = (form.dataset.initialFuTime || '').trim();
                const initFuSummary = initFu
                    ? (initFuDate + (initFuTime ? ' ' + initFuTime : ''))
                    : 'Off';

                const curFu = !!followCheck.checked;
                const curFuDate = (followDate ? followDate.value : '').trim();
                const curFuTime = (timeOn && timeOn.checked && followTime ? followTime.value : '').trim();
                const curFuSummary = curFu
                    ? (curFuDate + (curFuTime ? ' ' + curFuTime : ''))
                    : 'Off';

                if (initFuSummary !== curFuSummary) {
                    changes.push({
                        label: 'Follow Up',
                        from: initFuSummary,
                        to: curFuSummary,
                        changed: true
                    });
                } else if (curFu) {
                    changes.push({
                        label: 'Follow Up',
                        from: null,
                        to: curFuSummary,
                        changed: false
                    });
                }
            }

            this.postUpdateFormEl = form;
            this.postUpdateCustomerName = form.dataset.customerName || this.title || 'Customer';
            this.postUpdateChanges = changes;
            this.postUpdateModalOpen = true;
        },
        async confirmPostUpdate() {
            if (!this.postUpdateFormEl || this.postUpdateSubmitting) return;
            this.postUpdateSubmitting = true;
            try {
                await this.postActivity(this.postUpdateFormEl);
                this.postUpdateModalOpen = false;
                this.postUpdateFormEl = null;
            } finally {
                this.postUpdateSubmitting = false;
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
                if (listBadge && data.list_date_html != null) {
                    listBadge.innerHTML = data.list_date_html;
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
    wrap.classList.toggle('hidden', !on);
    wrap.classList.toggle('flex', !!on);
    wrap.style.display = on ? 'flex' : 'none';
    if (on && dateEl && !dateEl.value) dateEl.value = nextDayYmd();
}
function handleStatusChangeFollowUp(id, statusVal) {
    const s = String(statusVal || '').trim().toLowerCase();
    if (['confirmed', 'confirm', 'converted', 'cancelled', 'lost'].includes(s)) {
        const chk = document.getElementById('followUpCheck-' + id);
        const timeChk = document.getElementById('followUpTimeOn-' + id);
        if (chk) {
            chk.checked = false;
            toggleFollowUpFields(id, false);
        }
        if (timeChk) {
            timeChk.checked = false;
            toggleFollowUpTime(id, false);
        }
    }
}
function toggleFollowUpTime(id, on) {
    const wrapEl = document.getElementById('followUpTimeWrap-' + id);
    const timeEl = document.getElementById('followUpTime-' + id);
    if (wrapEl) {
        wrapEl.classList.toggle('hidden', !on);
        wrapEl.classList.toggle('inline-flex', !!on);
        wrapEl.style.display = on ? 'inline-flex' : 'none';
    } else if (timeEl) {
        timeEl.style.display = on ? 'inline-block' : 'none';
    }
    if (on && timeEl && !timeEl.value) timeEl.value = currentHm();
}
function shiftFollowUpDate(id, deltaDays) {
    const dateEl = document.getElementById('followUpDate-' + id);
    if (!dateEl) return;
    const raw = (dateEl.value || '').trim();
    let base = new Date();
    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
        const parts = raw.split('-').map(Number);
        base = new Date(parts[0], parts[1] - 1, parts[2]);
    }
    base.setDate(base.getDate() + Number(deltaDays || 0));
    dateEl.value = base.getFullYear() + '-' + pad2(base.getMonth() + 1) + '-' + pad2(base.getDate());
}
function shiftFollowUpTime(id, deltaMinutes) {
    const timeEl = document.getElementById('followUpTime-' + id);
    if (!timeEl) return;
    const raw = (timeEl.value || '').trim();
    const now = new Date();
    let hours = now.getHours();
    let mins = now.getMinutes();
    if (/^\d{1,2}:\d{2}/.test(raw)) {
        const parts = raw.split(':').map(Number);
        hours = parts[0];
        mins = parts[1];
    }
    let totalMins = (hours * 60 + mins + Number(deltaMinutes || 0)) % 1440;
    if (totalMins < 0) totalMins += 1440;
    timeEl.value = pad2(Math.floor(totalMins / 60)) + ':' + pad2(totalMins % 60);
}
function shiftInputDate(inp, deltaDays) {
    if (!inp) return;
    const raw = (inp.value || '').trim();
    let base = new Date();
    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
        const parts = raw.split('-').map(Number);
        base = new Date(parts[0], parts[1] - 1, parts[2]);
    }
    base.setDate(base.getDate() + Number(deltaDays || 0));
    inp.value = base.getFullYear() + '-' + pad2(base.getMonth() + 1) + '-' + pad2(base.getDate());
}
function shiftInputTime(inp, deltaMinutes) {
    if (!inp) return;
    const raw = (inp.value || '').trim();
    const now = new Date();
    let hours = now.getHours();
    let mins = now.getMinutes();
    if (/^\d{1,2}:\d{2}/.test(raw)) {
        const parts = raw.split(':').map(Number);
        hours = parts[0];
        mins = parts[1];
    }
    let totalMins = (hours * 60 + mins + Number(deltaMinutes || 0)) % 1440;
    if (totalMins < 0) totalMins += 1440;
    inp.value = pad2(Math.floor(totalMins / 60)) + ':' + pad2(totalMins % 60);
}
function updateImageFileLabel(inputEl) {
    if (!inputEl) return;
    const info = inputEl.closest('div')?.querySelector('[data-img-info]');
    if (info) {
        info.textContent = inputEl.files?.length
            ? Array.from(inputEl.files).map(f => f.name).join(', ')
            : 'No image selected';
    }
}
function toggleMoreCustomerMessages(btnEl) {
    if (!btnEl) return;
    const cid = btnEl.dataset.cid;
    const moreCount = btnEl.dataset.moreCount || '';
    const items = document.querySelectorAll('[data-more-msg="' + cid + '"]');
    const lbl = btnEl.querySelector('[data-view-more-label]');
    let isHidden = false;
    items.forEach(el => { isHidden = el.classList.toggle('hidden'); });
    if (lbl) {
        lbl.textContent = isHidden ? 'View More (' + moreCount + ' more)' : 'Show Less';
    }
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
