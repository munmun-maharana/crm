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
                $links = [
                    ['dashboard', 'Dashboard'],
                    ['customers.index', 'CRM'],
                    ['upload.index', 'Excel Upload'],
                    ['fields.index', 'Fields'],
                    ['note-templates.index', 'Note Templates'],
                    ['users.index', 'Users'],
                ];
            @endphp
            <div class="px-2 pt-1 pb-2 text-[11px] font-semibold text-slate-500">Favorites</div>
            @foreach($links as [$route, $label])
                <a href="{{ route($route) }}"
                   class="nav-item flex items-center gap-2 px-3 py-1.5 mb-0.5 {{ request()->routeIs($route) || request()->routeIs(str_replace('.index','.*',$route)) ? 'active' : 'text-ink hover:bg-mist' }}">
                    <span>{{ $label }}</span>
                </a>
            @endforeach

            <div class="px-2 pt-4 pb-2 text-[11px] font-semibold text-slate-500">Automations</div>
            <div class="px-3 py-1.5 text-[12px] text-ink">Auto Call</div>
            <div class="px-3 py-1.5 text-[12px] text-ink">Auto WhatsApp</div>
            <div class="px-3 py-1.5 text-[12px] text-ink">Auto Email</div>
            <div class="px-3 py-1.5 text-[12px] text-ink">Auto SMS</div>
        </nav>
        <div class="p-3 border-t border-line text-[12px]">
            <div class="font-semibold truncate">{{ auth()->user()->name }}</div>
            <div class="flex items-center justify-between gap-2 mt-0.5">
                <span class="text-slate-500 truncate">{{ auth()->user()->username }}</span>
                <form method="POST" action="{{ route('logout') }}">@csrf
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
            this.form = { id: null };
            (window.CRM_FORM_FIELDS || []).forEach(f => {
                this.form[f.key] = f.key === 'status' ? 'new' : '';
            });
            this.formOpen = true;
        },
        openEditForm(c) {
            this.formMode = 'edit';
            this.form = { id: c.id };
            (window.CRM_FORM_FIELDS || []).forEach(f => {
                let val = c[f.key];
                if ((val === undefined || val === null) && c.meta) val = c.meta[f.key];
                this.form[f.key] = val ?? '';
            });
            this.formOpen = true;
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
</script>
@stack('scripts')
</body>
</html>
