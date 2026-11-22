<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Refund;
use App\Services\Refunds\RefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RefundController extends Controller
{
    public function __construct(private RefundService $refunds) {}

    public function index(Request $request)
    {
        $query = Refund::with(['order.user','paymentTransaction','requestedByAdmin'])->latest();
        if ($request->filled('status')) $query->where('status',$request->status);
        if ($request->filled('gateway')) $query->where('gateway',$request->gateway);
        if ($request->filled('search')) {
            $s=trim((string)$request->search);
            $query->where(fn($q)=>$q->where('provider_reference','like',"%{$s}%")
                ->orWhereHas('order',fn($o)=>$o->where('order_number','like',"%{$s}%")));
        }
        $refunds=$query->paginate(25)->withQueryString();
        return view('Admin.refunds.index',compact('refunds'));
    }

    public function store(Request $request, Order $order)
    {
        $max=max(0,(float)$order->total_amount-(float)($order->refunded_amount??0));
        $data=$request->validate([
            'amount'=>['required','numeric','min:0.01','max:'.$max],
            'reason'=>'required|string|min:3|max:1000',
            'destination_wallet'=>'sometimes|boolean',
            'items'=>'sometimes|array',
            'items.*.order_item_id'=>'required_with:items|integer|exists:order_items,id',
            'items.*.quantity'=>'required_with:items|integer|min:1',
        ]);
        try {
            $refund=$this->refunds->request($order,(float)$data['amount'],$data['reason'],[
                'admin_id'=>auth('admin')->id(),
                'destination_wallet'=>$request->boolean('destination_wallet'),
                'items'=>$data['items']??null,
                'idempotency_key'=>$request->header('Idempotency-Key') ?: 'admin-refund-'.$order->id.'-'.Str::uuid(),
            ]);
            return back()->with('success','تم إنشاء طلب الاسترداد بحالة: '.$refund->status);
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['refund'=>$e->getMessage()]);
        }
    }

    public function retry(Refund $refund)
    {
        try {
            if (!in_array($refund->status,[Refund::STATUS_FAILED,Refund::STATUS_REQUESTED],true)) {
                return back()->withErrors(['refund'=>'لا يمكن إعادة محاولة هذه العملية في حالتها الحالية.']);
            }
            $refund=$this->refunds->process($refund);
            return back()->with('success','تمت إعادة محاولة الاسترداد: '.$refund->status);
        } catch (\Throwable $e) {
            report($e); return back()->withErrors(['refund'=>$e->getMessage()]);
        }
    }
}
