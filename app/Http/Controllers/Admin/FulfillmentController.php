<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Like4AppPurchase;
use App\Models\OrderItem;
use App\Services\Fulfillment\Like4AppFulfillmentService;
use Illuminate\Http\Request;

class FulfillmentController extends Controller
{
    public function __construct(private Like4AppFulfillmentService $fulfillment) {}

    public function index(Request $request)
    {
        $query=Like4AppPurchase::with(['order','orderItem.product'])->latest();
        if($request->filled('status')) $query->where('status',$request->status);
        if($request->filled('search')){
            $s=trim((string)$request->search);
            $query->where(fn($q)=>$q->where('stable_reference','like',"%{$s}%")
                ->orWhereHas('order',fn($o)=>$o->where('order_number','like',"%{$s}%")));
        }
        $purchases=$query->paginate(30)->withQueryString();
        return view('Admin.fulfillment.index',compact('purchases'));
    }

    public function retry(OrderItem $item)
    {
        try {
            $result=$this->fulfillment->retryItem($item);
            $ok=(bool)($result['success']??false);
            return back()->with($ok?'success':'warning',$ok?'تمت إعادة محاولة تنفيذ المنتج بنجاح.':'اكتملت المحاولة مع عناصر غير منفذة؛ راجع السجل.');
        } catch (\Throwable $e) {
            report($e); return back()->withErrors(['fulfillment'=>$e->getMessage()]);
        }
    }
}
