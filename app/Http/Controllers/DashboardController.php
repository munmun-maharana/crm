<?php

namespace App\Http\Controllers;

use App\Models\CrmField;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $today = Carbon::today();
        $pipeline = $request->user()->isOperation() ? 'operations' : 'crm';
        $leads = fn () => Customer::query()->visibleTo($request->user())->inPipeline($pipeline);

        $normalize = fn ($s) => strtolower(trim(str_replace(['_', '-'], ' ', (string) $s)));

        $rawStatusCounts = $leads()
            ->whereNotNull('status')
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $normalizedCounts = [];
        foreach ($rawStatusCounts as $st => $count) {
            $norm = $normalize($st);
            if ($norm !== '') {
                $normalizedCounts[$norm] = ($normalizedCounts[$norm] ?? 0) + (int) $count;
            }
        }

        $statuses = $this->statusOptions($pipeline);

        // Include any active status in DB that might not be in configured options
        $configuredNormalized = array_map($normalize, $statuses);
        foreach ($rawStatusCounts as $dbStatus => $count) {
            $dbNorm = $normalize($dbStatus);
            if ($dbNorm !== '' && !in_array($dbNorm, $configuredNormalized, true)) {
                $statuses[] = ucwords(str_replace(['_', '-'], ' ', (string) $dbStatus));
                $configuredNormalized[] = $dbNorm;
            }
        }

        $listRoute = $pipeline === 'operations' ? 'operations.index' : 'customers.index';

        $tones = ['sky', 'teal', 'amber', 'emerald', 'rose', 'indigo', 'violet', 'orange', 'cyan', 'slate'];
        $statusCards = [
            [
                'label' => 'Today Follow up',
                'value' => $leads()->where('follow_up', true)->whereDate('follow_up_at', $today)->count(),
                'tone' => 'orange',
                'url' => route($listRoute, ['filter' => 'follow_up_today']),
            ],
        ];

        foreach (array_values($statuses) as $i => $status) {
            $norm = $normalize($status);
            $statusCards[] = [
                'label' => ucwords(str_replace(['_', '-'], ' ', (string) $status)),
                'value' => (int) ($normalizedCounts[$norm] ?? 0),
                'tone' => $tones[$i % count($tones)],
                'url' => route($listRoute, ['status' => $status]),
            ];
        }

        $dayLabels = [];
        $dayData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = $today->copy()->subDays($i);
            $dayLabels[] = $date->format('D');
            $dayData[] = $leads()->whereDate('created_at', $date)->count();
        }

        $monthLabels = [];
        $monthData = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = $today->copy()->subMonths($i);
            $monthLabels[] = $date->format('M');
            $monthData[] = $leads()->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
        }

        $yearLabels = [];
        $yearData = [];
        for ($i = 4; $i >= 0; $i--) {
            $year = $today->year - $i;
            $yearLabels[] = (string) $year;
            $yearData[] = $leads()->whereYear('created_at', $year)->count();
        }

        $falloffLabels = array_map(
            fn ($s) => ucwords(str_replace(['_', '-'], ' ', (string) $s)),
            $statuses
        );
        $falloffData = array_map(
            fn ($s) => (int) ($normalizedCounts[$normalize($s)] ?? 0),
            $statuses
        );
        $falloffColors = ['#0f766e', '#0284c7', '#059669', '#e11d48', '#7c3aed', '#ea580c', '#0891b2', '#334155', '#db2777', '#ca8a04'];
        $falloffChartColors = array_values(array_slice($falloffColors, 0, max(1, count($falloffLabels))));

        $recent = $leads()->with('assignee')->latest()->take(8)->get();

        return view('dashboard', compact(
            'statusCards',
            'dayLabels',
            'dayData',
            'monthLabels',
            'monthData',
            'yearLabels',
            'yearData',
            'falloffLabels',
            'falloffData',
            'falloffChartColors',
            'recent',
            'listRoute'
        ));
    }

    private function statusOptions(string $pipeline): array
    {
        $field = CrmField::inPipeline($pipeline === 'operations' ? 'operations' : 'crm')
            ->where('key', 'status')
            ->first();

        $fromField = collect($field?->options ?? [])
            ->map(fn ($o) => trim((string) $o))
            ->filter()
            ->values()
            ->all();

        if ($fromField !== []) {
            return $fromField;
        }

        return ['New', 'Interested', 'Contacted', 'Converted', 'Lost', 'Request'];
    }
}
