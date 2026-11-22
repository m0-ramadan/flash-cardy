<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Services\Fulfillment\LocalSerialFulfillmentService;
use Illuminate\Http\Request;

class OrderSerialController extends Controller
{
    public function __construct(private LocalSerialFulfillmentService $serials) {}

    public function assignAvailable(OrderItem $item, Request $request)
    {
        $item->load('order','product');
        $this->guardPayment($item, $request);
        $result=$this->serials->fulfillItem($item,true);
        $this->serials->syncOrderStatus($item->order->fresh(['items.product']));
        if (!($result['success']??false)) {
            return back()->with('warning','تم تخصيص الأكواد المتاحة، لكن ما زالت هناك أكواد ناقصة. يمكنك إضافتها من نفس الطلب.');
        }
        return back()->with('success','تم تخصيص وإرسال الأكواد المتاحة للعميل.');
    }

    public function addAndAssign(OrderItem $item, Request $request)
    {
        $item->load('order','product');
        $this->guardPayment($item, $request);
        $data=$request->validate([
            'code'=>'required|string|max:1000','pin'=>'nullable|string|max:500','expires_at'=>'nullable|date','notes'=>'nullable|string|max:1000',
        ]);
        $result=$this->serials->addAndAssign($item,$data,auth('admin')->id());
        $this->serials->syncOrderStatus($item->order->fresh(['items.product']));
        return back()->with(($result['success']??false)?'success':'warning',($result['success']??false)?'تمت إضافة الكود وإرساله للطلب.':'تمت إضافة الكود، وما زال الطلب يحتاج أكواد إضافية.');
    }

    private function guardPayment(OrderItem $item, Request $request): void
    {
        if (in_array($item->order->payment_state,['paid','partially_refunded'],true) || $item->order->status_payment==='paid') return;
        $request->validate(['confirm_unpaid'=>'accepted']);
    }
}
