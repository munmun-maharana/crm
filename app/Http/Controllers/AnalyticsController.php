<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserActivityLog;
use App\Models\UserWorkSession;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function trackEvent(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || $user->isAdmin()) {
            return response()->json(['ok' => true, 'skipped' => true]);
        }

        $allowedTypes = ['page_view', 'click', 'select', 'submit'];
        $events = $request->input('events');
        if (! is_array($events)) {
            $events = [$request->only([
                'event_type', 'page_path', 'page_title', 'target_label', 'target_value', 'duration_seconds', 'meta',
            ])];
        }

        $pageViewId = null;
        $sessionId = $request->hasSession() ? $request->session()->getId() : null;
        $ip = $request->ip();
        $ua = mb_substr((string) $request->userAgent(), 0, 255);

        foreach (array_slice($events, 0, 25) as $ev) {
            if (! is_array($ev)) {
                continue;
            }
            $type = (string) ($ev['event_type'] ?? '');
            if (! in_array($type, $allowedTypes, true)) {
                continue;
            }
            $path = '/' . ltrim((string) ($ev['page_path'] ?? '/'), '/');
            $log = UserActivityLog::create([
                'user_id' => $user->id,
                'user_role' => (string) $user->role,
                'session_id' => $sessionId,
                'event_type' => $type,
                'page_path' => mb_substr($path, 0, 255),
                'page_title' => isset($ev['page_title']) ? mb_substr((string) $ev['page_title'], 0, 160) : null,
                'target_label' => isset($ev['target_label']) ? mb_substr((string) $ev['target_label'], 0, 255) : null,
                'target_value' => isset($ev['target_value']) ? mb_substr((string) $ev['target_value'], 0, 255) : null,
                'method' => 'GET',
                'duration_seconds' => max(0, min(86400, (int) ($ev['duration_seconds'] ?? 0))),
                'ip_address' => $ip,
                'user_agent' => $ua,
                'meta' => is_array($ev['meta'] ?? null) ? $ev['meta'] : null,
            ]);

            if ($type === 'page_view' && $pageViewId === null) {
                $pageViewId = $log->id;
            }
        }

        return response()->json([
            'ok' => true,
            'log_id' => $pageViewId,
        ]);
    }

    public function trackDuration(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || $user->isAdmin()) {
            return response()->json(['ok' => true, 'skipped' => true]);
        }

        $payload = $request->all();
        if (empty($payload)) {
            $raw = $request->getContent();
            if ($raw) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }
        }

        $logId = (int) ($payload['log_id'] ?? 0);
        $duration = max(0, min(86400, (int) ($payload['duration_seconds'] ?? 0)));
        $wasteDuration = max(0, min($duration, (int) ($payload['waste_seconds'] ?? 0)));

        if ($logId > 0 && ($duration > 0 || $wasteDuration > 0)) {
            $log = UserActivityLog::query()
                ->where('id', $logId)
                ->where('user_id', $user->id)
                ->first();

            if ($log) {
                $oldWaste = (int) $log->waste_seconds;
                $updates = [];
                if ($duration > $log->duration_seconds) {
                    $updates['duration_seconds'] = $duration;
                }
                if ($wasteDuration > $oldWaste) {
                    $updates['waste_seconds'] = $wasteDuration;
                }
                if (! empty($updates)) {
                    $log->update($updates);
                }

                // Sync incremental waste seconds into active work session (only when NOT on break)
                $wasteDelta = max(0, $wasteDuration - $oldWaste);
                if ($wasteDelta > 0) {
                    $openSession = UserWorkSession::query()
                        ->where('user_id', $user->id)
                        ->where('status', 'active')
                        ->latest('id')
                        ->first();

                    if ($openSession) {
                        $openSession->total_waste_seconds += $wasteDelta;
                        $openSession->total_seconds = $openSession->computedTotalSeconds();
                        $openSession->desk_seconds = $openSession->computedDeskSeconds();
                        $openSession->save();
                    }
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    public function workSessionStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || $user->isAdmin()) {
            return response()->json(['ok' => true, 'skipped' => true]);
        }

        $session = UserWorkSession::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'on_break'])
            ->latest('id')
            ->first();

        return response()->json([
            'ok' => true,
            'session' => $this->formatSessionPayload($session),
        ]);
    }

    public function workSessionStart(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || $user->isAdmin()) {
            return response()->json(['ok' => true, 'skipped' => true]);
        }

        $session = UserWorkSession::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'on_break'])
            ->latest('id')
            ->first();

        if (! $session) {
            $session = UserWorkSession::create([
                'user_id' => $user->id,
                'user_role' => (string) $user->role,
                'started_at' => now(),
                'status' => 'active',
            ]);
        }

        return response()->json([
            'ok' => true,
            'session' => $this->formatSessionPayload($session),
        ]);
    }

    public function workSessionBreakOn(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || $user->isAdmin()) {
            return response()->json(['ok' => true, 'skipped' => true]);
        }

        $session = UserWorkSession::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'on_break'])
            ->latest('id')
            ->first();

        if ($session && $session->status === 'active') {
            $session->status = 'on_break';
            $session->break_started_at = now();
            $session->total_seconds = $session->computedTotalSeconds();
            $session->desk_seconds = $session->computedDeskSeconds();
            $session->save();
        }

        return response()->json([
            'ok' => true,
            'session' => $this->formatSessionPayload($session),
        ]);
    }

    public function workSessionBreakOff(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || $user->isAdmin()) {
            return response()->json(['ok' => true, 'skipped' => true]);
        }

        $session = UserWorkSession::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'on_break'])
            ->latest('id')
            ->first();

        if ($session && $session->status === 'on_break') {
            if ($session->break_started_at) {
                $session->total_break_seconds += max(0, now()->getTimestamp() - $session->break_started_at->getTimestamp());
            }
            $session->break_started_at = null;
            $session->status = 'active';
            $session->total_seconds = $session->computedTotalSeconds();
            $session->desk_seconds = $session->computedDeskSeconds();
            $session->save();
        }

        return response()->json([
            'ok' => true,
            'session' => $this->formatSessionPayload($session),
        ]);
    }

    public function workSessionEnd(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || $user->isAdmin()) {
            return response()->json(['ok' => true, 'skipped' => true]);
        }

        $session = UserWorkSession::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'on_break'])
            ->latest('id')
            ->first();

        if ($session) {
            if ($session->status === 'on_break' && $session->break_started_at) {
                $session->total_break_seconds += max(0, now()->getTimestamp() - $session->break_started_at->getTimestamp());
            }
            $session->break_started_at = null;
            $session->ended_at = now();
            $session->status = 'ended';
            $session->total_seconds = max(0, $session->ended_at->getTimestamp() - $session->started_at->getTimestamp());
            $session->desk_seconds = max(
                0,
                $session->total_seconds - (int) $session->total_break_seconds - (int) $session->total_waste_seconds
            );
            $session->save();
        }

        return response()->json([
            'ok' => true,
            'session' => null,
            'last_session' => $this->formatSessionPayload($session),
        ]);
    }

    public static function formatSessionPayload(?UserWorkSession $session): ?array
    {
        if (! $session) {
            return null;
        }

        return [
            'id' => $session->id,
            'status' => $session->status,
            'started_at_ts' => $session->started_at?->timestamp,
            'ended_at_ts' => $session->ended_at?->timestamp,
            'break_started_at_ts' => $session->break_started_at?->timestamp,
            'total_break_seconds' => $session->computedBreakSeconds(),
            'total_waste_seconds' => (int) $session->total_waste_seconds,
            'total_seconds' => $session->computedTotalSeconds(),
            'desk_seconds' => $session->computedDeskSeconds(),
        ];
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        $period = in_array($request->string('period')->toString(), ['day', 'week', 'month', 'year'], true)
            ? $request->string('period')->toString()
            : 'day';

        try {
            $refDate = $request->filled('date')
                ? Carbon::parse($request->string('date')->toString())
                : now();
        } catch (\Throwable) {
            $refDate = now();
        }

        $selectedUserId = $request->integer('user_id') ?: null;
        $selectedRole = trim($request->string('role')->toString());

        $nonAdminUsers = User::query()
            ->where('role', '!=', User::ROLE_ADMIN)
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'role']);

        $roles = $nonAdminUsers->pluck('role')
            ->merge(UserActivityLog::query()->distinct()->pluck('user_role'))
            ->filter(fn ($r) => $r && strtolower((string) $r) !== User::ROLE_ADMIN)
            ->unique()
            ->sort()
            ->values();

        // Compute range & timeline buckets based on period
        [$start, $end, $periodLabel, $prevDate, $nextDate, $buckets] = $this->resolvePeriodRange($period, $refDate);

        $baseQuery = UserActivityLog::query()
            ->whereBetween('created_at', [$start, $end])
            ->where('user_role', '!=', User::ROLE_ADMIN)
            ->when($selectedUserId, fn ($q) => $q->where('user_id', $selectedUserId))
            ->when($selectedRole !== '', fn ($q) => $q->where('user_role', $selectedRole));

        // KPI Totals
        $totalTimeSeconds = (int) (clone $baseQuery)->where('event_type', 'page_view')->sum('duration_seconds');
        $totalWasteSeconds = (int) (clone $baseQuery)->where('event_type', 'page_view')->sum('waste_seconds');
        $eventCountsRaw = (clone $baseQuery)
            ->select('event_type', DB::raw('COUNT(*) as cnt'))
            ->groupBy('event_type')
            ->pluck('cnt', 'event_type')
            ->all();

        $totalPageViews = (int) ($eventCountsRaw['page_view'] ?? 0);
        $totalClicks = (int) ($eventCountsRaw['click'] ?? 0);
        $totalSelects = (int) ($eventCountsRaw['select'] ?? 0);
        $totalSubmits = (int) (($eventCountsRaw['submit'] ?? 0) + ($eventCountsRaw['api'] ?? 0));

        // Timeline Chart Data
        $timelineRows = (clone $baseQuery)
            ->get(['event_type', 'duration_seconds', 'waste_seconds', 'created_at']);

        $bucketActions = array_fill_keys(array_keys($buckets), 0);
        $bucketSeconds = array_fill_keys(array_keys($buckets), 0);
        $bucketWasteSeconds = array_fill_keys(array_keys($buckets), 0);

        foreach ($timelineRows as $row) {
            $key = $this->bucketKeyFor($period, $row->created_at);
            if (array_key_exists($key, $bucketActions)) {
                $bucketActions[$key]++;
                if ($row->event_type === 'page_view') {
                    $bucketSeconds[$key] += (int) $row->duration_seconds;
                    $bucketWasteSeconds[$key] += (int) $row->waste_seconds;
                }
            }
        }

        $timelineLabels = array_values($buckets);
        $timelineActions = array_values($bucketActions);
        $timelineMinutes = array_map(fn ($s) => round($s / 60, 2), array_values($bucketSeconds));
        $timelineWasteMinutes = array_map(fn ($s) => round($s / 60, 2), array_values($bucketWasteSeconds));

        // Per-Page Stats
        $pageStats = (clone $baseQuery)
            ->where('event_type', 'page_view')
            ->select(
                'page_path',
                DB::raw('MAX(page_title) as page_title'),
                DB::raw('COUNT(*) as visits'),
                DB::raw('SUM(duration_seconds) as total_seconds'),
                DB::raw('SUM(waste_seconds) as waste_seconds')
            )
            ->groupBy('page_path')
            ->orderByDesc('total_seconds')
            ->orderByDesc('visits')
            ->limit(15)
            ->get();

        $pageChartLabels = $pageStats->take(8)->pluck('page_path')->values()->all();
        $pageChartMinutes = $pageStats->take(8)->map(fn ($p) => round(((int) $p->total_seconds) / 60, 2))->values()->all();

        // Top Clicked Options & Selected Values
        $actionStats = (clone $baseQuery)
            ->whereIn('event_type', ['click', 'select'])
            ->whereNotNull('target_label')
            ->select(
                'event_type',
                'target_label',
                'target_value',
                'page_path',
                DB::raw('COUNT(*) as total_count'),
                DB::raw('MAX(created_at) as last_at')
            )
            ->groupBy('event_type', 'target_label', 'target_value', 'page_path')
            ->orderByDesc('total_count')
            ->limit(25)
            ->get();

        // Per-User Summary
        $userStatsRaw = (clone $baseQuery)
            ->select(
                'user_id',
                DB::raw("SUM(CASE WHEN event_type = 'page_view' THEN 1 ELSE 0 END) as page_views"),
                DB::raw("SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks"),
                DB::raw("SUM(CASE WHEN event_type = 'select' THEN 1 ELSE 0 END) as selects"),
                DB::raw("SUM(CASE WHEN event_type IN ('submit', 'api') THEN 1 ELSE 0 END) as submits"),
                DB::raw("SUM(CASE WHEN event_type = 'page_view' THEN duration_seconds ELSE 0 END) as total_seconds"),
                DB::raw("SUM(CASE WHEN event_type = 'page_view' THEN waste_seconds ELSE 0 END) as waste_seconds")
            )
            ->groupBy('user_id')
            ->orderByDesc('total_seconds')
            ->get()
            ->keyBy('user_id');

        // Work Sessions (Start -> End, Break, Waste, Desk Time)
        $sessionsQuery = UserWorkSession::query()
            ->whereBetween('started_at', [$start, $end])
            ->where('user_role', '!=', User::ROLE_ADMIN)
            ->when($selectedUserId, fn ($q) => $q->where('user_id', $selectedUserId))
            ->when($selectedRole !== '', fn ($q) => $q->where('user_role', $selectedRole));

        $workSessions = (clone $sessionsQuery)
            ->with('user:id,name,username,role')
            ->latest('started_at')
            ->limit(30)
            ->get();

        $allSessionsForRange = (clone $sessionsQuery)->get();
        $totalBreakSeconds = 0;
        $totalDeskSeconds = 0;
        $userSessionAgg = [];
        foreach ($allSessionsForRange as $ws) {
            $b = $ws->computedBreakSeconds();
            $d = $ws->computedDeskSeconds();
            $totalBreakSeconds += $b;
            $totalDeskSeconds += $d;
            if (! isset($userSessionAgg[$ws->user_id])) {
                $userSessionAgg[$ws->user_id] = ['break_seconds' => 0, 'desk_seconds' => 0];
            }
            $userSessionAgg[$ws->user_id]['break_seconds'] += $b;
            $userSessionAgg[$ws->user_id]['desk_seconds'] += $d;
        }

        $usersMap = $nonAdminUsers->keyBy('id');
        $userStats = $userStatsRaw->map(function ($row) use ($usersMap, $userSessionAgg) {
            $u = $usersMap->get($row->user_id);
            $sessAgg = $userSessionAgg[$row->user_id] ?? ['break_seconds' => 0, 'desk_seconds' => 0];
            return [
                'user_id' => $row->user_id,
                'name' => $u?->name ?? ('User #' . $row->user_id),
                'role' => $u ? User::roleLabelFor($u->role) : 'User',
                'page_views' => (int) $row->page_views,
                'clicks' => (int) $row->clicks,
                'selects' => (int) $row->selects,
                'submits' => (int) $row->submits,
                'total_seconds' => (int) $row->total_seconds,
                'waste_seconds' => (int) $row->waste_seconds,
                'break_seconds' => $sessAgg['break_seconds'],
                'desk_seconds' => $sessAgg['desk_seconds'],
            ];
        })->values();

        // Paginated Detailed Activity Log
        $logs = (clone $baseQuery)
            ->with('user:id,name,username,role')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $selectedUser = $selectedUserId ? $nonAdminUsers->firstWhere('id', $selectedUserId) : null;

        return view('analytics.index', compact(
            'period', 'refDate', 'periodLabel', 'prevDate', 'nextDate',
            'nonAdminUsers', 'roles', 'selectedUserId', 'selectedRole', 'selectedUser',
            'totalTimeSeconds', 'totalWasteSeconds', 'totalBreakSeconds', 'totalDeskSeconds',
            'totalPageViews', 'totalClicks', 'totalSelects', 'totalSubmits',
            'timelineLabels', 'timelineActions', 'timelineMinutes', 'timelineWasteMinutes',
            'pageStats', 'pageChartLabels', 'pageChartMinutes',
            'actionStats', 'userStats', 'workSessions', 'logs'
        ));
    }

    private function resolvePeriodRange(string $period, Carbon $ref): array
    {
        $buckets = [];

        if ($period === 'day') {
            $start = $ref->copy()->startOfDay();
            $end = $ref->copy()->endOfDay();
            $label = $ref->format('d M Y (D)');
            $prevDate = $ref->copy()->subDay()->toDateString();
            $nextDate = $ref->copy()->addDay()->toDateString();
            for ($h = 0; $h < 24; $h++) {
                $k = str_pad((string) $h, 2, '0', STR_PAD_LEFT);
                $buckets[$k] = $k . ':00';
            }
        } elseif ($period === 'week') {
            $start = $ref->copy()->startOfWeek();
            $end = $ref->copy()->endOfWeek();
            $label = $start->format('d M') . ' – ' . $end->format('d M Y');
            $prevDate = $ref->copy()->subWeek()->toDateString();
            $nextDate = $ref->copy()->addWeek()->toDateString();
            $cur = $start->copy();
            for ($i = 0; $i < 7; $i++) {
                $buckets[$cur->toDateString()] = $cur->format('D d');
                $cur->addDay();
            }
        } elseif ($period === 'month') {
            $start = $ref->copy()->startOfMonth();
            $end = $ref->copy()->endOfMonth();
            $label = $ref->format('F Y');
            $prevDate = $ref->copy()->subMonth()->toDateString();
            $nextDate = $ref->copy()->addMonth()->toDateString();
            $days = $start->daysInMonth;
            for ($d = 1; $d <= $days; $d++) {
                $k = str_pad((string) $d, 2, '0', STR_PAD_LEFT);
                $buckets[$k] = (string) $d;
            }
        } else {
            $start = $ref->copy()->startOfYear();
            $end = $ref->copy()->endOfYear();
            $label = $ref->format('Y');
            $prevDate = $ref->copy()->subYear()->toDateString();
            $nextDate = $ref->copy()->addYear()->toDateString();
            for ($m = 1; $m <= 12; $m++) {
                $k = str_pad((string) $m, 2, '0', STR_PAD_LEFT);
                $buckets[$k] = Carbon::create($ref->year, $m, 1)->format('M');
            }
        }

        return [$start, $end, $label, $prevDate, $nextDate, $buckets];
    }

    private function bucketKeyFor(string $period, Carbon $dt): string
    {
        return match ($period) {
            'day' => $dt->format('H'),
            'week' => $dt->toDateString(),
            'month' => $dt->format('d'),
            default => $dt->format('m'),
        };
    }
}
