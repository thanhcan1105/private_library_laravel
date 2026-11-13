<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    public function index(): View
    {
        $now = now();
        $dailyReads = DB::table('reading_sessions')
            ->where('started_at', '>=', $now->copy()->subDays(29)->startOfDay())
            ->where('started_at', '<=', $now)
            ->selectRaw('DATE(started_at) as period, COUNT(*) as read_count')
            ->groupByRaw('DATE(started_at)')
            ->orderBy('period')
            ->get();
        $monthlyReads = DB::table('reading_sessions')
            ->where('started_at', '>=', $now->copy()->subMonths(11)->startOfMonth())
            ->where('started_at', '<=', $now)
            ->selectRaw("DATE_FORMAT(started_at, '%Y-%m') as period, COUNT(*) as read_count")
            ->groupByRaw("DATE_FORMAT(started_at, '%Y-%m')")
            ->orderBy('period')
            ->get();
        $yearlyReads = DB::table('reading_sessions')
            ->where('started_at', '>=', $now->copy()->subYears(4)->startOfYear())
            ->where('started_at', '<=', $now)
            ->selectRaw('YEAR(started_at) as period, COUNT(*) as read_count')
            ->groupByRaw('YEAR(started_at)')
            ->orderBy('period')
            ->get();

        return view('admin.statistics.index', compact('dailyReads', 'monthlyReads', 'yearlyReads'));
    }
}
