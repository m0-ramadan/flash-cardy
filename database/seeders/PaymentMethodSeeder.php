<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run()
    {
        $methods = [
            ['key' => 'cash', 'name' => 'الدفع عند الاستلام', 'icon' => 'fas fa-money-bill-wave', 'is_active' => true, 'is_payment' => true, 'is_wallet' => false],
            ['key' => 'visa', 'name' => 'فيزا', 'icon' => 'fab fa-cc-visa', 'is_active' => true, 'is_payment' => true, 'is_wallet' => false],
            ['key' => 'mastercard', 'name' => 'ماستركارد', 'icon' => 'fab fa-cc-mastercard', 'is_active' => true, 'is_payment' => true, 'is_wallet' => false],
            ['key' => 'fawry', 'name' => 'فوري', 'icon' => 'fas fa-mobile-alt', 'is_active' => true, 'is_payment' => true, 'is_wallet' => false],
            ['key' => 'vodafone_cash', 'name' => 'فودافون كاش', 'icon' => 'fas fa-wallet', 'is_active' => true, 'is_payment' => true, 'is_wallet' => true],
            ['key' => 'instapay', 'name' => 'إنستا باي', 'icon' => 'fas fa-credit-card', 'is_active' => true, 'is_payment' => true, 'is_wallet' => false],
            ['key' => 'paypal', 'name' => 'PayPal', 'icon' => 'fab fa-paypal', 'is_active' => true, 'is_payment' => true, 'is_wallet' => false],
            ['key' => 'bank_transfer', 'name' => 'التحويل البنكي', 'icon' => 'fas fa-university', 'is_active' => true, 'is_payment' => true, 'is_wallet' => false],
            ['key' => 'paymob', 'name' => 'Paymob', 'icon' => 'fas fa-credit-card', 'is_active' => true, 'is_payment' => true, 'is_wallet' => false],
            ['key' => 'nowpayments', 'name' => 'NOWPayments', 'icon' => 'fas fa-coins', 'is_active' => true, 'is_payment' => true, 'is_wallet' => false],
        ];

        foreach ($methods as $method) {
            PaymentMethod::updateOrCreate(
                ['key' => $method['key']],
                $method
            );
        }
    }
}
