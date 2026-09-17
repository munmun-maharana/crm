@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')
@section('subheading', auth()->user()->canSeeAllRecords()
    ? (auth()->user()->isOperation() ? 'All Operations data' : 'All mapped CRM data')
    : (auth()->user()->isOperation() ? 'Operations queue' : 'Only leads mapped to you'))

@section('content')
<div class="grid gap-3 mb-5" style="grid-template-columns: repeat({{ min(10, max(2, count($statusCards))) }}, minmax(0, 1fr));">
    @foreach($statusCards as $card)
        <div class="stat-card rounded-2xl border border-line bg-white px-4 py-3.5">
            <div class="text-[11px] uppercase tracking-[0.14em] text-slate-400 font-bold">{{ $card['label'] }}</div>
            <div class="mt-1 text-2xl font-extrabold text-{{ $card['tone'] }}-700">{{ $card['value'] }}</div>
        </div>
    @endforeach
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
    <div class="rounded-2xl border border-line bg-white p-4 min-w-0">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-extrabold uppercase tracking-wide">Daily leads</h2>
            <span class="text-[11px] text-slate-400">Last 7 days</span>
        </div>
        <div class="h-[260px] relative">
            <canvas id="dayChart"></canvas>
        </div>
    </div>
    <div class="rounded-2xl border border-line bg-white p-4 min-w-0">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-extrabold uppercase tracking-wide">Monthly leads</h2>
            <span class="text-[11px] text-slate-400">Last 12 months</span>
        </div>
        <div class="h-[260px] relative">
            <canvas id="monthChart"></canvas>
        </div>
    </div>
    <div class="rounded-2xl border border-line bg-white p-4 min-w-0">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-extrabold uppercase tracking-wide">Yearly leads</h2>
            <span class="text-[11px] text-slate-400">5 year trend</span>
        </div>
        <div class="h-[260px] relative">
            <canvas id="yearChart"></canvas>
        </div>
    </div>
    <div class="rounded-2xl border border-line bg-white p-4 min-w-0">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-extrabold uppercase tracking-wide">Falloff funnel</h2>
            <span class="text-[11px] text-slate-400">Pipeline drop</span>
        </div>
        <div class="h-[260px] relative">
            <canvas id="falloffChart"></canvas>
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
</script>
@endpush
