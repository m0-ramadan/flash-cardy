<?php

namespace App\Services\Inventory;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function reserveOrder(Order $order): void
    {
        $order->loadMissing('items.product');
        foreach ($order->items as $item) {
            $this->reserveItem($item);
        }
    }

    public function reserveItem(OrderItem $item): ?StockReservation
    {
        return DB::transaction(function () use ($item) {
            $product = Product::withTrashed()->whereKey($item->product_id)->lockForUpdate()->first();
            if (!$product || !$this->tracksStock($product)) {
                return null;
            }

            $existing = StockReservation::where('order_item_id', $item->id)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $stock = max(0, (int) ($product->stock ?? 0));
            $reserved = max(0, (int) ($product->reserved_stock ?? 0));
            $qty = max(1, (int) $item->quantity);
            $available = $stock - $reserved;
            if ($available < $qty) {
                throw new \RuntimeException("المخزون غير كافٍ للمنتج #{$product->id}. المتاح {$available} والمطلوب {$qty}");
            }

            $beforeReserved = $reserved;
            $product->reserved_stock = $reserved + $qty;
            $product->save();

            $reference = 'RES-' . $item->order_id . '-' . $item->id;
            $reservation = StockReservation::create([
                'order_id' => $item->order_id,
                'order_item_id' => $item->id,
                'product_id' => $product->id,
                'quantity' => $qty,
                'status' => StockReservation::STATUS_RESERVED,
                'reference' => $reference,
                'reserved_at' => now(),
            ]);

            InventoryMovement::firstOrCreate(['reference' => $reference], [
                'product_id' => $product->id,
                'order_id' => $item->order_id,
                'order_item_id' => $item->id,
                'type' => InventoryMovement::TYPE_RESERVE,
                'quantity' => $qty,
                'stock_before' => $stock,
                'stock_after' => $stock,
                'reserved_before' => $beforeReserved,
                'reserved_after' => (int) $product->reserved_stock,
            ]);

            return $reservation;
        }, 3);
    }

    public function commitOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $reservations = StockReservation::where('order_id', $order->id)
                ->where('status', StockReservation::STATUS_RESERVED)
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                $product = Product::withTrashed()->whereKey($reservation->product_id)->lockForUpdate()->first();
                if (!$product || !$this->tracksStock($product)) {
                    $reservation->update(['status' => StockReservation::STATUS_COMMITTED, 'committed_at' => now()]);
                    continue;
                }

                $qty = (int) $reservation->quantity;
                $stockBefore = (int) ($product->stock ?? 0);
                $reservedBefore = (int) ($product->reserved_stock ?? 0);
                if ($stockBefore < $qty || $reservedBefore < $qty) {
                    throw new \RuntimeException("تعذر تثبيت مخزون المنتج #{$product->id}: بيانات الحجز غير متسقة");
                }

                $product->stock = $stockBefore - $qty;
                $product->reserved_stock = $reservedBefore - $qty;
                $product->sold_stock = max(0, (int) ($product->sold_stock ?? 0)) + $qty;
                $product->save();

                $reference = 'SALE-' . $order->id . '-' . $reservation->order_item_id;
                InventoryMovement::firstOrCreate(['reference' => $reference], [
                    'product_id' => $product->id,
                    'order_id' => $order->id,
                    'order_item_id' => $reservation->order_item_id,
                    'type' => InventoryMovement::TYPE_SALE,
                    'quantity' => -$qty,
                    'stock_before' => $stockBefore,
                    'stock_after' => (int) $product->stock,
                    'reserved_before' => $reservedBefore,
                    'reserved_after' => (int) $product->reserved_stock,
                ]);

                $reservation->update([
                    'status' => StockReservation::STATUS_COMMITTED,
                    'committed_at' => now(),
                ]);
            }

            if (!$order->inventory_committed_at) {
                $order->forceFill(['inventory_committed_at' => now()])->save();
            }
        }, 3);
    }

    public function releaseOrder(Order $order, string $reason = 'order_cancelled'): void
    {
        DB::transaction(function () use ($order, $reason) {
            $reservations = StockReservation::where('order_id', $order->id)
                ->where('status', StockReservation::STATUS_RESERVED)
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                $product = Product::withTrashed()->whereKey($reservation->product_id)->lockForUpdate()->first();
                if ($product && $this->tracksStock($product)) {
                    $qty = (int) $reservation->quantity;
                    $stockBefore = (int) ($product->stock ?? 0);
                    $reservedBefore = (int) ($product->reserved_stock ?? 0);
                    $product->reserved_stock = max(0, $reservedBefore - $qty);
                    $product->save();

                    $reference = 'REL-' . $order->id . '-' . $reservation->order_item_id;
                    InventoryMovement::firstOrCreate(['reference' => $reference], [
                        'product_id' => $product->id,
                        'order_id' => $order->id,
                        'order_item_id' => $reservation->order_item_id,
                        'type' => InventoryMovement::TYPE_RELEASE,
                        'quantity' => 0,
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockBefore,
                        'reserved_before' => $reservedBefore,
                        'reserved_after' => (int) $product->reserved_stock,
                        'reason' => $reason,
                    ]);
                }

                $reservation->update([
                    'status' => StockReservation::STATUS_RELEASED,
                    'released_at' => now(),
                    'release_reason' => $reason,
                ]);
            }

            if (!$order->inventory_released_at) {
                $order->forceFill(['inventory_released_at' => now()])->save();
            }
        }, 3);
    }

    public function releaseItem(OrderItem $item, string $reason = 'order_item_removed'): void
    {
        DB::transaction(function () use ($item, $reason) {
            $reservation = StockReservation::where('order_item_id',$item->id)->where('status',StockReservation::STATUS_RESERVED)->lockForUpdate()->first();
            if (!$reservation) return;
            $product = Product::withTrashed()->whereKey($reservation->product_id)->lockForUpdate()->first();
            if ($product && $this->tracksStock($product)) {
                $reservedBefore=(int)($product->reserved_stock??0); $stock=(int)($product->stock??0); $qty=(int)$reservation->quantity;
                $product->reserved_stock=max(0,$reservedBefore-$qty); $product->save();
                InventoryMovement::create([
                    'product_id'=>$product->id,'order_id'=>$item->order_id,'order_item_id'=>$item->id,
                    'admin_id'=>auth('admin')->id(),'type'=>InventoryMovement::TYPE_RELEASE,'quantity'=>0,
                    'stock_before'=>$stock,'stock_after'=>$stock,'reserved_before'=>$reservedBefore,'reserved_after'=>(int)$product->reserved_stock,
                    'reference'=>'RELITEM-'.$item->order_id.'-'.$item->id.'-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(2)),
                    'reason'=>$reason,
                ]);
            }
            $reservation->forceFill(['status'=>StockReservation::STATUS_RELEASED,'released_at'=>now(),'release_reason'=>$reason])->save();
        },3);
    }

    public function restoreForRefund(OrderItem $item, int $quantity, string $reference): void
    {
        if ($quantity <= 0) return;

        DB::transaction(function () use ($item, $quantity, $reference) {
            if (InventoryMovement::where('reference', $reference)->exists()) return;

            $product = Product::withTrashed()->whereKey($item->product_id)->lockForUpdate()->first();
            if (!$product || !$this->tracksStock($product)) return;

            $stockBefore = (int) ($product->stock ?? 0);
            $reservedBefore = (int) ($product->reserved_stock ?? 0);
            $product->stock = $stockBefore + $quantity;
            $product->sold_stock = max(0, (int) ($product->sold_stock ?? 0) - $quantity);
            $product->save();

            $item->refunded_quantity = min((int) $item->quantity, (int) $item->refunded_quantity + $quantity);
            $item->save();

            InventoryMovement::create([
                'product_id' => $product->id,
                'order_id' => $item->order_id,
                'order_item_id' => $item->id,
                'type' => InventoryMovement::TYPE_REFUND,
                'quantity' => $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => (int) $product->stock,
                'reserved_before' => $reservedBefore,
                'reserved_after' => (int) $product->reserved_stock,
                'reference' => $reference,
                'reason' => 'refund',
            ]);
        }, 3);
    }

    public function adjust(Product $product, int $newStock, ?int $adminId = null, ?string $reason = null): InventoryMovement
    {
        if ($newStock < 0) throw new \InvalidArgumentException('المخزون لا يمكن أن يكون سالباً');

        return DB::transaction(function () use ($product, $newStock, $adminId, $reason) {
            $locked = Product::withTrashed()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            if ((int) ($locked->reserved_stock ?? 0) > $newStock) {
                throw new \RuntimeException('لا يمكن جعل المخزون أقل من الكمية المحجوزة');
            }
            $before = (int) ($locked->stock ?? 0);
            $locked->stock = $newStock;
            $locked->save();

            return InventoryMovement::create([
                'product_id' => $locked->id,
                'admin_id' => $adminId,
                'type' => InventoryMovement::TYPE_ADJUSTMENT,
                'quantity' => $newStock - $before,
                'stock_before' => $before,
                'stock_after' => $newStock,
                'reserved_before' => (int) ($locked->reserved_stock ?? 0),
                'reserved_after' => (int) ($locked->reserved_stock ?? 0),
                'reference' => 'ADJ-' . $locked->id . '-' . now()->format('YmdHis') . '-' . bin2hex(random_bytes(3)),
                'reason' => $reason,
            ]);
        }, 3);
    }

    /**
     * Resize an existing unpaid reservation after an administrator edits order quantity.
     * Committed sales are deliberately immutable here; paid quantity corrections must go
     * through refund/adjustment flows so the inventory and financial audit trail stay valid.
     */
    public function resizeReservation(OrderItem $item, int $oldQuantity, ?int $adminId = null): void
    {
        DB::transaction(function () use ($item, $oldQuantity, $adminId) {
            $lockedItem = OrderItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $reservation = StockReservation::where('order_item_id', $lockedItem->id)->lockForUpdate()->first();
            if (!$reservation) {
                $this->reserveItem($lockedItem);
                return;
            }
            if ($reservation->status === StockReservation::STATUS_COMMITTED) {
                throw new \RuntimeException('لا يمكن تعديل كمية عنصر تم تثبيت مخزونه بعد الدفع. استخدم الاسترداد أو حركة مخزون مصححة.');
            }
            if ($reservation->status !== StockReservation::STATUS_RESERVED) {
                throw new \RuntimeException('حجز المخزون لهذا العنصر لم يعد قابلاً للتعديل.');
            }

            $product = Product::withTrashed()->whereKey($lockedItem->product_id)->lockForUpdate()->first();
            if (!$product || !$this->tracksStock($product)) return;

            $newQuantity = max(1, (int)$lockedItem->quantity);
            $reservedBefore = max(0, (int)$product->reserved_stock);
            $stock = max(0, (int)$product->stock);
            $delta = $newQuantity - (int)$reservation->quantity;
            if ($delta > 0 && ($stock - $reservedBefore) < $delta) {
                throw new \RuntimeException("المخزون غير كافٍ لزيادة الكمية. المتاح ".($stock-$reservedBefore));
            }
            $product->reserved_stock = max(0, $reservedBefore + $delta);
            $product->save();
            $reservation->quantity = $newQuantity;
            $reservation->save();

            InventoryMovement::create([
                'product_id'=>$product->id,'order_id'=>$lockedItem->order_id,'order_item_id'=>$lockedItem->id,
                'admin_id'=>$adminId,'type'=>InventoryMovement::TYPE_ADJUSTMENT,'quantity'=>0,
                'stock_before'=>$stock,'stock_after'=>$stock,'reserved_before'=>$reservedBefore,
                'reserved_after'=>(int)$product->reserved_stock,
                'reference'=>'RESIZE-'.$lockedItem->order_id.'-'.$lockedItem->id.'-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(2)),
                'reason'=>"admin_order_quantity_edit:{$oldQuantity}->{$newQuantity}",
            ]);
        }, 3);
    }

    private function tracksStock(Product $product): bool
    {
        if (($product->inventory_type ?? null) === 'digital') return false;
        if (isset($product->track_stock) && !$product->track_stock) return false;
        return $product->stock !== null;
    }
}
