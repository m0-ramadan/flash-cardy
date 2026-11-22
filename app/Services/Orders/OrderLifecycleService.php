<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderLog;
use App\Services\Inventory\InventoryService;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\DB;

class OrderLifecycleService
{
    public function __construct(private InventoryService $inventory, private NotificationService $notifications) {}

    public function transition(Order $order, string $target, ?int $adminId = null, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $target, $adminId, $note) {
            $locked=Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $current=(string)$locked->status;
            if($target==='refunded') throw new \RuntimeException('الاسترداد عملية مالية مستقلة؛ استخدم شاشة Refunds.');
            if(in_array($current,['delivered','completed','cancelled'],true) && $target!==$current) throw new \RuntimeException('لا يمكن تغيير طلب في حالة نهائية.');

            $allowed=[
                'pending'=>['pending','processing','cancelled'],
                'processing'=>['processing','shipped','delivered','completed','cancelled'],
                'delivered'=>['delivered'], 'completed'=>['completed'], 'cancelled'=>['cancelled'],
            ];
            if(!in_array($target,$allowed[$current]??[$current],true)) throw new \RuntimeException("انتقال غير مسموح من {$current} إلى {$target}");

            if($target==='cancelled'){
                if(in_array($locked->status_payment,['paid','partially_refunded'],true)) throw new \RuntimeException('الطلب المدفوع يجب استرداده مالياً قبل الإلغاء.');
                $this->inventory->releaseOrder($locked,'admin_cancelled');
                app(\App\Services\Fulfillment\LocalSerialFulfillmentService::class)->releaseOrder($locked,'admin_cancelled');
                $locked->status='cancelled'; $locked->fulfillment_status='cancelled';
            } elseif($target==='shipped'){
                $locked->status='processing'; $locked->fulfillment_status='shipped';
                if($locked->isFillable('shipped_at')) $locked->shipped_at=now();
            } elseif($target==='delivered'){
                $locked->status='delivered'; $locked->fulfillment_status='delivered';
                if($locked->isFillable('delivered_at')) $locked->delivered_at=now();
            } elseif($target==='completed'){
                $locked->status='completed'; $locked->fulfillment_status='fulfilled';
            } else {
                $locked->status=$target;
                $locked->fulfillment_status=$target==='processing'?'processing':($locked->fulfillment_status?:'unfulfilled');
            }
            $locked->save();
            OrderLog::create([
                'order_id'=>$locked->id,'action'=>'status_transition','provider'=>$locked->provider,
                'request_data'=>['from'=>$current,'to'=>$target,'note'=>$note],
                'response_data'=>['status'=>$locked->status,'fulfillment_status'=>$locked->fulfillment_status],
                'ip_address'=>request()->ip(),'user_agent'=>request()->userAgent(),'created_by'=>$adminId,
            ]);
            $fresh=$locked->fresh(['user']);
            if($fresh->user) $this->notifications->user($fresh->user,'تحديث حالة الطلب','تم تحديث حالة الطلب '.$fresh->order_number.' إلى '.$target,'order_status',['order_id'=>$fresh->id,'status'=>$fresh->status,'fulfillment_status'=>$fresh->fulfillment_status]);
            return $fresh;
        },3);
    }
}
