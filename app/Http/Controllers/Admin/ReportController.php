<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CouponUsage;
use App\Models\Like4AppPurchase;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Refund;
use App\Models\User;
use App\Models\Wallet\UserWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $from=$request->date('from')?->startOfDay() ?? now()->subDays(29)->startOfDay();
        $to=$request->date('to')?->endOfDay() ?? now()->endOfDay();

        $paidTx=PaymentTransaction::query()->whereIn('status',['paid','finished','confirmed','success'])->whereBetween('paid_at',[$from,$to]);
        $revenue=(float)(clone $paidTx)->sum('original_amount');
        $paidOrders=(clone $paidTx)->distinct('order_id')->count('order_id');

        $stats=[
            'revenue'=>$revenue,
            'paid_orders'=>$paidOrders,
            'all_orders'=>Order::whereBetween('created_at',[$from,$to])->count(),
            'unpaid_orders'=>Order::whereBetween('created_at',[$from,$to])->whereNotIn('status_payment',['paid','partially_refunded','refunded'])->count(),
            'refund_total'=>(float)Refund::where('status',Refund::STATUS_REFUNDED)->whereBetween('processed_at',[$from,$to])->sum('amount'),
            'refund_count'=>Refund::whereBetween('created_at',[$from,$to])->count(),
            'customers'=>User::whereBetween('created_at',[$from,$to])->count(),
            'wallet_balance'=>(float)UserWallet::sum('balance'),
            'wallet_held'=>(float)UserWallet::sum('held_balance'),
            'coupon_uses'=>class_exists(CouponUsage::class)?CouponUsage::whereBetween('created_at',[$from,$to])->count():0,
            'like4app_failures'=>Like4AppPurchase::where('status',Like4AppPurchase::STATUS_FAILED)->whereBetween('updated_at',[$from,$to])->count(),
            'stock_alerts'=>Product::where('track_stock',true)->whereRaw('(stock - COALESCE(reserved_stock,0)) <= 5')->count(),
        ];

        $gateways=PaymentTransaction::select('gateway','status',DB::raw('COUNT(*) total'))
            ->whereBetween('created_at',[$from,$to])->groupBy('gateway','status')->orderBy('gateway')->get();
        $salesByDay=PaymentTransaction::selectRaw('DATE(paid_at) day, SUM(original_amount) revenue, COUNT(DISTINCT order_id) orders')
            ->whereIn('status',['paid','finished','confirmed','success'])->whereBetween('paid_at',[$from,$to])->groupByRaw('DATE(paid_at)')->orderBy('day')->get();
        $topProducts=DB::table('order_items')->join('orders','orders.id','=','order_items.order_id')->join('products','products.id','=','order_items.product_id')
            ->whereIn('orders.status_payment',['paid','partially_refunded','refunded'])->whereBetween('orders.paid_at',[$from,$to])
            ->select('products.id','products.name',DB::raw('SUM(order_items.quantity) quantity'),DB::raw('SUM(order_items.total_price) revenue'))
            ->groupBy('products.id','products.name')->orderByDesc('revenue')->limit(20)->get();
        $topCategories=DB::table('order_items')->join('orders','orders.id','=','order_items.order_id')->join('products','products.id','=','order_items.product_id')->leftJoin('categories','categories.id','=','products.category_id')
            ->whereIn('orders.status_payment',['paid','partially_refunded','refunded'])->whereBetween('orders.paid_at',[$from,$to])
            ->select('categories.id','categories.name',DB::raw('SUM(order_items.quantity) quantity'),DB::raw('SUM(order_items.total_price) revenue'))
            ->groupBy('categories.id','categories.name')->orderByDesc('revenue')->limit(20)->get();

        return view('Admin.reports.index',compact('stats','gateways','salesByDay','topProducts','topCategories','from','to'));
    }
}
