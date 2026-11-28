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
        $query = Visitor::query();

        if ($search = trim((string) $request->get('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('ip', 'like', "%{$search}%")
                    ->orWhere('path', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%")
                    ->orWhere('browser', 'like', "%{$search}%");
            });
        }

        if ($country = trim((string) $request->get('country', ''))) {
            $query->where('country', $country);
        }

        if ($deviceType = trim((string) $request->get('device_type', ''))) {
            match ($deviceType) {
                'mobile' => $query->where('is_mobile', true),
                'tablet' => $query->where('is_tablet', true),
                'desktop' => $query->where('is_desktop', true),
                'bot' => $query->where('is_bot', true),
                default => null,
            };
        }

        if ($from = $request->get('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->get('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $visitors = $query->latest('id')->paginate(25)->withQueryString();

        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $stats = [
            'total' => Visitor::count(),
            'today' => Visitor::whereDate('created_at', today())->count(),
            'month' => Visitor::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count(),
            'unique_month_ips' => Visitor::whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->distinct('ip')
                ->count('ip'),
        ];

        $since30Days = now()->subDays(30);

        $topCountries = Visitor::select('country', DB::raw('COUNT(*) as total'))
            ->where('created_at', '>=', $since30Days)
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->groupBy('country')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $topPages = Visitor::select('path', DB::raw('COUNT(*) as total'))
            ->where('created_at', '>=', $since30Days)
            ->whereNotNull('path')
            ->where('path', '!=', '')
            ->groupBy('path')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $countries = Visitor::select('country')
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->distinct()
            ->orderBy('country')
            ->pluck('country');

        return view('Admin.visitors.index', compact(
            'visitors',
            'stats',
            'topCountries',
            'topPages',
            'countries'
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
