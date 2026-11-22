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
use App\Services\Payment\PaymobWebhookService;
use App\Services\Orders\OrderCreationService;
use App\Services\Orders\OrderLifecycleService;
use App\Services\Coupons\CouponReservationService;
use App\Services\Inventory\InventoryService;
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
    public function store(CreateOrderRequest $request, OrderCreationService $creator)
    {
        $paymentMethod = PaymentMethod::query()
            ->whereKey($request->integer('payment_method'))
            ->where('is_active', true)->where('is_payment', true)->first();
        if (!$paymentMethod) return $this->errorResponse('طريقة الدفع غير متاحة', 422);
        try {
            [$order, $paymentTransaction] = $creator->create($request, $paymentMethod);
        } catch (\Throwable $e) {
            PaymentLogger::warning('Order creation rejected', ['user_id'=>$request->user()?->id,'error_class'=>$e::class]);
            return $this->errorResponse($e instanceof \Illuminate\Validation\ValidationException ? $e->errors() : $e->getMessage(), 422);
        }
        if ($paymentMethod->key === 'paymob') {
            $payment = $this->initiatePaymobPayment($order, $paymentTransaction);
            if (empty($payment['success'])) {
                $paymentTransaction->forceFill(['status'=>'failed','failure_reason'=>$payment['message']??'Paymob initiation failed','failed_at'=>now()])->save();
                app(InventoryService::class)->releaseOrder($order,'paymob_initiation_failed'); app(\App\Services\Fulfillment\LocalSerialFulfillmentService::class)->releaseOrder($order,'paymob_initiation_failed'); app(CouponReservationService::class)->release($order);
                return $this->errorResponse('تعذر إنشاء عملية الدفع عبر Paymob حاليًا', 422);
            }
            return $this->successResponse(['payment_url'=>$payment['payment_url'],'shorten_url'=>null,'client_secret'=>$payment['client_secret']??null,'intention_id'=>$payment['intention_id']??null,'order_number'=>$order->order_number,'payment'=>['gateway'=>'paymob','payment_type'=>'redirect','payment_url'=>$payment['payment_url'],'client_secret'=>$payment['client_secret']??null,'intention_id'=>$payment['intention_id']??null]],'جاري توجيهك إلى بوابة الدفع الآمنة...',201);
        }
        if ($paymentMethod->key === 'nowpayments') return $this->initiateNowPaymentsPayment($request,$order,$paymentTransaction);
        $paymentTransaction->forceFill(['status'=>'pending','provider_status'=>'awaiting_manual_payment'])->save();
        return $this->successResponse(new OrderDetailsResource($order->load(['items.product'])), 'تم إنشاء الطلب بنجاح', 201);
    }

    /**
     * إلغاء الطلب (فقط إذا كان pending أو processing)
     */
    public function cancelled($codeOrder, Request $request, OrderLifecycleService $lifecycle)
    {
        $order = Order::where('order_number',$codeOrder)->where('user_id',$request->user()->id)->firstOrFail();
        try { $order = $lifecycle->transition($order,'cancelled',null,'customer_cancelled'); app(CouponReservationService::class)->release($order); }
        catch (\Throwable $e) { return $this->errorResponse($e->getMessage(), 422); }
        return $this->successResponse(new OrderDetailsResource($order),'تم إلغاء الطلب بنجاح');
    }

    /**
     * تتبع الطلب برقم الطلب (للمسجلين والزوار)
     */
    public function traceOrder($codeOrder, Request $request)
    {
        $order = Order::with(['items.product'])->where('order_number',$codeOrder)->where('user_id',$request->user()->id)->firstOrFail();
        return $this->successResponse(new OrderDetailsResource($order),'تم جلب تفاصيل الطلب');
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

    public function webhook(Request $request, PaymobWebhookService $webhooks)
    {
        $received = $request->header('X-Paymob-Hmac-Signature') ?? $request->query('hmac') ?? $request->input('hmac');
        if (!$webhooks->verify($request->all(), $received)) { PaymentLogger::warning('Invalid Paymob webhook HMAC',['ip'=>$request->ip()]); return response('Invalid HMAC',401); }
        try { $webhooks->process($request->all()); } catch (\Throwable $e) { PaymentLogger::warning('Paymob webhook processing failed',['error_class'=>$e::class]); return response('Processing error',500); }
        return response('OK',200);
    }

    public function paymentMethods(Request $request)
    {
        $query=PaymentMethod::query()->where('is_active',true);
        if ($request->has('is_payment')) { $value=filter_var($request->input('is_payment'),FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE); if($value!==null)$query->where('is_payment',$value); }
        return $this->successResponse(PaymentMethodResource::collection($query->orderBy('id')->get()),'تم جلب طرق الدفع بنجاح');
    }

    public function paymentStatus(Request $request)
    {
        $orderId=$request->query('orderId'); if(!$orderId)return $this->errorResponse('بيانات غير مكتملة',400);
        $query=Order::where('user_id',$request->user()->id); $order=is_numeric($orderId)?(clone $query)->whereKey((int)$orderId)->first():(clone $query)->where('order_number',$orderId)->first();
        if(!$order)return $this->errorResponse('الطلب غير موجود',404); $payment=$order->latestPaymentTransaction;
        return $this->successResponse(['order_id'=>$order->id,'order_number'=>$order->order_number,'order_status'=>$order->order_state?:$order->status,'payment_status'=>$order->payment_state?:$order->status_payment,'fulfillment_status'=>$order->fulfillment_status,'gateway'=>$payment?->gateway,'payment_id'=>$payment?->provider_transaction_id?:$payment?->provider_payment_id,'payment_url'=>$payment?->metadata['invoice_url']??$payment?->metadata['payment_url']??null,'pay_currency'=>$payment?->pay_currency,'pay_amount'=>$payment?->pay_amount,'actually_paid'=>$payment?->actually_paid,'requires_manual_review'=>(bool)($payment?->requires_manual_review??false),'paid_at'=>$order->paid_at?->toIso8601String(),'expires_at'=>$payment?->expires_at?->toIso8601String(),'total'=>$order->total_amount,'currency'=>$order->currency?:config('commerce.base_currency','EGP')],'تم جلب حالة الدفع');
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
