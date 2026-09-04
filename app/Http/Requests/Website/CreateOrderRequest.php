<?php

namespace App\Http\Requests\Website;

use App\Models\PaymentMethod;
use App\Services\Payment\NowPaymentsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreateOrderRequest extends FormRequest
{
    private const SUPPORTED_PAYMENT_KEYS = [
        'paymob',
        'nowpayments',
        'cash',
        'cod',
        'bank_transfer',
        'instapay',
        'visa',
        'mastercard',
        'fawry',
        'vodafone_cash',
        'paypal',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('pay_currency')) {
            $this->merge([
                'pay_currency' => strtolower(trim((string) $this->input('pay_currency'))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'customer_name' => 'nullable|string|max:100',

            'customer_phone' => ['nullable', 'string'],

            'customer_email' => 'nullable|email',

            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',

            // وسائل الدفع المتاحة
            'payment_method' => 'required|exists:payment_methods,id',
            'pay_currency' => 'nullable|string|max:20',

            'notes' => 'nullable|string|max:1000',

            'coupon_code' => 'nullable|string',

            'price_amount' => 'prohibited',
            'price_currency' => 'prohibited',
            'total_amount' => 'prohibited',
            'payout_currency' => 'prohibited',
            'order_id' => 'prohibited',
            'ipn_callback_url' => 'prohibited',
            'is_fixed_rate' => 'prohibited',
            'is_fee_paid_by_user' => 'prohibited',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $paymentMethod = PaymentMethod::query()
                    ->whereKey($this->integer('payment_method'))
                    ->first();

                if (! $paymentMethod || ! $paymentMethod->is_active || ! $paymentMethod->is_payment) {
                    $validator->errors()->add('payment_method', 'طريقة الدفع غير متاحة');

                    return;
                }

                if (! $paymentMethod->key || ! in_array($paymentMethod->key, self::SUPPORTED_PAYMENT_KEYS, true)) {
                    $validator->errors()->add('payment_method', 'بوابة الدفع غير مدعومة');
                }

                if ($paymentMethod->key === 'nowpayments') {
                    $nowPayments = app(NowPaymentsService::class);
                    $payCurrency = $this->input('pay_currency', $nowPayments->defaultPayCurrency());
                    $allowed = $nowPayments->allowedPayCurrencies();

                    if (! in_array(strtolower($payCurrency), $allowed, true)) {
                        $validator->errors()->add('pay_currency', 'عملة الدفع الرقمية غير مدعومة');
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'payment_method.in' => 'طريقة الدفع غير صحيحة',
            'prohibited' => 'لا يمكن إرسال هذا الحقل من العميل',
        ];
    }
}
