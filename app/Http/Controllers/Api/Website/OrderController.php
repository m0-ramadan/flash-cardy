<?php

namespace App\Http\Controllers\Api\Website;

use App\Exceptions\NowPaymentsRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Website\ApplyCouponRequest;
use App\Http\Requests\Website\CreateOrderRequest;
use App\Http\Resources\Website\OrderDetailsResource;
use App\Http\Resources\Website\OrderResource;
use App\Http\Resources\Website\PaymentMethodResource;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use App\Services\Payment\CompletePaidOrderService;
use App\Services\Payment\NowPaymentsService;
use App\Services\Wallet\ExchangeRateService;
use App\Support\PaymentLogger;
use App\Traits\ApiResponseTrait;
use App\Traits\HandlesPaymobPayment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class OrderController extends Controller
{
    use ApiResponseTrait, HandlesPaymobPayment;

    /**
     * عرض طلبات المستخدم المسجل
     */
    public function index(Request $request)
    {
        $user = auth('sanctum')->user();

        if (! $user) {
            return $this->errorResponse('يجب تسجيل الدخول لعرض الطلبات', 401);
        }

        $orders = Order::with(['items.product', 'items'])
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(15);

        return $this->successResponse(
            OrderResource::collection($orders)->response()->getData(true),
            'تم جلب الطلبات بنجاح'
        );
    }

    /**
     * إنشاء طلب جديد من السلة
     */
    public function store(CreateOrderRequest $request)
    {
        $user = auth('sanctum')->user();
        $cart = $this->getCurrentCart();

        if ($cart->items()->count() === 0) {
            return $this->errorResponse('السلة فارغة، لا يمكن إنشاء طلب', 400);
        }

        $paymentMethod = PaymentMethod::query()
            ->whereKey($request->integer('payment_method'))
            ->where('is_active', true)
            ->where('is_payment', true)
            ->first();

        if (! $paymentMethod) {
            return $this->errorResponse('طريقة الدفع غير متاحة', 422);
        }

        $coupon = null;
        $discountAmount = 0;
        $imagePath = null;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('orders', 'public');
        }

        if ($request->filled('coupon_code')) {
            $coupon = Coupon::where('code', strtoupper($request->coupon_code))->first();

            if (! $coupon || ! $coupon->isValidForOrder($cart->total, $user?->id, $cart->session_id ?: session()->getId())) {
                return $this->errorResponse('كوبون الخصم غير صالح أو منتهي الصلاحية', 400);
            }

            $discountAmount = $coupon->calculateDiscount($cart->total);
        }

        [$order, $paymentTransaction] = DB::transaction(function () use ($request, $user, $cart, $coupon, $discountAmount, $imagePath, $paymentMethod) {
            $order = Order::create([
                'user_id' => $user?->id,
                'cart_id' => $cart->id,
                'session_id' => $cart->session_id,
                'order_number' => $this->generateUniqueOrderNumber(),
                'customer_name' => $request->customer_name ?? $user?->name,
                'customer_phone' => $request->customer_phone ?? $user?->phone,
                'customer_email' => $request->customer_email ?? $user?->email,
                'subtotal' => $cart->subtotal,
                'shipping_amount' => 0,
                'discount_amount' => $discountAmount,
                'tax_amount' => 0,
                'total_amount' => $cart->total - $discountAmount,
                'payment_method' => $request->payment_method,
                'status' => 'pending',
                'status_payment' => 'pending',
                'notes' => $request->notes,
                'coupon_id' => $coupon?->id,
                'image' => $imagePath,
            ]);

            // نقل العناصر من السلة إلى الطلب
            foreach ($cart->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    // 'size_id'               => $item->size_id,
                    // 'color_id'              => $item->color_id,
                    // 'printing_method_id'    => $item->printing_method_id,
                    // 'print_locations'       => $item->print_locations,
                    // 'embroider_locations'   => $item->embroider_locations,
                    // 'selected_options'      => $item->selected_options,
                    // 'design_service_id'     => $item->design_service_id,
                    'quantity' => $item->quantity,
                    'price_per_unit' => $item->price_per_unit,
                    'total_price' => $item->line_total ?? $item->price_per_unit * $item->quantity,
                    'is_sample' => $item->is_sample,
                    'note' => $item->note,
                    'quantity_id' => $item->quantity_id,
                    // 'image_design'          => $item->image_design,
                ]);
            }

            $paymentTransaction = null;

            if ($paymentMethod->key === 'nowpayments') {
                $nowPayments = app(NowPaymentsService::class);

                $paymentTransaction = PaymentTransaction::create([
                    'order_id' => $order->id,
                    'payment_method_id' => $paymentMethod->id,
                    'gateway' => 'nowpayments',
                    'status' => 'initiating',
                    'original_amount' => $order->total_amount,
                    'original_currency' => $this->nowPaymentsStoreCurrency($nowPayments),
                    'is_fixed_rate' => $nowPayments->isFixedRate(),
                    'is_fee_paid_by_user' => $nowPayments->isFeePaidByUser(),
                    'metadata' => [
                        'requested_pay_currency' => $request->input('pay_currency'),
                    ],
                ]);
            }

            return [$order, $paymentTransaction];
        });

        if ($paymentMethod->key === 'paymob') {
            $payment = $this->initiatePaymobPayment($order);
            $payment = $payment instanceof JsonResponse
                ? ($payment->getData(true) ?: [])
                : $payment;

            if (! is_array($payment) || empty($payment['success'])) {
                return $this->errorResponse([$payment['message'] ?? 'فشل إنشاء عملية الدفع'], 400);
            }

            return $this->successResponse([
                'payment_url' => $payment['payment_url'],
                'shorten_url' => $payment['shorten_url'] ?? null,
                'client_secret' => $payment['client_secret'] ?? null,
                'intention_id' => $payment['intention_id'] ?? null,
                'order_number' => $order->order_number,
                'payment' => [
                    'gateway' => 'paymob',
                    'payment_type' => 'redirect',
                    'payment_url' => $payment['payment_url'],
                    'shorten_url' => $payment['shorten_url'] ?? null,
                    'client_secret' => $payment['client_secret'] ?? null,
                    'intention_id' => $payment['intention_id'] ?? null,
                ],
                'message' => 'جاري توجيهك إلى بوابة الدفع الآمنة...',
            ]);
        }

        if ($paymentMethod->key === 'nowpayments') {
            return $this->initiateNowPaymentsPayment($request, $order, $paymentTransaction);
        }

        return $this->successResponse(
            new OrderDetailsResource($order->load(['items.product'])),
            'تم إنشاء الطلب بنجاح',
            201
        );
    }

    /**
     * إلغاء الطلب (فقط إذا كان pending أو processing)
     */
    public function cancelled($codeOrder)
    {
        $order = Order::where('order_number', $codeOrder)->firstOrFail();

        $user = auth('sanctum')->user();

        // تحقق من الصلاحية
        if ($user && $order->user_id !== $user->id) {
            return $this->errorResponse('غير مصرح لك بإلغاء هذا الطلب', 403);
        }

        if (! $user && ! $this->guestCanAccessOrder($order)) {
            return $this->errorResponse('رقم الهاتف مطلوب لإلغاء الطلب', 403);
        }

        if (! in_array($order->status, ['pending', 'processing'])) {
            return $this->errorResponse('لا يمكن إلغاء الطلب في هذه الحالة', 400);
        }

        $order->update(['status' => 'cancelled']);

        return $this->successResponse(
            new OrderDetailsResource($order),
            'تم إلغاء الطلب بنجاح'
        );
    }

    /**
     * تتبع الطلب برقم الطلب (للمسجلين والزوار)
     */
    public function traceOrder($codeOrder, Request $request)
    {
        $order = Order::with(['items.product'])
            ->where('order_number', $codeOrder)
            ->firstOrFail();

        $user = auth('sanctum')->user();

        // لو مسجل دخول → تأكد إنه صاحب الطلب
        // if ($user && $order->user_id !== $user->id) {
        //     return $this->errorResponse('هذا الطلب ليس لك', 403);
        // }

        // لو زائر → يطلب رقم التليفون
        // if (!$user) {
        //     $phone = $request->input('phone');
        //     if (!$phone || $order->customer_phone !== $phone) {
        //         return $this->errorResponse('رقم الهاتف غير صحيح', 403);
        //     }
        // }

        return $this->successResponse(
            new OrderDetailsResource($order),
            'تم جلب تفاصيل الطلب'
        );
    }

    /**
     * Summary of show
     *
     * @param  mixed  $orderID
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($orderID)
    {
        $user = auth('sanctum')->user();

        if (! $user) {
            return $this->errorResponse('يجب تسجيل الدخول لعرض تفاصيل الطلب', 401);
        }

        $order = Order::where('id', $orderID)
            ->where('user_id', $user->id)
            ->firstOrFail();

        return $this->successResponse(
            new OrderDetailsResource($order),
            'تم جلب تفاصيل الطلب بنجاح'
        );
    }

    /**
     * تطبيق كوبون خصم على السلة الحالية
     */
    public function applyCoupon(ApplyCouponRequest $request)
    {
        $user = auth()->user();
        $cart = $this->getCurrentCart();

        if ($cart->items()->count() === 0) {
            return $this->errorResponse('السلة فارغة، لا يمكن تطبيق كوبون', 400);
        }

        $coupon = Coupon::where('code', strtoupper($request->coupon_code))->first();

        if (! $coupon || ! $coupon->isValidForOrder($cart->total, $user?->id, session()->getId())) {
            return $this->errorResponse('كوبون الخصم غير صالح أو منتهي الصلاحية', 400);
        }

        $discountAmount = $coupon->calculateDiscount($cart->total);

        return $this->successResponse(
            [
                'coupon_id' => $coupon->id,
                'discount_amount' => $discountAmount,
                'new_total' => $cart->total - $discountAmount,
            ],
            'تم تطبيق الكوبون بنجاح'
        );
    }

    public function webhook(Request $request)
    {
        $hmacSecret = config('services.paymob.hmac_secret');

        $receivedHmac = $request->header('X-Paymob-Hmac-Signature')
            ?? $request->input('hmac');

        if (! $receivedHmac || empty($hmacSecret)) {
            return response('Unauthorized', 401);
        }

        $obj = $request->input('obj');
        $concatenated = collect($obj)->flatten()->implode('');
        $calculatedHmac = hash_hmac('sha512', $concatenated, $hmacSecret);

        if (! hash_equals($calculatedHmac, $receivedHmac)) {
            \Illuminate\Support\Facades\Log::warning('PayMob Webhook HMAC Invalid', ['ip' => $request->ip()]);

            return response('Invalid HMAC', 400);
        }

        if ($request->input('type') === 'TRANSACTION' && ($obj['success'] ?? false) && ($obj['is_capture'] ?? true)) {
            $orderNumber = $obj['merchant_reference']
                ?? $obj['order']['merchant_order_id']
                ?? $obj['order']['special_reference']
                ?? $obj['payment_key_claims']['extra']['order_number']
                ?? $obj['payment_key_claims']['extra']['order_id']
                ?? null;

            if (! $orderNumber) {
                return response('No reference', 400);
            }

            $order = Order::where('order_number', $orderNumber)->first();

            if ($order && $order->status === 'pending') {
                $order->update(['transaction_id' => $obj['id']]);
                app(CompletePaidOrderService::class)->complete($order);

                PaymentLogger::info("Order Paid via PayMob: {$orderNumber}");
            }
        }

        return response('OK', 200);
    }

    public function paymentMethods(Request $request)
    {

        $isPayment = filter_var(
            $request->input('is_payment'),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );

        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->where('is_payment', $isPayment)
            ->get();

        return $this->successResponse(
            PaymentMethodResource::collection($paymentMethods),
            'تم جلب طرق الدفع بنجاح'
        );
    }

    public function paymentStatus(Request $request)
    {
        $orderId = $request->query('orderId');

        if (! $orderId) {
            return $this->errorResponse('بيانات غير مكتملة', 400);
        }

        $order = Order::where('order_number', $orderId)
            ->orWhere('id', $orderId)
            ->first();

        if (! $order) {
            return $this->errorResponse('الطلب غير موجود', 404);
        }

        $user = auth('sanctum')->user();

        if ($user && $order->user_id && $order->user_id !== $user->id) {
            return $this->errorResponse('غير مصرح لك بعرض حالة هذا الطلب', 403);
        }

        $payment = $order->latestPaymentTransaction;

        return $this->successResponse([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'order_status' => $order->status,
            'payment_status' => $order->status_payment,
            'gateway' => $payment?->gateway,
            'payment_id' => $payment?->provider_payment_id,
            'payment_url' => $payment?->metadata['invoice_url'] ?? null,
            'invoice_url' => $payment?->metadata['invoice_url'] ?? null,
            'pay_currency' => $payment?->pay_currency,
            'pay_amount' => $payment?->pay_amount,
            'actually_paid' => $payment?->actually_paid,
            'requires_manual_review' => (bool) ($payment?->requires_manual_review ?? false),
            'paid_at' => $order->paid_at?->toIso8601String(),
            'expires_at' => $payment?->expires_at?->toIso8601String(),
            'total' => $order->total_amount,
        ], 'تم جلب حالة الدفع');
    }

    private function initiateNowPaymentsPayment(Request $request, Order $order, ?PaymentTransaction $paymentTransaction)
    {
        $nowPayments = app(NowPaymentsService::class);
        $exchangeRates = app(ExchangeRateService::class);
        $payCurrency = strtolower($request->input('pay_currency', $this->nowPaymentsDefaultPayCurrency($nowPayments)));
        $allowedCurrencies = $this->nowPaymentsAllowedPayCurrencies($nowPayments);

        if (! in_array($payCurrency, $allowedCurrencies, true)) {
            return $this->errorResponse('عملة الدفع الرقمية غير مدعومة', 422);
        }

        try {
            $storeCurrency = $this->nowPaymentsStoreCurrency($nowPayments);
            $priceCurrency = $this->nowPaymentsPriceCurrency($nowPayments);

            $conversion = $exchangeRates->convert((float) $order->total_amount, $storeCurrency, strtoupper($priceCurrency));

            if (empty($conversion['success'])) {
                throw new \RuntimeException($conversion['message'] ?? 'Currency conversion failed');
            }

            $priceAmount = number_format((float) $conversion['converted_amount'], 2, '.', '');

            $payload = [
                'price_amount' => (float) $priceAmount,
                'price_currency' => $priceCurrency,
                'ipn_callback_url' => route('nowpayments.ipn'),
                'success_url' => $this->nowPaymentsSuccessUrl(),
                'cancel_url' => $this->nowPaymentsCancelUrl(),
                'order_id' => $order->order_number,
                'order_description' => 'Order '.$order->order_number,
            ];

            $providerInvoice = $this->nowPaymentsCreateInvoice($nowPayments, $payload);

            foreach (['id', 'invoice_url'] as $field) {
                if (! array_key_exists($field, $providerInvoice)) {
                    throw new \RuntimeException("NOWPayments response missing {$field}.");
                }
            }

            $invoiceUrl = (string) $providerInvoice['invoice_url'];
            $providerStatus = (string) ($providerInvoice['payment_status'] ?? $providerInvoice['status'] ?? 'waiting');
            $normalizedStatus = $nowPayments->normalizeStatus($providerStatus);

            DB::transaction(function () use ($paymentTransaction, $payload, $providerInvoice, $invoiceUrl, $providerStatus, $normalizedStatus, $conversion, $priceAmount, $priceCurrency, $payCurrency, $order) {
                $paymentTransaction?->update([
                    'provider_payment_id' => (string) $providerInvoice['id'],
                    'purchase_id' => $providerInvoice['token_id'] ?? $providerInvoice['purchase_id'] ?? null,
                    'status' => $normalizedStatus,
                    'provider_status' => $providerStatus,
                    'exchange_rate' => $conversion['exchange_rate'],
                    'price_amount' => $priceAmount,
                    'price_currency' => $priceCurrency,
                    'pay_amount' => $providerInvoice['pay_amount'] ?? null,
                    'pay_currency' => $providerInvoice['pay_currency'] ?? $payCurrency,
                    'pay_address' => $providerInvoice['pay_address'] ?? null,
                    'payin_extra_id' => $providerInvoice['payin_extra_id'] ?? null,
                    'outcome_amount' => $providerInvoice['outcome_amount'] ?? null,
                    'outcome_currency' => $providerInvoice['outcome_currency'] ?? null,
                    'initial_payload' => $payload,
                    'provider_response' => $providerInvoice,
                    'expires_at' => isset($providerInvoice['expiration_estimate_date']) ? \Carbon\Carbon::parse($providerInvoice['expiration_estimate_date']) : null,
                    'metadata' => [
                        'invoice_url' => $invoiceUrl,
                        'invoice_id' => (string) $providerInvoice['id'],
                        'requested_pay_currency' => $payCurrency,
                        'exchange_rate_provider' => $conversion['rate_source'] ?? null,
                        'exchange_rate_obtained_at' => $conversion['converted_at'] ?? now()->toIso8601String(),
                    ],
                ]);

                $order->update([
                    'status_payment' => $normalizedStatus,
                    'transaction_id' => (string) $providerInvoice['id'],
                ]);
            });

            return $this->successResponse([
                'payment_url' => $invoiceUrl,
                'invoice_url' => $invoiceUrl,
                'order_number' => $order->order_number,
                'order_status' => $order->status,
                'payment' => [
                    'gateway' => 'nowpayments',
                    'payment_type' => 'redirect',
                    'payment_url' => $invoiceUrl,
                    'invoice_url' => $invoiceUrl,
                    'invoice_id' => (string) $providerInvoice['id'],
                    'payment_id' => $providerInvoice['payment_id'] ?? null,
                    'payment_status' => $normalizedStatus,
                    'pay_address' => $providerInvoice['pay_address'] ?? null,
                    'pay_amount' => isset($providerInvoice['pay_amount']) ? (string) $providerInvoice['pay_amount'] : null,
                    'pay_currency' => $payCurrency,
                    'payin_extra_id' => $providerInvoice['payin_extra_id'] ?? null,
                    'price_amount' => $priceAmount,
                    'price_currency' => $priceCurrency,
                    'original_amount' => (string) $order->total_amount,
                    'original_currency' => $storeCurrency,
                    'expires_at' => $paymentTransaction?->fresh()?->expires_at?->toIso8601String(),
                ],
            ], 'تم إنشاء بيانات الدفع بالعملة الرقمية', 201);
        } catch (\Throwable $e) {
            $failedUpdate = [
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
                'failed_at' => now(),
            ];

            if ($e instanceof NowPaymentsRequestException) {
                $failedUpdate['provider_response'] = [
                    'http_status' => $e->statusCode(),
                    'body' => $e->responseBody(),
                ];
            }

            $paymentTransaction?->update($failedUpdate);

            if (
                $e instanceof NowPaymentsRequestException
                && is_array($e->responseBody())
                && ($e->responseBody()['code'] ?? null) === 'AMOUNT_MINIMAL_ERROR'
            ) {
                return $this->errorResponse([
                    'message' => 'قيمة الطلب أقل من الحد الأدنى لدى NOWPayments لهذه العملة. اختر عملة أخرى أو زِد قيمة الطلب.',
                    'provider_message' => $e->responseBody()['message'] ?? null,
                ], 422);
            }

            PaymentLogger::warning('NOWPayments initiation failed', [
                'gateway' => 'nowpayments',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'local_payment_id' => $paymentTransaction?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('تعذر إنشاء عملية الدفع بالعملة الرقمية حاليًا', 422);
        }
    }

    // ==================== Helpers ====================

    private function getCurrentCart(): Cart
    {
        $user = auth('sanctum')->user();
        $sessionId = request()->header('X-Session-Id') ?: session()->getId();

        return Cart::firstOrCreate(
            $user ? ['user_id' => $user->id] : ['session_id' => $sessionId],
            ['subtotal' => 0, 'total' => 0]
        );
    }

    private function generateUniqueOrderNumber(): string
    {
        do {
            $number = 'ORD-'.strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }

    private function guestCanAccessOrder(Order $order): bool
    {
        $phone = request()->input('phone');

        return $phone && $order->customer_phone === $phone;
    }

    private function nowPaymentsStoreCurrency(NowPaymentsService $nowPayments): string
    {
        if (method_exists($nowPayments, 'storeCurrency')) {
            return $nowPayments->storeCurrency();
        }

        return strtoupper((string) $this->nowPaymentsSetting('services.nowpayments.store_currency', 'NOWPAYMENTS_STORE_CURRENCY', 'EGP'));
    }

    private function nowPaymentsPriceCurrency(NowPaymentsService $nowPayments): string
    {
        if (method_exists($nowPayments, 'priceCurrency')) {
            return $nowPayments->priceCurrency();
        }

        return strtolower((string) $this->nowPaymentsSetting('services.nowpayments.price_currency', 'NOWPAYMENTS_PRICE_CURRENCY', 'usd'));
    }

    private function nowPaymentsPayoutCurrency(NowPaymentsService $nowPayments): string
    {
        if (method_exists($nowPayments, 'payoutCurrency')) {
            return $nowPayments->payoutCurrency();
        }

        return strtolower((string) $this->nowPaymentsSetting('services.nowpayments.payout_currency', 'NOWPAYMENTS_PAYOUT_CURRENCY', 'usdttrc20'));
    }

    private function nowPaymentsDefaultPayCurrency(NowPaymentsService $nowPayments): string
    {
        if (method_exists($nowPayments, 'defaultPayCurrency')) {
            return $nowPayments->defaultPayCurrency();
        }

        return strtolower((string) $this->nowPaymentsSetting('services.nowpayments.default_pay_currency', 'NOWPAYMENTS_DEFAULT_PAY_CURRENCY', 'usdttrc20'));
    }

    private function nowPaymentsAllowedPayCurrencies(NowPaymentsService $nowPayments): array
    {
        if (method_exists($nowPayments, 'allowedPayCurrencies')) {
            return $nowPayments->allowedPayCurrencies();
        }

        $value = $this->nowPaymentsSetting('services.nowpayments.allowed_pay_currencies', 'NOWPAYMENTS_ALLOWED_PAY_CURRENCIES', 'usdttrc20,usdtbsc');

        if (is_array($value)) {
            return array_values(array_filter(array_map(
                fn ($currency) => strtolower(trim((string) $currency)),
                $value
            )));
        }

        return array_values(array_filter(array_map(
            fn ($currency) => strtolower(trim($currency)),
            explode(',', (string) $value)
        )));
    }

    private function nowPaymentsSuccessUrl(): string
    {
        return (string) $this->nowPaymentsSetting('services.nowpayments.success_url', 'NOWPAYMENTS_SUCCESS_URL', url('/payment/success'));
    }

    private function nowPaymentsCancelUrl(): string
    {
        return (string) $this->nowPaymentsSetting('services.nowpayments.cancel_url', 'NOWPAYMENTS_CANCEL_URL', url('/payment/cancel'));
    }

    private function nowPaymentsCreateInvoice(NowPaymentsService $nowPayments, array $payload): array
    {
        if (method_exists($nowPayments, 'createInvoice')) {
            return $nowPayments->createInvoice($payload);
        }

        $apiKey = $this->nowPaymentsSetting('services.nowpayments.api_key', 'NOWPAYMENTS_API_KEY');

        if (! $apiKey) {
            throw new \RuntimeException('NOWPayments API key is not configured.');
        }

        $baseUrl = rtrim((string) $this->nowPaymentsSetting('services.nowpayments.base_url', 'NOWPAYMENTS_BASE_URL', 'https://api.nowpayments.io/v1'), '/');

        $response = Http::baseUrl($baseUrl)
            ->withHeaders(['x-api-key' => $apiKey])
            ->acceptJson()
            ->asJson()
            ->connectTimeout((int) $this->nowPaymentsSetting('services.nowpayments.connect_timeout', 'NOWPAYMENTS_CONNECT_TIMEOUT', 5))
            ->timeout((int) $this->nowPaymentsSetting('services.nowpayments.timeout', 'NOWPAYMENTS_TIMEOUT', 20))
            ->post('invoice', $payload);

        PaymentLogger::info('NOWPayments HTTP response', [
            'gateway' => 'nowpayments',
            'method' => 'POST',
            'uri' => 'invoice',
            'http_status' => $response->status(),
        ]);

        if ($response->failed()) {
            $body = $response->json();

            if (! is_array($body)) {
                $body = $response->body();
            }

            PaymentLogger::warning('NOWPayments error response', [
                'gateway' => 'nowpayments',
                'method' => 'POST',
                'uri' => 'invoice',
                'http_status' => $response->status(),
                'response' => $body,
            ]);

            throw new NowPaymentsRequestException(
                'NOWPayments request failed with HTTP '.$response->status(),
                $response->status(),
                $body
            );
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new \RuntimeException('NOWPayments returned malformed JSON.');
        }

        return $json;
    }

    private function nowPaymentsSetting(string $configKey, string $envKey, mixed $default = null): mixed
    {
        $value = $_ENV[$envKey] ?? $_SERVER[$envKey] ?? getenv($envKey);

        if ($value !== false && $value !== null && $value !== '') {
            return $this->normalizeNowPaymentsEnvValue((string) $value);
        }

        $value = $this->readNowPaymentsEnvFileValue($envKey);

        if ($value !== null && $value !== '') {
            return $value;
        }

        $value = config($configKey);

        return $value !== null && $value !== '' ? $value : $default;
    }

    private function readNowPaymentsEnvFileValue(string $key): ?string
    {
        $path = base_path('.env');

        if (! is_readable($path)) {
            return null;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);

            if (trim($name) === $key) {
                return $this->normalizeNowPaymentsEnvValue($value);
            }
        }

        return null;
    }

    private function normalizeNowPaymentsEnvValue(string $value): string
    {
        $value = trim($value);

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
