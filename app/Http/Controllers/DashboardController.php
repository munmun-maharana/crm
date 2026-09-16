<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $today = Carbon::today();

        $stats = [
            'total' => Customer::count(),
            'interested' => Customer::where('status', 'interested')->count(),
            'requests' => Customer::where('status', 'request')->count(),
            'converted' => Customer::where('status', 'converted')->count(),
            'lost' => Customer::where('status', 'lost')->count(),
            'today' => Customer::whereDate('created_at', $today)->count(),
        ];

        $dayLabels = [];
        $dayData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = $today->copy()->subDays($i);
            $dayLabels[] = $date->format('D');
            $dayData[] = Customer::whereDate('created_at', $date)->count();
        }

        $monthLabels = [];
        $monthData = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = $today->copy()->subMonths($i);
            $monthLabels[] = $date->format('M');
            $monthData[] = Customer::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
        }

        $yearLabels = [];
        $yearData = [];
        for ($i = 4; $i >= 0; $i--) {
            $year = $today->year - $i;
            $yearLabels[] = (string) $year;
            $yearData[] = Customer::whereYear('created_at', $year)->count();
        }

        $statusCounts = Customer::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $falloffLabels = ['Interested', 'Contacted', 'Converted', 'Lost'];
        $falloffData = [
            (int) ($statusCounts['interested'] ?? 0),
            (int) ($statusCounts['contacted'] ?? 0),
            (int) ($statusCounts['converted'] ?? 0),
            (int) ($statusCounts['lost'] ?? 0),
        ];

        $recent = Customer::with('assignee')->latest()->take(8)->get();

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
            'recent'
        ));
    }
}
