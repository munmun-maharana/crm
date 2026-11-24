@extends('layouts.app')

@section('title', \App\Models\MenuSetting::getLabel('analytics', 'User Activity Analytics'))
@section('heading', $selectedUser ? (\App\Models\MenuSetting::getLabel('analytics', 'Analytics') . ' · ' . $selectedUser->name) : \App\Models\MenuSetting::getLabel('analytics', 'User Activity Analytics'))
@section('subheading', 'Every page visit, time spent, option clicked & selection for all non-Admin users (' . $periodLabel . ')')

@section('actions')
    <div class="flex flex-wrap items-center gap-1.5">
        @foreach(['day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'year' => 'Year'] as $pKey => $pLabel)
            <a href="{{ route('analytics.index', array_filter(['period' => $pKey, 'date' => $refDate->toDateString(), 'user_id' => $selectedUserId, 'role' => $selectedRole ?: null])) }}"
               class="px-3 py-1.5 rounded-lg text-[12px] font-bold border transition {{ $period === $pKey ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs' : 'bg-white text-slate-700 border-line hover:bg-mist' }}">
                {{ $pLabel }}
            </a>
        @endforeach
    </div>
@endsection

@section('content')
<div class="space-y-4">

    {{-- Filter Bar --}}
    <form method="GET" action="{{ route('analytics.index') }}"
          class="rounded-2xl border border-line bg-white p-3.5 shadow-xs flex flex-wrap items-end gap-3">
        <input type="hidden" name="period" value="{{ $period }}">

        <div class="flex items-center gap-1">
            <a href="{{ route('analytics.index', array_filter(['period' => $period, 'date' => $prevDate, 'user_id' => $selectedUserId, 'role' => $selectedRole ?: null])) }}"
               class="h-9 px-2.5 inline-flex items-center justify-center rounded-lg border border-line bg-mist/60 hover:bg-brand-soft hover:text-brand font-bold text-sm transition"
               title="Previous {{ $period }}">‹</a>
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-0.5">Reference Date</label>
                <input type="date" name="date" value="{{ $refDate->toDateString() }}"
                       onchange="this.form.submit()"
                       class="h-9 rounded-lg border border-line bg-white px-2.5 text-[12.5px] font-semibold text-ink outline-none focus:border-brand">
            </div>
            <a href="{{ route('analytics.index', array_filter(['period' => $period, 'date' => $nextDate, 'user_id' => $selectedUserId, 'role' => $selectedRole ?: null])) }}"
               class="h-9 px-2.5 inline-flex items-center justify-center rounded-lg border border-line bg-mist/60 hover:bg-brand-soft hover:text-brand font-bold text-sm transition"
               title="Next {{ $period }}">›</a>
        </div>

        <div class="min-w-[180px] flex-1">
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-0.5">User (Non-Admin)</label>
            <select name="user_id" onchange="this.form.submit()"
                    class="w-full h-9 rounded-lg border border-line bg-white px-2.5 text-[12.5px] font-semibold text-ink outline-none focus:border-brand">
                <option value="">All Users</option>
                @foreach($nonAdminUsers as $u)
                    <option value="{{ $u->id }}" @selected($selectedUserId === $u->id)>
                        {{ $u->name }} ({{ \App\Models\User::roleLabelFor($u->role) }})
                    </option>
                @endforeach
            </select>
        </div>

        <div class="min-w-[160px]">
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-0.5">User Type / Role</label>
            <select name="role" onchange="this.form.submit()"
                    class="w-full h-9 rounded-lg border border-line bg-white px-2.5 text-[12.5px] font-semibold text-ink outline-none focus:border-brand">
                <option value="">All User Types</option>
                @foreach($roles as $r)
                    <option value="{{ $r }}" @selected($selectedRole === $r)>{{ \App\Models\User::roleLabelFor($r) }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-center gap-2 ml-auto">
            <span class="px-3 py-1.5 rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-800 text-[12px] font-bold">
                {{ $periodLabel }}
            </span>
            @if($selectedUserId || $selectedRole !== '')
                <a href="{{ route('analytics.index', ['period' => $period, 'date' => $refDate->toDateString()]) }}"
                   class="h-9 px-3 inline-flex items-center rounded-lg border border-line text-slate-600 hover:bg-mist text-[12px] font-semibold">
                    Reset
                </a>
            @endif
        </div>
    </form>

    {{-- KPI Summary Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-white p-3.5 shadow-2xs">
            <div class="text-[10.5px] font-bold uppercase tracking-wider text-emerald-700">Total Desk Time</div>
            <div class="text-xl font-extrabold text-emerald-950 mt-1">{{ \App\Models\UserActivityLog::formatDuration($totalDeskSeconds) }}</div>
            <div class="text-[10px] text-emerald-700/80 mt-0.5">Shift − Break − Waste</div>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-3.5 shadow-2xs">
            <div class="text-[10.5px] font-bold uppercase tracking-wider text-amber-700">Total Break Time</div>
            <div class="text-xl font-extrabold text-amber-950 mt-1">{{ \App\Models\UserActivityLog::formatDuration($totalBreakSeconds) }}</div>
        </div>
        <div class="rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white p-3.5 shadow-2xs">
            <div class="text-[10.5px] font-bold uppercase tracking-wider text-indigo-600">Total Page Time</div>
            <div class="text-xl font-extrabold text-indigo-950 mt-1">{{ \App\Models\UserActivityLog::formatDuration($totalTimeSeconds) }}</div>
        </div>
        <div class="rounded-2xl border border-rose-200 bg-gradient-to-br from-rose-50 to-white p-3.5 shadow-2xs">
            <div class="text-[10.5px] font-bold uppercase tracking-wider text-rose-600">Total Waste Time</div>
            <div class="text-xl font-extrabold text-rose-950 mt-1">{{ \App\Models\UserActivityLog::formatDuration($totalWasteSeconds) }}</div>
        </div>
        <div class="rounded-2xl border border-sky-200 bg-gradient-to-br from-sky-50 to-white p-3.5 shadow-2xs">
            <div class="text-[10.5px] font-bold uppercase tracking-wider text-sky-600">Page Views</div>
            <div class="text-xl font-extrabold text-sky-950 mt-1">{{ number_format($totalPageViews) }}</div>
        </div>
        <div class="rounded-2xl border border-teal-200 bg-gradient-to-br from-teal-50 to-white p-3.5 shadow-2xs">
            <div class="text-[10.5px] font-bold uppercase tracking-wider text-teal-600">Options Clicked</div>
            <div class="text-xl font-extrabold text-teal-950 mt-1">{{ number_format($totalClicks) }}</div>
        </div>
        <div class="rounded-2xl border border-orange-200 bg-gradient-to-br from-orange-50 to-white p-3.5 shadow-2xs">
            <div class="text-[10.5px] font-bold uppercase tracking-wider text-orange-600">Selections Made</div>
            <div class="text-xl font-extrabold text-orange-950 mt-1">{{ number_format($totalSelects) }}</div>
        </div>
        <div class="rounded-2xl border border-purple-200 bg-gradient-to-br from-purple-50 to-white p-3.5 shadow-2xs">
            <div class="text-[10.5px] font-bold uppercase tracking-wider text-purple-600">Submits &amp; API</div>
            <div class="text-xl font-extrabold text-purple-950 mt-1">{{ number_format($totalSubmits) }}</div>
        </div>
    </div>

    {{-- Graphs Row --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
        <div class="lg:col-span-7 rounded-2xl border border-line bg-white p-4 shadow-xs">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h2 class="text-sm font-bold text-ink">Total Time vs Waste Time ({{ ucfirst($period) }})</h2>
                    <p class="text-[11px] text-slate-500">Line comparison of total minutes spent vs idle/waste minutes across {{ $periodLabel }}</p>
                </div>
            </div>
            <div class="h-64">
                <canvas id="analyticsTimelineChart"></canvas>
            </div>
        </div>

        <div class="lg:col-span-5 rounded-2xl border border-line bg-white p-4 shadow-xs">
            <div class="mb-3">
                <h2 class="text-sm font-bold text-ink">Time Spent Per Page (Minutes)</h2>
                <p class="text-[11px] text-slate-500">Top pages by total stay duration</p>
            </div>
            <div class="h-64">
                <canvas id="analyticsPageChart"></canvas>
            </div>
        </div>
    </div>

    {{-- User Breakdown Table (shown when viewing all users or multiple users) --}}
    @if($userStats->isNotEmpty())
        <div class="rounded-2xl border border-line bg-white shadow-xs overflow-hidden">
            <div class="px-4 py-3 border-b border-line bg-slate-50 flex items-center justify-between">
                <h2 class="text-sm font-bold text-ink">Users Activity Summary ({{ $userStats->count() }})</h2>
                <span class="text-[11px] text-slate-500">Admin excluded</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-[12.5px]">
                    <thead class="bg-mist/60 text-slate-500 text-[10.5px] uppercase tracking-wider">
                        <tr>
                            <th class="px-4 py-2.5 font-bold">User</th>
                            <th class="px-4 py-2.5 font-bold">User Type</th>
                            <th class="px-4 py-2.5 font-bold">Page Views</th>
                            <th class="px-4 py-2.5 font-bold">Clicks</th>
                            <th class="px-4 py-2.5 font-bold">Selects</th>
                            <th class="px-4 py-2.5 font-bold">Submits / API</th>
                            <th class="px-4 py-2.5 font-bold">Total Time</th>
                            <th class="px-4 py-2.5 font-bold">Break Time</th>
                            <th class="px-4 py-2.5 font-bold">Waste Time</th>
                            <th class="px-4 py-2.5 font-bold">Desk Time</th>
                            <th class="px-4 py-2.5 font-bold text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach($userStats as $us)
                            <tr class="hover:bg-mist/40">
                                <td class="px-4 py-2.5 font-bold text-ink">{{ $us['name'] }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 text-[11px] font-bold">{{ $us['role'] }}</span>
                                </td>
                                <td class="px-4 py-2.5 font-semibold">{{ $us['page_views'] }}</td>
                                <td class="px-4 py-2.5 font-semibold text-emerald-700">{{ $us['clicks'] }}</td>
                                <td class="px-4 py-2.5 font-semibold text-amber-700">{{ $us['selects'] }}</td>
                                <td class="px-4 py-2.5 font-semibold text-purple-700">{{ $us['submits'] }}</td>
                                <td class="px-4 py-2.5 font-extrabold text-indigo-700">{{ \App\Models\UserActivityLog::formatDuration($us['total_seconds']) }}</td>
                                <td class="px-4 py-2.5 font-extrabold text-amber-600">{{ \App\Models\UserActivityLog::formatDuration($us['break_seconds']) }}</td>
                                <td class="px-4 py-2.5 font-extrabold text-rose-600">{{ \App\Models\UserActivityLog::formatDuration($us['waste_seconds']) }}</td>
                                <td class="px-4 py-2.5 font-extrabold text-emerald-700">{{ \App\Models\UserActivityLog::formatDuration($us['desk_seconds']) }}</td>
                                <td class="px-4 py-2.5 text-right">
                                    <a href="{{ route('analytics.index', ['user_id' => $us['user_id'], 'period' => $period, 'date' => $refDate->toDateString()]) }}"
                                       class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-[11px] font-bold transition">
                                        View Analytics →
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Work Sessions (Start -> End, Break, Waste, Desk Time) --}}
    <div class="rounded-2xl border border-line bg-white shadow-xs overflow-hidden">
        <div class="px-4 py-3 border-b border-line bg-emerald-50/70 flex items-center justify-between">
            <h2 class="text-sm font-bold text-emerald-950">Work Sessions (Start → End · Break · Waste · Desk Time)</h2>
            <span class="text-[11px] text-emerald-700 font-semibold">Desk Time = Total Shift − Break − Waste</span>
        </div>
        <div class="overflow-x-auto max-h-80 crm-scroll">
            <table class="w-full text-left text-[12px]">
                <thead class="bg-mist/60 text-slate-500 text-[10.5px] uppercase tracking-wider sticky top-0">
                    <tr>
                        <th class="px-4 py-2 font-bold">User</th>
                        <th class="px-4 py-2 font-bold">Start</th>
                        <th class="px-4 py-2 font-bold">End</th>
                        <th class="px-4 py-2 font-bold">Status</th>
                        <th class="px-4 py-2 font-bold">Shift Time</th>
                        <th class="px-4 py-2 font-bold">Break Time</th>
                        <th class="px-4 py-2 font-bold">Waste Time</th>
                        <th class="px-4 py-2 font-bold">Desk Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse($workSessions as $ws)
                        @php
                            $wsTot = $ws->computedTotalSeconds();
                            $wsBrk = $ws->computedBreakSeconds();
                            $wsWst = (int) $ws->total_waste_seconds;
                            $wsDsk = $ws->computedDeskSeconds();
                        @endphp
                        <tr class="hover:bg-mist/40">
                            <td class="px-4 py-2 font-bold text-ink">
                                {{ $ws->user?->name ?? ('User #' . $ws->user_id) }}
                                <span class="ml-1 text-[10px] text-indigo-700 font-semibold">({{ \App\Models\User::roleLabelFor($ws->user_role) }})</span>
                            </td>
                            <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $ws->started_at?->format('d M Y, h:i:s A') }}</td>
                            <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $ws->ended_at ? $ws->ended_at->format('d M Y, h:i:s A') : 'Running' }}</td>
                            <td class="px-4 py-2">
                                @if($ws->status === 'active')
                                    <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 text-[10.5px] font-bold">Working</span>
                                @elseif($ws->status === 'on_break')
                                    <span class="px-2 py-0.5 rounded bg-amber-100 text-amber-800 text-[10.5px] font-bold">On Break</span>
                                @else
                                    <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 text-[10.5px] font-bold">Ended</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 font-bold text-indigo-700">{{ \App\Models\UserActivityLog::formatDuration($wsTot) }}</td>
                            <td class="px-4 py-2 font-bold text-amber-600">{{ \App\Models\UserActivityLog::formatDuration($wsBrk) }}</td>
                            <td class="px-4 py-2 font-bold text-rose-600">{{ \App\Models\UserActivityLog::formatDuration($wsWst) }}</td>
                            <td class="px-4 py-2 font-extrabold text-emerald-700">{{ \App\Models\UserActivityLog::formatDuration($wsDsk) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-6 text-center text-slate-400">No work sessions recorded in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Per-Page Time + Top Clicked / Selected Options --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-line bg-white shadow-xs overflow-hidden">
            <div class="px-4 py-3 border-b border-line bg-sky-50/70">
                <h2 class="text-sm font-bold text-sky-950">Page-wise Time Spent</h2>
            </div>
            <div class="overflow-x-auto max-h-80 crm-scroll">
                <table class="w-full text-left text-[12px]">
                    <thead class="bg-mist/60 text-slate-500 text-[10px] uppercase tracking-wider sticky top-0">
                        <tr>
                            <th class="px-3.5 py-2 font-bold">Page</th>
                            <th class="px-3.5 py-2 font-bold">Visits</th>
                            <th class="px-3.5 py-2 font-bold">Total Time</th>
                            <th class="px-3.5 py-2 font-bold">Avg / Visit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse($pageStats as $ps)
                            @php
                                $totSec = (int) $ps->total_seconds;
                                $visits = max(1, (int) $ps->visits);
                                $avgSec = (int) round($totSec / $visits);
                            @endphp
                            <tr class="hover:bg-mist/40">
                                <td class="px-3.5 py-2">
                                    <div class="font-bold text-ink">{{ $ps->page_path }}</div>
                                    @if($ps->page_title)
                                        <div class="text-[10.5px] text-slate-400 truncate max-w-[240px]">{{ $ps->page_title }}</div>
                                    @endif
                                </td>
                                <td class="px-3.5 py-2 font-semibold">{{ $ps->visits }}</td>
                                <td class="px-3.5 py-2 font-bold text-indigo-700">{{ \App\Models\UserActivityLog::formatDuration($totSec) }}</td>
                                <td class="px-3.5 py-2 text-slate-600">{{ \App\Models\UserActivityLog::formatDuration($avgSec) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">No page views recorded in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl border border-line bg-white shadow-xs overflow-hidden">
            <div class="px-4 py-3 border-b border-line bg-emerald-50/70">
                <h2 class="text-sm font-bold text-emerald-950">Top Clicked Options &amp; Selected Values</h2>
            </div>
            <div class="overflow-x-auto max-h-80 crm-scroll">
                <table class="w-full text-left text-[12px]">
                    <thead class="bg-mist/60 text-slate-500 text-[10px] uppercase tracking-wider sticky top-0">
                        <tr>
                            <th class="px-3.5 py-2 font-bold">Type</th>
                            <th class="px-3.5 py-2 font-bold">Option / Field</th>
                            <th class="px-3.5 py-2 font-bold">Selected Value</th>
                            <th class="px-3.5 py-2 font-bold text-right">Count</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse($actionStats as $as)
                            <tr class="hover:bg-mist/40">
                                <td class="px-3.5 py-2">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase {{ $as->event_type === 'select' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                                        {{ $as->event_type }}
                                    </span>
                                </td>
                                <td class="px-3.5 py-2">
                                    <div class="font-semibold text-ink">{{ $as->target_label }}</div>
                                    <div class="text-[10px] text-slate-400">{{ $as->page_path }}</div>
                                </td>
                                <td class="px-3.5 py-2 text-slate-700 font-medium">{{ $as->target_value ?: '—' }}</td>
                                <td class="px-3.5 py-2 text-right font-extrabold text-ink">{{ $as->total_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">No clicks or selections recorded in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Detailed Activity Timeline --}}
    <div class="rounded-2xl border border-line bg-white shadow-xs overflow-hidden">
        <div class="px-4 py-3 border-b border-line bg-slate-50 flex items-center justify-between">
            <h2 class="text-sm font-bold text-ink">Detailed Activity Log</h2>
            <span class="text-[11px] text-slate-500">Showing {{ $logs->total() }} event(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-[12px]">
                <thead class="bg-mist/60 text-slate-500 text-[10px] uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-2.5 font-bold">Time</th>
                        <th class="px-4 py-2.5 font-bold">User</th>
                        <th class="px-4 py-2.5 font-bold">Event</th>
                        <th class="px-4 py-2.5 font-bold">Page</th>
                        <th class="px-4 py-2.5 font-bold">Clicked / Selected</th>
                        <th class="px-4 py-2.5 font-bold">Duration</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse($logs as $log)
                        @php
                            $evBadge = match ($log->event_type) {
                                'page_view' => 'bg-sky-100 text-sky-800',
                                'click' => 'bg-emerald-100 text-emerald-800',
                                'select' => 'bg-amber-100 text-amber-800',
                                default => 'bg-rose-100 text-rose-800',
                            };
                        @endphp
                        <tr class="hover:bg-mist/40">
                            <td class="px-4 py-2 text-slate-500 whitespace-nowrap">{{ $log->created_at?->format('d M Y, h:i:s A') }}</td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                <span class="font-bold text-ink">{{ $log->user?->name ?? ('User #' . $log->user_id) }}</span>
                                <span class="ml-1 text-[10px] text-indigo-700 font-semibold">({{ \App\Models\User::roleLabelFor($log->user_role) }})</span>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $evBadge }}">{{ $log->event_type }}</span>
                            </td>
                            <td class="px-4 py-2 font-mono text-[11px] text-slate-700">{{ $log->page_path }}</td>
                            <td class="px-4 py-2">
                                @if($log->target_label)
                                    <span class="font-semibold text-ink">{{ $log->target_label }}</span>
                                @endif
                                @if($log->target_value)
                                    <span class="ml-1 text-indigo-700 font-medium">→ {{ $log->target_value }}</span>
                                @endif
                                @if(!$log->target_label && !$log->target_value)
                                    <span class="text-slate-400">{{ $log->page_title ?: '—' }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 font-bold text-indigo-700 whitespace-nowrap">
                                {{ $log->event_type === 'page_view' ? \App\Models\UserActivityLog::formatDuration((int) $log->duration_seconds) : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No activity recorded for this filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3 border-t border-line">{{ $logs->links() }}</div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof Chart === 'undefined') return;

    const tlCtx = document.getElementById('analyticsTimelineChart');
    if (tlCtx) {
        new Chart(tlCtx, {
            type: 'line',
            data: {
                labels: @json($timelineLabels),
                datasets: [
                    {
                        label: 'Total Time (mins)',
                        data: @json($timelineMinutes),
                        borderColor: '#4F46E5',
                        backgroundColor: 'rgba(79, 70, 229, 0.14)',
                        borderWidth: 2.5,
                        pointRadius: 3,
                        tension: 0.3,
                        fill: true,
                    },
                    {
                        label: 'Total Waste Time (mins)',
                        data: @json($timelineWasteMinutes),
                        borderColor: '#E11D48',
                        backgroundColor: 'rgba(225, 29, 72, 0.12)',
                        borderWidth: 2.5,
                        pointRadius: 3,
                        tension: 0.3,
                        fill: true,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Minutes' } }
                }
            }
        });
    }

    const pgCtx = document.getElementById('analyticsPageChart');
    if (pgCtx) {
        new Chart(pgCtx, {
            type: 'bar',
            data: {
                labels: @json($pageChartLabels),
                datasets: [{
                    label: 'Minutes Spent',
                    data: @json($pageChartMinutes),
                    backgroundColor: ['#4F46E5', '#0284C7', '#059669', '#D97706', '#E11D48', '#7C3AED', '#0D9488', '#64748B'],
                    borderRadius: 6,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, title: { display: true, text: 'Minutes' } } }
            }
        });
    }
});
</script>
@endsection
