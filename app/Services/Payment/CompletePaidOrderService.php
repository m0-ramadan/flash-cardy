<?php

namespace App\Services\Payment;

use App\Models\Cart;
use App\Models\Order;

class CompletePaidOrderService
{
    public function complete(Order $order): void
    {
        if ($order->paid_at !== null || $order->status_payment === 'paid') {
            return;
        }

        $order->update([
            'status_payment' => 'paid',
            'paid_at' => now(),
        ]);

        $cart = null;

        if ($order->cart_id) {
            $cart = Cart::find($order->cart_id);
        }

        if (! $cart && $order->user_id) {
            $cart = Cart::where('user_id', $order->user_id)->first();
        }

        if (! $cart && $order->session_id) {
            $cart = Cart::where('session_id', $order->session_id)->first();
        }

        if ($cart) {
            $cart->items()->delete();
            $cart->update(['subtotal' => 0, 'total' => 0]);
        }
    }
}
