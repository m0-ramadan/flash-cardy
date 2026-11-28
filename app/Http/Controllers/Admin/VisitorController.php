<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Visitor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VisitorController extends Controller
{
    public function index(Request $request)
    {
        $range = $request->get('range', '30');
        $allowedRanges = ['7', '30', '90', '365', 'all'];
        if (!in_array($range, $allowedRanges, true)) {
            $range = '30';
        }

        $fromDate = $range === 'all' ? null : now()->subDays((int) $range)->startOfDay();

        $baseQuery = Visitor::query();
        if ($fromDate) {
            $baseQuery->where('created_at', '>=', $fromDate);
        }

        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $periodTotal = (clone $baseQuery)->count();

        $stats = [
            'total' => Visitor::count(),
            'today' => Visitor::whereDate('created_at', today())->count(),
            'month' => Visitor::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count(),
            'period' => $periodTotal,
        ];

        // Daily visits trend for chart (max last 30/90 days or grouped by month for 365/all)
        if (in_array($range, ['7', '30', '90'], true)) {
            $dailyRaw = (clone $baseQuery)
                ->selectRaw('DATE(created_at) as label, COUNT(*) as total')
                ->groupByRaw('DATE(created_at)')
                ->orderBy('label')
                ->get();
        } else {
            $dailyRaw = (clone $baseQuery)
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as label, COUNT(*) as total")
                ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
                ->orderBy('label')
                ->get();
        }

        $chartLabels = $dailyRaw->pluck('label')->values();
        $chartCounts = $dailyRaw->pluck('total')->map(fn ($v) => (int) $v)->values();

        // Device breakdown
        $devicesRaw = (clone $baseQuery)
            ->selectRaw('
                SUM(CASE WHEN is_bot = 1 THEN 1 ELSE 0 END) as bots,
                SUM(CASE WHEN is_bot = 0 AND is_mobile = 1 THEN 1 ELSE 0 END) as mobile,
                SUM(CASE WHEN is_bot = 0 AND is_tablet = 1 THEN 1 ELSE 0 END) as tablet,
                SUM(CASE WHEN is_bot = 0 AND is_mobile = 0 AND is_tablet = 0 THEN 1 ELSE 0 END) as desktop
            ')
            ->first();

        $deviceBreakdown = [
            ['label' => 'كمبيوتر (Desktop)', 'count' => (int) ($devicesRaw->desktop ?? 0), 'color' => 'bg-success', 'icon' => 'ti-device-desktop'],
            ['label' => 'موبايل (Mobile)', 'count' => (int) ($devicesRaw->mobile ?? 0), 'color' => 'bg-info', 'icon' => 'ti-device-mobile'],
            ['label' => 'تابلت (Tablet)', 'count' => (int) ($devicesRaw->tablet ?? 0), 'color' => 'bg-secondary', 'icon' => 'ti-device-tablet'],
            ['label' => 'بوتات (Bots)', 'count' => (int) ($devicesRaw->bots ?? 0), 'color' => 'bg-warning', 'icon' => 'ti-robot'],
        ];
        foreach ($deviceBreakdown as &$d) {
            $d['percent'] = $periodTotal > 0 ? round(($d['count'] / $periodTotal) * 100, 1) : 0;
        }
        unset($d);

        // Top countries
        $topCountries = (clone $baseQuery)
            ->select('country', DB::raw('COUNT(*) as total'))
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->groupBy('country')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Top pages
        $topPages = (clone $baseQuery)
            ->select('path', DB::raw('COUNT(*) as total'))
            ->whereNotNull('path')
            ->where('path', '!=', '')
            ->groupBy('path')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Top browsers
        $topBrowsers = (clone $baseQuery)
            ->select('browser', DB::raw('COUNT(*) as total'))
            ->whereNotNull('browser')
            ->where('browser', '!=', '')
            ->groupBy('browser')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        // Top platforms
        $topPlatforms = (clone $baseQuery)
            ->select('platform', DB::raw('COUNT(*) as total'))
            ->whereNotNull('platform')
            ->where('platform', '!=', '')
            ->groupBy('platform')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        return view('Admin.visitors.index', compact(
            'range',
            'stats',
            'chartLabels',
            'chartCounts',
            'deviceBreakdown',
            'topCountries',
            'topPages',
            'topBrowsers',
            'topPlatforms'
        ));
    }

    public function chartData(Request $request)
    {
        $year = $request->get('year', Carbon::now()->year);

        $data = Visitor::selectRaw('country, COUNT(*) as total')
            ->whereYear('created_at', $year)
            ->groupBy('country')
            ->orderByDesc('total')
            ->take(10)
            ->get();

        return response()->json([
            'countries' => $data->pluck('country'),
            'count' => $data->pluck('total'),
        ]);
    }

    public function ordersStats($year)
    {
        $data = Order::selectRaw('MONTH(created_at) as month, COUNT(*) as total')
            ->whereYear('created_at', $year)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $months = range(1, 12);
        $result = [];

        foreach ($months as $m) {
            $result[] = [
                'month' => date('M', mktime(0, 0, 0, $m, 1)),
                'total' => (int) ($data->firstWhere('month', $m)->total ?? 0),
            ];
        }

        return response()->json($result);
    }
}
