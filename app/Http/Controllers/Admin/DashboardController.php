<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Refund;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        return view('Admin.index', self::getDashboardData());
    }

    public static function getDashboardData(): array
    {
        $paidStatuses = ['paid', 'finished', 'confirmed', 'success'];
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();
        $startOfLastMonth = now()->subMonthNoOverflow()->startOfMonth();
        $endOfLastMonth = now()->subMonthNoOverflow()->endOfMonth();

        $totalRevenue = (float) PaymentTransaction::whereIn('status', $paidStatuses)->sum('original_amount');
        $totalRefunds = (float) Refund::where('status', Refund::STATUS_REFUNDED)->sum('amount');
        $netRevenue = max(0.0, $totalRevenue - $totalRefunds);

        $thisMonthRevenue = (float) PaymentTransaction::whereIn('status', $paidStatuses)
            ->whereBetween('paid_at', [$startOfMonth, $endOfMonth])
            ->sum('original_amount');

        $lastMonthRevenue = (float) PaymentTransaction::whereIn('status', $paidStatuses)
            ->whereBetween('paid_at', [$startOfLastMonth, $endOfLastMonth])
            ->sum('original_amount');

        $revenueDiff = $thisMonthRevenue - $lastMonthRevenue;

        // Real payment methods breakdown from orders
        $methodNames = PaymentMethod::pluck('name', 'id')->toArray();
        $staticLabels = [
            'wallet' => 'المحفظة',
            'cash' => 'كاش',
            'credit_card' => 'بطاقة بنكية',
            'bank_transfer' => 'تحويل بنكي',
        ];
        $barColors = ['bg-primary', 'bg-info', 'bg-success', 'bg-warning', 'bg-secondary'];

        $rawMethods = Order::select('payment_method', DB::raw('COUNT(*) as total'))
            ->whereNotNull('payment_method')
            ->where('payment_method', '!=', '')
            ->groupBy('payment_method')
            ->orderByDesc('total')
            ->get();

        $totalMethodOrders = (int) $rawMethods->sum('total');
        $paymentMethodsBreakdown = [];
        foreach ($rawMethods->take(5) as $idx => $row) {
            $key = (string) $row->payment_method;
            $label = $staticLabels[$key] ?? ($methodNames[(int) $key] ?? $key);
            $percent = $totalMethodOrders > 0 ? round(($row->total / $totalMethodOrders) * 100, 2) : 0;
            $paymentMethodsBreakdown[] = [
                'label' => $label,
                'count' => (int) $row->total,
                'percent' => $percent,
                'color' => $barColors[$idx % count($barColors)],
            ];
        }

        // Top customers by order count
        $topCustomers = Order::select('user_id', DB::raw('COUNT(*) as orders_count'))
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderByDesc('orders_count')
            ->with('user')
            ->limit(5)
            ->get();

        $baseCurrency = (string) config('commerce.base_currency', 'EGP');

        return [
            'stats' => [
                'monthly_orders' => Order::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count(),
                'total_orders' => Order::count(),
                'cancelled_orders' => Order::where('status', 'cancelled')->count(),
                'total_users' => User::count(),
                'monthly_users' => User::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count(),
                'total_admins' => Admin::count(),
                'total_visitors' => Visitor::count(),
                'monthly_visitors' => Visitor::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count(),
                'total_products' => Product::count(),
                'monthly_products' => Product::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count(),
                'total_categories' => Category::count(),
                'total_revenue' => $totalRevenue,
                'net_revenue' => $netRevenue,
                'this_month_revenue' => $thisMonthRevenue,
                'revenue_diff' => $revenueDiff,
                'base_currency' => $baseCurrency,
            ],
            'paymentMethodsBreakdown' => $paymentMethodsBreakdown,
            'topCustomers' => $topCustomers,
            'chartYears' => range((int) now()->year, (int) now()->year - 4),
        ];
    }
}
