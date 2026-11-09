<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsPayload;
use App\Http\Controllers\Controller;
use App\Models\CrmField;
use App\Models\Customer;
use App\Models\WhatsappSetting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Mirrors App\Http\Controllers\DashboardController (web) as JSON. */
class DashboardController extends Controller
{
    use FormatsPayload;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = Carbon::today();
        $pipeline = $user->isOperation() ? 'operations' : 'crm';
        $leads = fn () => Customer::query()->visibleTo($user)->inPipeline($pipeline);
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

        $field = CrmField::inPipeline($pipeline)->where('key', 'status')->first();
        $statuses = collect($field?->options ?? [])->map(fn ($o) => trim((string) $o))->filter()->values()->all();
        if ($statuses === []) {
            $statuses = ['New', 'Interested', 'Contacted', 'Converted', 'Lost', 'Request'];
        }
        $configured = array_map($normalize, $statuses);
        foreach ($rawStatusCounts as $dbStatus => $count) {
            $dbNorm = $normalize($dbStatus);
            if ($dbNorm !== '' && ! in_array($dbNorm, $configured, true)) {
                $statuses[] = ucwords(str_replace(['_', '-'], ' ', (string) $dbStatus));
                $configured[] = $dbNorm;
            }
        }

        $tones = ['sky', 'teal', 'amber', 'emerald', 'rose', 'indigo', 'violet', 'orange', 'cyan', 'slate'];
        $cards = [[
            'label' => 'Today Follow up',
            'value' => $leads()->where('follow_up', true)->whereDate('follow_up_at', $today)->count(),
            'tone' => 'orange',
            'filter' => 'follow_up_today',
            'status' => '',
        ]];
        foreach (array_values($statuses) as $i => $status) {
            $cards[] = [
                'label' => ucwords(str_replace(['_', '-'], ' ', (string) $status)),
                'value' => (int) ($normalizedCounts[$normalize($status)] ?? 0),
                'tone' => $tones[$i % count($tones)],
                'filter' => '',
                'status' => (string) $status,
            ];
        }

        $week = ['labels' => [], 'data' => []];
        for ($i = 6; $i >= 0; $i--) {
            $d = $today->copy()->subDays($i);
            $week['labels'][] = $d->format('D');
            $week['data'][] = $leads()->whereDate('created_at', $d)->count();
        }
        $month = ['labels' => [], 'data' => []];
        for ($i = 11; $i >= 0; $i--) {
            $d = $today->copy()->subMonths($i);
            $month['labels'][] = $d->format('M');
            $month['data'][] = $leads()->whereYear('created_at', $d->year)->whereMonth('created_at', $d->month)->count();
        }
        $year = ['labels' => [], 'data' => []];
        for ($i = 4; $i >= 0; $i--) {
            $y = $today->year - $i;
            $year['labels'][] = (string) $y;
            $year['data'][] = $leads()->whereYear('created_at', $y)->count();
        }

        $falloff = [
            'labels' => array_map(fn ($s) => ucwords(str_replace(['_', '-'], ' ', (string) $s)), $statuses),
            'data' => array_map(fn ($s) => (int) ($normalizedCounts[$normalize($s)] ?? 0), $statuses),
            'colors' => array_slice(['#0f766e', '#0284c7', '#059669', '#e11d48', '#7c3aed', '#ea580c', '#0891b2', '#334155', '#db2777', '#ca8a04'], 0, max(1, count($statuses))),
        ];

        $recent = $leads()->with('assignee')->latest()->take(8)->get()->map(fn ($c) => $this->customerRow($c))->values();

        return response()->json([
            'ok' => true,
            'pipeline' => $pipeline,
            'status_cards' => $cards,
            'charts' => ['week' => $week, 'month' => $month, 'year' => $year, 'falloff' => $falloff],
            'recent' => $recent,
            'whatsapp_target_phone' => WhatsappSetting::current()->normalizedPhone(),
        ]);
    }
}

