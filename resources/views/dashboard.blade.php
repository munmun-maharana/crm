@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')
@section('subheading', auth()->user()->canSeeAllRecords()
    ? (auth()->user()->isOperation() ? 'All Operations data' : 'All mapped CRM data')
    : (auth()->user()->isOperation() ? 'Operations queue' : 'Only leads mapped to you'))

@section('content')
<div class="grid gap-2 sm:gap-3 mb-5 grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:[grid-template-columns:repeat(var(--cols),minmax(0,1fr))]" style="--cols: {{ min(10, max(2, count($statusCards))) }};">
    @foreach($statusCards as $card)
        <a href="{{ $card['url'] ?? '#' }}" class="stat-card block rounded-2xl border border-line bg-white px-3 sm:px-4 py-3 sm:py-3.5 hover:border-brand/40 hover:shadow-sm transition min-w-0">
            <div class="text-[10px] sm:text-[11px] uppercase tracking-[0.1em] sm:tracking-[0.14em] text-slate-400 font-bold truncate" title="{{ $card['label'] }}">{{ $card['label'] }}</div>
            <div class="mt-1 text-xl sm:text-2xl font-extrabold text-{{ $card['tone'] }}-700">{{ $card['value'] }}</div>
        </a>
    @endforeach
</div>

<div class="grid grid-cols-1 xl:grid-cols-12 gap-4 mb-4 items-start">
    {{-- Left Side: All Graphs (2x2 grid) --}}
    <div class="xl:col-span-7 grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-line bg-white p-4 min-w-0">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-extrabold uppercase tracking-wide">Daily leads</h2>
                <span class="text-[11px] text-slate-400">Last 7 days</span>
            </div>
            <div class="h-[230px] relative">
                <canvas id="dayChart"></canvas>
            </div>
        </div>
        <div class="rounded-2xl border border-line bg-white p-4 min-w-0">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-extrabold uppercase tracking-wide">Monthly leads</h2>
                <span class="text-[11px] text-slate-400">Last 12 months</span>
            </div>
            <div class="h-[230px] relative">
                <canvas id="monthChart"></canvas>
            </div>
        </div>
        <div class="rounded-2xl border border-line bg-white p-4 min-w-0">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-extrabold uppercase tracking-wide">Yearly leads</h2>
                <span class="text-[11px] text-slate-400">5 year trend</span>
            </div>
            <div class="h-[230px] relative">
                <canvas id="yearChart"></canvas>
            </div>
        </div>
        <div class="rounded-2xl border border-line bg-white p-4 min-w-0">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-extrabold uppercase tracking-wide">Falloff funnel</h2>
                <span class="text-[11px] text-slate-400">Pipeline drop</span>
            </div>
            <div class="h-[230px] relative">
                <canvas id="falloffChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Right Side: Booking Slot Details with WhatsApp Buttons --}}
    <div class="xl:col-span-5 rounded-2xl border border-line bg-white overflow-hidden flex flex-col"
         x-data="dashboardBookingSlots(@js($whatsappTargetPhone))"
         x-init="loadSlots()"
         @booking-slots-updated.window="loadSlots(date)">
        <div class="px-4 py-3 border-b border-line bg-slate-50/80 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <span class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-200 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </span>
                <div>
                    <h2 class="text-sm font-extrabold text-ink leading-tight">Booking Slot Details</h2>
                    <div class="text-[11px] text-indigo-700 font-semibold" x-text="dateFormatted"></div>
                </div>
            </div>

            <div class="flex items-center gap-1.5">
                <div class="inline-flex items-center rounded-lg border border-line bg-white overflow-hidden">
                    <button type="button" @click="shiftDate(-1)" title="Previous day" class="px-2 py-1 text-slate-600 hover:bg-mist text-xs font-bold">‹</button>
                    <input type="date" :value="date" @change="loadSlots($event.target.value)"
                           class="border-x border-line px-2 py-1 text-[11.5px] font-semibold text-ink focus:outline-none">
                    <button type="button" @click="shiftDate(1)" title="Next day" class="px-2 py-1 text-slate-600 hover:bg-mist text-xs font-bold">›</button>
                </div>
                <button type="button" @click="loadSlots('{{ now()->toDateString() }}')"
                        class="px-2 py-1 rounded-lg border border-line bg-white hover:bg-mist text-[11px] font-semibold text-slate-700">
                    Today
                </button>
            </div>
        </div>

        {{-- Counters & Send All Booked to WhatsApp --}}
        <div class="px-4 py-2 border-b border-line bg-white flex flex-wrap items-center justify-between gap-2 text-[11px] font-semibold">
            <div class="flex items-center gap-1.5">
                <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800" x-text="'Available: ' + (counts.available || 0)"></span>
                <span class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-800" x-text="'Booked: ' + (counts.booked || 0)"></span>
                <template x-if="(counts.success || 0) > 0">
                    <span class="px-2 py-0.5 rounded-full bg-teal-100 text-teal-800" x-text="'Success: ' + counts.success"></span>
                </template>
                <template x-if="(counts.cancelled || 0) > 0">
                    <span class="px-2 py-0.5 rounded-full bg-slate-200 text-slate-700" x-text="'Cancelled: ' + counts.cancelled"></span>
                </template>
            </div>

            <button type="button"
                    x-show="bookedSlots().length > 0"
                    @click="sendAllBookedWhatsapp()"
                    class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white px-2.5 py-1 text-[11px] font-bold shadow-2xs transition">
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0012.04 2zm0 1.82c4.46 0 8.09 3.63 8.09 8.09s-3.63 8.09-8.09 8.09c-1.42 0-2.8-.37-4.01-1.06l-.29-.17-3.12.82.83-3.04-.19-.31a8.05 8.05 0 01-1.22-4.33c0-4.46 3.63-8.09 8.09-8.09zm4.57 10.38c-.18-.09-1.06-.52-1.22-.58-.16-.06-.28-.09-.4.09-.12.18-.46.58-.56.7-.1.12-.2.13-.38.04-.18-.09-.76-.28-1.45-.89-.54-.48-.9-1.07-1.01-1.25-.1-.18-.01-.28.08-.37.08-.08.18-.2.27-.3.09-.1.12-.18.18-.3.06-.12.03-.22-.02-.31-.04-.09-.4-.96-.55-1.31-.14-.34-.29-.3-.4-.3h-.34c-.12 0-.31.04-.47.22-.16.18-.62.61-.62 1.48s.64 1.72.73 1.84c.09.12 1.25 1.91 3.03 2.68.42.18.75.29 1.01.37.42.13.81.11 1.11.07.34-.05 1.06-.43 1.21-.85.15-.42.15-.78.1-.85-.04-.07-.16-.12-.34-.21z"/></svg>
                <span>Send All Booked WP</span>
            </button>
        </div>

        {{-- Slots List --}}
        <div class="p-3.5 space-y-2.5 max-h-[505px] overflow-y-auto crm-scroll">
            <template x-if="loading">
                <div class="space-y-2">
                    <template x-for="i in 4" :key="i">
                        <div class="h-16 rounded-xl border border-line bg-slate-50 animate-pulse"></div>
                    </template>
                </div>
            </template>

            <template x-if="!loading && slots.length === 0">
                <div class="rounded-xl border border-dashed border-slate-300 bg-mist/30 py-12 px-4 text-center text-[12.5px] text-slate-500">
                    No booking slots configured for <strong class="text-ink" x-text="dateFormatted"></strong>.
                </div>
            </template>

            <template x-if="!loading && slots.length > 0">
                <div class="space-y-2">
                    <template x-for="slot in slots" :key="slot.id">
                        <div :class="{
                                 'border-emerald-300 bg-white hover:border-emerald-400 cursor-pointer': slot.status === 'available',
                                 'border-rose-200 bg-rose-50/70': slot.status === 'booked',
                                 'border-teal-300 bg-teal-50/70': slot.status === 'success',
                                 'border-slate-200 bg-slate-100 opacity-75': slot.status === 'cancelled'
                             }"
                             @click="slot.status === 'available' ? openBookSlotModal(slot) : null"
                             class="rounded-xl border p-3 flex items-center justify-between gap-2.5 transition">

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-[12.5px] font-bold text-ink" x-text="slot.label"></span>
                                    <span class="text-[9.5px] font-bold uppercase px-1.5 py-0.5 rounded shrink-0"
                                          :class="{
                                              'bg-emerald-100 text-emerald-800': slot.status === 'available',
                                              'bg-rose-100 text-rose-800': slot.status === 'booked',
                                              'bg-teal-600 text-white': slot.status === 'success',
                                              'bg-slate-200 text-slate-600': slot.status === 'cancelled'
                                          }"
                                          x-text="slot.status"></span>
                                </div>

                                <template x-if="slot.status === 'available'">
                                    <div class="text-[11px] font-medium text-emerald-700 mt-0.5">Available — Click to book for a customer</div>
                                </template>

                                <template x-if="slot.status === 'booked' || slot.status === 'success'">
                                    <div class="space-y-1 mt-1">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11.5px] font-bold"
                                             :class="slot.status === 'success' ? 'text-teal-900' : 'text-rose-900'">
                                            <span class="inline-flex items-center gap-1 truncate">
                                                <svg class="w-3.5 h-3.5 shrink-0" :class="slot.status === 'success' ? 'text-teal-600' : 'text-rose-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                                <span class="truncate" x-text="slot.customer_name || 'Customer'"></span>
                                            </span>
                                            <template x-if="slot.customer_phone">
                                                <span class="inline-flex items-center gap-1 text-[10.5px] font-semibold px-1.5 py-0.2 rounded"
                                                      :class="slot.status === 'success' ? 'text-teal-800 bg-teal-100/80' : 'text-rose-800 bg-rose-100/80'">
                                                    <span>📞</span>
                                                    <span x-text="slot.customer_phone"></span>
                                                </span>
                                            </template>
                                        </div>
                                        <template x-if="slot.car_model || slot.service_type || slot.price">
                                            <div class="flex flex-wrap items-center gap-1 text-[10.5px] text-slate-700">
                                                <template x-if="slot.car_model">
                                                    <span class="inline-flex items-center gap-1 rounded bg-white/90 border border-line px-1.5 py-0.2 font-semibold">
                                                        <span class="text-slate-400">Model:</span>
                                                        <span class="text-ink" x-text="slot.car_model"></span>
                                                    </span>
                                                </template>
                                                <template x-if="slot.service_type">
                                                    <span class="inline-flex items-center gap-1 rounded bg-white/90 border border-line px-1.5 py-0.2 font-semibold">
                                                        <span class="text-slate-400">Service:</span>
                                                        <span class="text-ink" x-text="slot.service_type"></span>
                                                    </span>
                                                </template>
                                                <template x-if="slot.price">
                                                    <span class="inline-flex items-center gap-0.5 rounded bg-emerald-50 border border-emerald-200 px-1.5 py-0.2 font-bold text-emerald-800">
                                                        <span>₹</span>
                                                        <span x-text="slot.price"></span>
                                                    </span>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <template x-if="slot.status === 'cancelled'">
                                    <div class="text-[11px] font-semibold text-slate-500 line-through mt-0.5">Slot Cancelled</div>
                                </template>
                            </div>

                            <div class="shrink-0 flex items-center gap-1.5" @click.stop>
                                <template x-if="slot.status === 'available'">
                                    <button type="button" @click="openBookSlotModal(slot)"
                                            class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white px-2.5 py-1.5 text-[11px] font-bold shadow-2xs transition">
                                        Book →
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>
</div>

<div class="rounded-2xl border border-line bg-white overflow-hidden">
    <div class="px-4 py-3 border-b border-line flex items-center justify-between">
        <h2 class="text-sm font-extrabold">Recent customers</h2>
        <a href="{{ route($listRoute) }}" class="text-[12px] font-bold text-brand hover:underline">{{ ($listRoute ?? 'customers.index') === 'operations.index' ? 'Open TEAM OPS' : 'Open CRM' }}</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-[13px]">
            <thead class="bg-mist/80 text-slate-500 text-[11px] uppercase tracking-wide">
                <tr>
                    <th class="text-left font-bold px-4 py-2.5">Name</th>
                    <th class="text-left font-bold px-4 py-2.5">Phone</th>
                    <th class="text-left font-bold px-4 py-2.5">Status</th>
                    <th class="text-left font-bold px-4 py-2.5">Agent</th>
                    <th class="text-right font-bold px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($recent as $c)
                    <tr class="border-t border-line hover:bg-mist/50 cursor-pointer" onclick="window.location='{{ route($listRoute, ['open' => $c->id]) }}'">
                        <td class="px-4 py-2.5 font-semibold">{{ $c->name }}</td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $c->phone ?: '—' }}</td>
                        <td class="px-4 py-2.5"><span class="inline-flex px-2 py-0.5 rounded-md bg-brand-soft text-brand text-[11px] font-bold capitalize">{{ $c->status }}</span></td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $c->assignee?->name ?: '—' }}</td>
                        <td class="px-4 py-2.5 text-right text-brand font-bold text-[12px]">Open →</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">No customers yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
const common = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
        y: { beginAtZero: true, grid: { color: '#eef2f7' }, ticks: { font: { size: 11 }, precision: 0 } }
    }
};
new Chart(document.getElementById('dayChart'), {
    type: 'bar',
    data: { labels: @json($dayLabels), datasets: [{ data: @json($dayData), backgroundColor: '#0f766e', borderRadius: 6, barThickness: 18 }] },
    options: common
});
new Chart(document.getElementById('monthChart'), {
    type: 'line',
    data: { labels: @json($monthLabels), datasets: [{ data: @json($monthData), borderColor: '#0284c7', backgroundColor: 'rgba(2,132,199,.12)', fill: true, tension: .35, pointRadius: 3 }] },
    options: common
});
new Chart(document.getElementById('yearChart'), {
    type: 'bar',
    data: { labels: @json($yearLabels), datasets: [{ data: @json($yearData), backgroundColor: '#334155', borderRadius: 6, barThickness: 28 }] },
    options: common
});
new Chart(document.getElementById('falloffChart'), {
    type: 'doughnut',
    data: {
        labels: @json($falloffLabels),
        datasets: [{ data: @json($falloffData), backgroundColor: @json($falloffChartColors), borderWidth: 0 }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } }
    }
});

function dashboardBookingSlots(whatsappTargetPhone) {
    return {
        date: '{{ now()->toDateString() }}',
        dateFormatted: '{{ now()->format("d M Y") }}',
        loading: false,
        slots: [],
        counts: { total: 0, available: 0, booked: 0, cancelled: 0 },
        whatsappTargetPhone: whatsappTargetPhone || '',

        async loadSlots(newDate = null) {
            if (newDate) this.date = newDate;
            this.loading = true;
            try {
                const res = await fetch(`{{ route('booking-slots.index') }}?date=${encodeURIComponent(this.date)}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                if (res.ok) {
                    this.date = data.date || this.date;
                    this.dateFormatted = data.formatted_date || data.date_formatted || this.date;
                    this.slots = data.slots || [];
                    this.counts = data.counts || { total: 0, available: 0, booked: 0, cancelled: 0 };
                }
            } catch (e) {
                console.error('Failed to load dashboard booking slots', e);
            } finally {
                this.loading = false;
            }
        },

        shiftDate(days) {
            const parts = (this.date || '').split('-').map(Number);
            const d = parts.length === 3 ? new Date(parts[0], parts[1] - 1, parts[2]) : new Date();
            d.setDate(d.getDate() + days);
            const ymd = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            this.loadSlots(ymd);
        },

        bookedSlots() {
            return (this.slots || []).filter(s => s.status === 'booked');
        },

        formatSlotText(slot, idx = null) {
            const prefix = idx != null ? `*#${idx} Booking Slot*` : `*Booking Slot Details*`;
            const lines = [
                prefix,
                `📅 *Date:* ${this.dateFormatted} (${this.date})`,
                `⏰ *Slot Time:* ${slot.label || '-'}`,
                `👤 *Customer:* ${slot.customer_name || '-'}`,
            ];
            if (slot.customer_phone) lines.push(`📞 *Phone:* ${slot.customer_phone}`);
            if (slot.car_model) lines.push(`🚗 *Car Model:* ${slot.car_model}`);
            if (slot.service_type) lines.push(`🛠 *Service:* ${slot.service_type}`);
            if (slot.price) lines.push(`💰 *Price:* ₹${slot.price}`);
            return lines.join('\n');
        },

        openWhatsappWithMessage(message, fallbackPhone = '') {
            let target = (this.whatsappTargetPhone || fallbackPhone || '').replace(/\D+/g, '');
            if (target.length === 10) target = '91' + target;
            const url = target
                ? `https://api.whatsapp.com/send?phone=${target}&text=${encodeURIComponent(message)}`
                : `https://api.whatsapp.com/send?text=${encodeURIComponent(message)}`;
            window.open(url, '_blank');
        },

        sendSlotWhatsapp(slot) {
            if (!slot) return;
            const msg = this.formatSlotText(slot);
            this.openWhatsappWithMessage(msg, slot.customer_phone || '');
        },

        sendAllBookedWhatsapp() {
            const booked = this.bookedSlots();
            if (!booked.length) return;
            const header = `*📋 Booked Slots Summary — ${this.dateFormatted} (${booked.length} Booked)*`;
            const body = booked.map((s, i) => this.formatSlotText(s, i + 1)).join('\n\n──────────────────\n\n');
            this.openWhatsappWithMessage(`${header}\n\n${body}`);
        }
    };
}
</script>
@endpush
