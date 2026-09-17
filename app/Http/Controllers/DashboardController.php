<?php

namespace App\Http\Controllers;

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

        $stats = [
            'total' => $leads()->count(),
            'interested' => $leads()->where('status', 'interested')->count(),
            'requests' => $leads()->where('status', 'request')->count(),
            'converted' => $leads()->where('status', 'converted')->count(),
            'lost' => $leads()->where('status', 'lost')->count(),
            'today' => $leads()->whereDate('created_at', $today)->count(),
        ];

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

        $statusCounts = $leads()->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $falloffLabels = ['Interested', 'Contacted', 'Converted', 'Lost'];
        $falloffData = [
            (int) ($statusCounts['interested'] ?? 0),
            (int) ($statusCounts['contacted'] ?? 0),
            (int) ($statusCounts['converted'] ?? 0),
            (int) ($statusCounts['lost'] ?? 0),
        ];

        $recent = $leads()->with('assignee')->latest()->take(8)->get();
        $listRoute = $pipeline === 'operations' ? 'operations.index' : 'customers.index';

        return view('dashboard', compact(
            'stats',
            'dayLabels',
            'dayData',
            'monthLabels',
            'monthData',
            'yearLabels',
            'yearData',
            'falloffLabels',
            'falloffData',
            'recent',
            'listRoute'
        ));
    }
}
