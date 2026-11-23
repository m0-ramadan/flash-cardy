<?php

namespace App\Services\Payment;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\PaymentTransaction;
use App\Services\Coupons\CouponReservationService;
use App\Services\Fulfillment\Like4AppFulfillmentService;
use App\Services\Fulfillment\LocalSerialFulfillmentService;
use App\Services\Inventory\InventoryService;
use App\Services\Notifications\NotificationService;
use App\Support\SensitiveDataRedactor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompletePaidOrderService
{
    public function __construct(
        private InventoryService $inventory,
        private Like4AppFulfillmentService $like4App,
        private LocalSerialFulfillmentService $localSerials,
        private CouponReservationService $coupons,
        private NotificationService $notifications,
    ) {}

    /**
     * Complete a paid order exactly once. External provider calls happen outside the
     * database transaction, while every local step is independently idempotent.
     */
    public function complete(Order $order, ?PaymentTransaction $payment = null): void
    {
        $order = DB::transaction(function () use ($order, $payment) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->payment_state, ['refunded', 'chargeback'], true)) {
                throw new \RuntimeException('Cannot fulfill a refunded or charged-back order.');
            }

            if ($locked->status_payment !== 'paid' || $locked->payment_state !== 'paid' || !$locked->paid_at) {
                $locked->forceFill([
                    'status_payment' => 'paid',
                    'payment_state' => 'paid',
                    'paid_at' => $locked->paid_at ?: now(),
                    'order_state' => 'paid',
                    // Digital catalogue: a confirmed payment means "paid", not "processing".
                    'status' => $locked->status === 'pending' ? 'paid' : $locked->status,
                    'fulfillment_status' => in_array($locked->fulfillment_status, ['fulfilled', 'cancelled'], true)
                        ? $locked->fulfillment_status
                        : 'processing',
                ])->save();

                $this->writeLog($locked, 'payment_confirmed', [
                    'payment_transaction_id' => $payment?->id,
                    'gateway' => $payment?->gateway,
                ]);
            }

            if ($payment) {
                $payment->forceFill([
                    'status' => 'paid',
                    'paid_at' => $payment->paid_at ?: now(),
                    'webhook_processed_at' => $payment->webhook_processed_at ?: now(),
                ])->save();
            }

            return $locked->fresh(['items.product', 'user']);
        }, 3);

        // Local stock/coupon operations are idempotent and locked internally.
        $this->inventory->commitOrder($order);
        $this->coupons->consume($order);

        // Local code fulfilment is lock-safe. Automatic products are delivered now;
        // manual products remain awaiting_manual until an administrator releases/adds a code.
        $localResult = $this->localSerials->fulfillOrder($order, false);

        // External fulfillment is retry-safe and uses stable references per unit.
        $providerResult = $this->like4App->fulfillOrder($order);

        DB::transaction(function () use ($order, $providerResult, $localResult) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $hasProviderItems = (bool) ($providerResult['has_provider_items'] ?? false);
            $providerSuccess = (bool) ($providerResult['success'] ?? false);
            $hasLocalItems = (bool) ($localResult['has_local_items'] ?? false);
            $localSuccess = (bool) ($localResult['success'] ?? false);
            $success = (!$hasProviderItems || $providerSuccess) && (!$hasLocalItems || $localSuccess);

            if ($success) {
                $locked->forceFill([
                    'fulfillment_status' => 'fulfilled',
                    'fulfilled_at' => $locked->fulfilled_at ?: now(),
                    'order_state' => $locked->order_state === 'cancelled' ? $locked->order_state : 'completed',
                    // Codes delivered automatically -> the order is complete.
                    'status' => in_array($locked->status, ['cancelled', 'refunded'], true) ? $locked->status : 'completed',
                ])->save();
            } else {
                $locked->forceFill([
                    'fulfillment_status' => 'processing',
                    'order_state' => $locked->order_state ?: 'processing',
                ])->save();
            }

            $this->writeLog($locked, 'fulfillment_processed', [
                'success' => $success,
                'has_provider_items' => $hasProviderItems,
                'provider_success' => $providerSuccess,
                'has_local_items' => $hasLocalItems,
                'local_success' => $localSuccess,
            ]);
        }, 3);

        // Clear the cart only after payment is authoritative and local fulfillment state was persisted.
        $this->clearCart($order);

        $fresh = $order->fresh(['user']);
        if ($fresh?->user) {
            $this->notifications->user(
                $fresh->user,
                'تم تأكيد الدفع',
                'تم تأكيد دفع الطلب '.$fresh->order_number.' وجارٍ تنفيذ الطلب.',
                'payment_success',
                ['order_id' => $fresh->id, 'order_number' => $fresh->order_number],
                true
            );
        }
    }

    private function clearCart(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $cart = $order->cart_id ? Cart::whereKey($order->cart_id)->lockForUpdate()->first() : null;
            if (!$cart && $order->user_id) {
                $cart = Cart::where('user_id', $order->user_id)->lockForUpdate()->first();
            }
            if (!$cart) return;

            // Never clear a cart that has since been reused for another checkout unless it is
            // explicitly the cart captured by this order.
            if ($order->cart_id && (int) $cart->id !== (int) $order->cart_id) return;
            $cart->items()->delete();
            $cart->forceFill(['subtotal' => 0, 'total' => 0])->save();
        }, 3);
    }

    private function writeLog(Order $order, string $action, array $data): void
    {
        try {
            OrderLog::create([
                'order_id' => $order->id,
                'action' => $action,
                'provider' => $order->provider,
                'request_data' => SensitiveDataRedactor::redact($data),
                'response_data' => null,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_by' => auth('admin')->id(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Unable to write order fulfillment log', [
                'order_id' => $order->id,
                'error_class' => $e::class,
            ]);
        }
    }
}
