<?php

namespace App\Services\Fulfillment;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderLog;
use App\Models\ProductSerial;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class LocalSerialFulfillmentService
{
    public function addCodes(int $productId, array $rows, ?int $adminId = null, string $source = 'manual'): array
    {
        $created = 0; $duplicates = 0; $invalid = 0;
        $batch = 'SER-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(3));

        DB::transaction(function () use ($productId, $rows, $adminId, $source, $batch, &$created, &$duplicates, &$invalid) {
            foreach ($rows as $row) {
                $code = trim((string) ($row['code'] ?? ''));
                if ($code === '') { $invalid++; continue; }
                $hash = ProductSerial::hashCode($code);
                if (ProductSerial::where('code_hash', $hash)->exists()) { $duplicates++; continue; }
                $expires = $row['expires_at'] ?? null;
                ProductSerial::create([
                    'product_id' => $productId,
                    'code_hash' => $hash,
                    'code' => $code,
                    'pin' => filled($row['pin'] ?? null) ? trim((string)$row['pin']) : null,
                    'expires_at' => $expires ?: null,
                    'status' => ProductSerial::AVAILABLE,
                    'source' => $source,
                    'batch_reference' => $batch,
                    'notes' => $row['notes'] ?? null,
                    'created_by' => $adminId,
                ]);
                $created++;
            }
        }, 3);

        return compact('created','duplicates','invalid','batch');
    }

    public function fulfillOrder(Order $order, bool $includeManual = false): array
    {
        $order->loadMissing('items.product');
        $results = []; $hasLocal = false; $allComplete = true;
        foreach ($order->items as $item) {
            if ($this->sourceFor($item) !== 'local_serials') continue;
            $hasLocal = true;
            $mode = $this->modeFor($item);
            if ($mode === 'manual' && !$includeManual) {
                $this->markWaiting($item, 'awaiting_manual');
                $results[$item->id] = ['success'=>false,'status'=>'awaiting_manual'];
                $allComplete = false;
                continue;
            }
            $result = $this->fulfillItem($item, true);
            $results[$item->id] = $result;
            if (!($result['success'] ?? false)) $allComplete = false;
        }
        return ['has_local_items'=>$hasLocal,'success'=>!$hasLocal || $allComplete,'items'=>$results];
    }

    /** Reserve currently available local codes for an order before payment. */
    public function reserveOrder(Order $order): void
    {
        $order->loadMissing('items.product');
        foreach ($order->items as $item) {
            if ($this->sourceFor($item) === 'local_serials') $this->reserveItem($item);
        }
    }

    public function reserveItem(OrderItem $item): array
    {
        return DB::transaction(function () use ($item) {
            $locked = OrderItem::with('product')->whereKey($item->id)->lockForUpdate()->firstOrFail();
            if ($this->sourceFor($locked) !== 'local_serials') return ['reserved'=>0,'required'=>(int)$locked->quantity];

            $already = ProductSerial::where('order_item_id',$locked->id)
                ->where('status', ProductSerial::ASSIGNED)->lockForUpdate()->count();
            $needed = max(0, (int)$locked->quantity - $already);
            if ($needed > 0) {
                $available = ProductSerial::where('product_id',$locked->product_id)->usable()
                    ->orderByRaw('expires_at IS NULL, expires_at ASC')->orderBy('id')
                    ->lockForUpdate()->limit($needed)->get();
                if (!$locked->product?->allow_serial_backorder && ($already + $available->count()) < (int)$locked->quantity) {
                    throw new \RuntimeException('لا توجد أكواد تسلسلية كافية لهذا المنتج، والطلب بدون كود غير مسموح من إعدادات المنتج.');
                }
                foreach ($available as $serial) {
                    $serial->forceFill([
                        'status'=>ProductSerial::ASSIGNED,'order_id'=>$locked->order_id,'order_item_id'=>$locked->id,
                        'reserved_at'=>now(),'assigned_at'=>now(),
                    ])->save();
                }
            }
            $reserved = ProductSerial::where('order_item_id',$locked->id)->where('status',ProductSerial::ASSIGNED)->count();
            $locked->forceFill(['serial_status'=>$reserved >= (int)$locked->quantity ? 'reserved' : ($reserved ? 'partial_reserved' : 'awaiting_serial')])->save();
            return ['reserved'=>$reserved,'required'=>(int)$locked->quantity];
        },3);
    }

    /** Release only codes that have not been delivered. Issued codes are immutable. */
    public function releaseOrder(Order $order, string $reason = 'payment_released'): int
    {
        return DB::transaction(function () use ($order, $reason) {
            $rows = ProductSerial::where('order_id',$order->id)->where('status',ProductSerial::ASSIGNED)->lockForUpdate()->get();
            foreach ($rows as $serial) {
                $serial->forceFill([
                    'status'=>ProductSerial::AVAILABLE,'order_id'=>null,'order_item_id'=>null,
                    'reserved_at'=>null,'assigned_at'=>null,'notes'=>trim(($serial->notes ? $serial->notes."\n" : '').'released: '.$reason),
                ])->save();
            }
            foreach ($order->items()->where('fulfillment_source_snapshot','local_serials')->get() as $item) {
                if (!$item->fulfilled_at) $item->forceFill(['serial_status'=>'pending','provider_status'=>'pending'])->save();
            }
            return $rows->count();
        },3);
    }

    public function releaseItem(OrderItem $item, string $reason = 'order_item_removed'): int
    {
        return DB::transaction(function () use ($item,$reason) {
            $rows=ProductSerial::where('order_item_id',$item->id)->where('status',ProductSerial::ASSIGNED)->lockForUpdate()->get();
            foreach($rows as $serial){
                $serial->forceFill(['status'=>ProductSerial::AVAILABLE,'order_id'=>null,'order_item_id'=>null,'reserved_at'=>null,'assigned_at'=>null,
                    'notes'=>trim(($serial->notes?$serial->notes."\n":'').'released: '.$reason)])->save();
            }
            return $rows->count();
        },3);
    }

    public function fulfillItem(OrderItem $item, bool $force = false): array
    {
        return DB::transaction(function () use ($item, $force) {
            $locked = OrderItem::with('product')->whereKey($item->id)->lockForUpdate()->firstOrFail();
            if ($this->sourceFor($locked) !== 'local_serials') return ['success'=>true,'status'=>'not_required','assigned'=>0];
            if ($this->modeFor($locked) === 'manual' && !$force) {
                $this->markWaiting($locked, 'awaiting_manual');
                return ['success'=>false,'status'=>'awaiting_manual','assigned'=>0];
            }

            $already = ProductSerial::where('order_item_id', $locked->id)
                ->whereIn('status', [ProductSerial::ASSIGNED, ProductSerial::DELIVERED])
                ->lockForUpdate()->get();
            $needed = max(0, (int)$locked->quantity - $already->count());
            $picked = collect();
            if ($needed > 0) {
                $picked = ProductSerial::where('product_id', $locked->product_id)
                    ->usable()->orderByRaw('expires_at IS NULL, expires_at ASC')->orderBy('id')
                    ->lockForUpdate()->limit($needed)->get();
                foreach ($picked as $serial) {
                    $serial->forceFill([
                        'status' => ProductSerial::DELIVERED,
                        'order_id' => $locked->order_id,
                        'order_item_id' => $locked->id,
                        'assigned_at' => now(),
                        'delivered_at' => now(),
                    ])->save();
                }
            }

            // Codes reserved before payment become delivered atomically at fulfilment time.
            ProductSerial::where('order_item_id',$locked->id)->where('status',ProductSerial::ASSIGNED)
                ->lockForUpdate()->get()->each(function(ProductSerial $serial){
                    $serial->forceFill(['status'=>ProductSerial::DELIVERED,'delivered_at'=>now()])->save();
                });

            $assigned = ProductSerial::where('order_item_id', $locked->id)
                ->whereIn('status', [ProductSerial::ASSIGNED, ProductSerial::DELIVERED])
                ->orderBy('id')->get();

            $snapshots = $assigned->map(fn(ProductSerial $serial) => $this->snapshot($serial))->values()->all();
            $complete = $assigned->count() >= (int)$locked->quantity;
            $status = $complete ? 'delivered' : ($assigned->count() ? 'partial' : 'awaiting_serial');

            $locked->forceFill([
                'serial_codes' => $snapshots,
                'serial_status' => $status,
                'provider_status' => $complete ? 'fulfilled' : $status,
                'fulfilled_at' => $complete ? ($locked->fulfilled_at ?: now()) : null,
                'failure_reason' => $complete ? null : 'لا توجد أكواد تسلسلية كافية متاحة حالياً',
            ])->save();

            $this->log($locked, 'local_serial_fulfillment', [
                'required'=>(int)$locked->quantity,'assigned'=>$assigned->count(),'newly_assigned'=>$picked->count(),'status'=>$status,
            ]);

            return ['success'=>$complete,'status'=>$status,'assigned'=>$assigned->count(),'required'=>(int)$locked->quantity];
        }, 3);
    }

    public function addAndAssign(OrderItem $item, array $data, ?int $adminId = null): array
    {
        $result = $this->addCodes($item->product_id, [[
            'code'=>$data['code'], 'pin'=>$data['pin']??null, 'expires_at'=>$data['expires_at']??null, 'notes'=>$data['notes']??null,
        ]], $adminId, 'order_manual');
        if (($result['created'] ?? 0) < 1) throw new \RuntimeException('الكود موجود بالفعل أو غير صالح.');
        return $this->fulfillItem($item, true);
    }

    public function refreshExpiredStatuses(?int $productId = null): int
    {
        return ProductSerial::query()->when($productId, fn($q)=>$q->where('product_id',$productId))
            ->where('status', ProductSerial::AVAILABLE)->whereNotNull('expires_at')->where('expires_at','<=',now())
            ->update(['status'=>ProductSerial::EXPIRED]);
    }

    public function syncOrderStatus(Order $order): void
    {
        $order->loadMissing('items.product');
        $needsFulfillment = $order->items->filter(fn($i)=>in_array($this->sourceFor($i), ['local_serials','like4app'], true));
        if ($needsFulfillment->isEmpty()) return;
        $all = $needsFulfillment->every(function ($item) {
            if ($this->sourceFor($item)==='local_serials') return $item->serial_status==='delivered' && (bool)$item->fulfilled_at;
            return $item->provider_status==='fulfilled' && (bool)$item->fulfilled_at;
        });
        $order->forceFill([
            'fulfillment_status'=>$all?'fulfilled':'processing',
            'fulfilled_at'=>$all?($order->fulfilled_at?:now()):null,
        ])->save();
    }

    public function sourceFor(OrderItem $item): string
    {
        return (string)($item->fulfillment_source_snapshot ?: $item->product?->fulfillment_source ?: ($item->product?->external_id ? 'like4app' : 'none'));
    }

    public function modeFor(OrderItem $item): string
    {
        return (string)($item->fulfillment_mode_snapshot ?: $item->product?->fulfillment_mode ?: 'manual');
    }

    private function markWaiting(OrderItem $item, string $status): void
    {
        $item->forceFill(['serial_status'=>$status,'provider_status'=>$status])->save();
    }

    private function snapshot(ProductSerial $serial): array
    {
        return [
            'serial_id'=>$serial->id,
            'serial_code'=>Crypt::encryptString((string)$serial->code),
            'serial_number'=>null,
            'valid_to'=>$serial->expires_at?->toIso8601String(),
            'source'=>'local',
            'pin'=>filled($serial->pin) ? Crypt::encryptString((string)$serial->pin) : null,
        ];
    }

    private function log(OrderItem $item, string $action, array $data): void
    {
        OrderLog::create([
            'order_id'=>$item->order_id,'action'=>$action,'provider'=>'local_serials',
            'request_data'=>array_merge(['order_item_id'=>$item->id],$data),'response_data'=>null,
            'ip_address'=>request()?->ip(),'user_agent'=>request()?->userAgent(),'created_by'=>auth('admin')->id(),
        ]);
    }
}
