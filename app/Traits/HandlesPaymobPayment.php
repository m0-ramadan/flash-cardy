<?php

namespace App\Traits;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

trait HandlesPaymobPayment
{
    /**
     * بدء عملية الدفع عبر Paymob Egypt Unified Checkout.
     */
    public function initiatePaymobPayment($order)
    {
        $intention = $this->createPaymobIntention($order);

        if (! $intention['success']) {
            return [
                'success' => false,
                'message' => $intention['message'] ?? 'فشل إنشاء عملية الدفع',
                'error_details' => $intention,
            ];
        }

        $paymentUrl = $this->buildPaymobUnifiedCheckoutUrl($intention['client_secret']);

        $order->update([
            'transaction_id' => $intention['intention_id'] ?? null,
            'status_payment' => 'pending',
            'provider_response' => $intention['provider_response'] ?? null,
        ]);

        return [
            'success' => true,
            'payment_url' => $paymentUrl,
            'shorten_url' => null,
            'order_number' => $order->order_number,
            'client_secret' => $intention['client_secret'],
            'intention_id' => $intention['intention_id'] ?? null,
            'message' => 'تم إنشاء رابط الدفع بنجاح',
        ];
    }

    /**
     * تسجيل الدخول وجلب التوكن - kept for old API compatibility.
     */
    private function paymobAuthenticate(): array
    {
        $apiKey = $this->paymobSetting('api_key', 'PAYMOB_API_KEY');

        if (! $apiKey) {
            return [
                'success' => false,
                'message' => 'Paymob API key is not configured.',
            ];
        }

        $response = Http::asJson()->post($this->paymobUrl('/api/auth/tokens'), [
            'api_key' => $apiKey,
        ]);

        if ($response->failed()) {
            return [
                'success' => false,
                'message' => 'فشل تسجيل الدخول في PayMob',
                'error'   => $response->json(),
            ];
        }

        return [
            'success' => true,
            'token' => $response->json('token'),
        ];
    }

    /**
     * إنشاء Intention للبوابة المصرية ثم استخدام client_secret في Unified Checkout.
     */
    private function createPaymobIntention(Order $order): array
    {
        $secretKey = $this->paymobSetting('secret_key', 'PAYMOB_SECRET_KEY');
        $publicKey = $this->paymobSetting('public_key', 'PAYMOB_PUBLIC_KEY');
        $integrationId = $this->paymobSetting('integration_id', 'PAYMOB_INTEGRATION_ID');

        if (! $secretKey || ! $publicKey || ! $integrationId) {
            return [
                'success' => false,
                'message' => 'Paymob secret key, public key, or integration id is not configured.',
            ];
        }

        $amountInCents = max(100, (int) round(((float) $order->total_amount) * 100));
        $currency = strtoupper((string) $this->paymobSetting('currency', 'PAYMOB_CURRENCY', 'EGP'));
        $customerName = trim((string) ($order->customer_name ?: 'Flashicard Customer'));
        [$firstName, $lastName] = $this->splitPaymobName($customerName);

        $payload = [
            'amount' => $amountInCents,
            'currency' => $currency,
            'payment_methods' => [(int) $integrationId],
            'items' => [[
                'name' => 'Order '.$order->order_number,
                'amount' => $amountInCents,
                'description' => 'Flashicard order '.$order->order_number,
                'quantity' => 1,
            ]],
            'billing_data' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $order->customer_email ?: 'customer@example.com',
                'phone_number' => $this->normalizePaymobPhone($order->customer_phone),
                'country' => 'EG',
                'city' => 'Cairo',
                'state' => 'Cairo',
                'postal_code' => '00000',
                'street' => 'NA',
                'building' => 'NA',
                'floor' => 'NA',
                'apartment' => 'NA',
            ],
            'customer' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $order->customer_email ?: 'customer@example.com',
                'extras' => [
                    'order_number' => $order->order_number,
                ],
            ],
            'extras' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ],
            'special_reference' => $order->order_number,
            'notification_url' => $this->paymobCallbackUrl(),
            'redirection_url' => $this->paymobReturnUrl($order),
            'expiration' => 3600,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Token '.$secretKey,
            'Content-Type' => 'application/json',
        ])->acceptJson()->asJson()->post($this->paymobUrl('/v1/intention/'), $payload);

        if ($response->failed()) {
            return [
                'success' => false,
                'message' => 'فشل إنشاء نية الدفع في Paymob',
                'http_status' => $response->status(),
                'request' => $payload,
                'error' => $response->json() ?: $response->body(),
            ];
        }

        $data = $response->json();

        if (! is_array($data) || empty($data['client_secret'])) {
            return [
                'success' => false,
                'message' => 'استجابة Paymob لا تحتوي على client_secret',
                'provider_response' => $data,
            ];
        }

        return [
            'success' => true,
            'client_secret' => $data['client_secret'],
            'intention_id' => $data['id'] ?? $data['intention_order_id'] ?? null,
            'provider_response' => $data,
        ];
    }

    private function createPaymobPaymentLink($token, $order)
    {
        return $this->createPaymobIntention($order);
    }

    private function buildPaymobUnifiedCheckoutUrl(string $clientSecret): string
    {
        return $this->paymobUrl('/unifiedcheckout/').'?'.http_build_query([
            'publicKey' => $this->paymobSetting('public_key', 'PAYMOB_PUBLIC_KEY'),
            'clientSecret' => $clientSecret,
        ]);
    }

    private function paymobCallbackUrl(): string
    {
        return (string) ($this->paymobSetting('callback_url', 'PAYMOB_CALLBACK_URL') ?: url('/api/paymob/webhook'));
    }

    private function paymobReturnUrl(Order $order): string
    {
        $baseUrl = (string) ($this->paymobSetting('return_url', 'PAYMOB_RETURN_URL') ?: url('/payment-status'));

        return $baseUrl.(str_contains($baseUrl, '?') ? '&' : '?').http_build_query([
            'orderId' => $order->order_number,
        ]);
    }

    private function paymobUrl(string $path): string
    {
        $baseUrl = rtrim((string) $this->paymobSetting('base_url', 'PAYMOB_BASE_URL', 'https://accept.paymob.com'), '/');

        return $baseUrl.'/'.ltrim($path, '/');
    }

    private function splitPaymobName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2) ?: [];

        return [
            $parts[0] ?? 'Customer',
            $parts[1] ?? 'Flashicard',
        ];
    }

    private function normalizePaymobPhone(?string $phone): string
    {
        $phone = preg_replace('/[^\d+]/', '', (string) $phone);

        return $phone !== '' ? $phone : '01000000000';
    }

    private function paymobSetting(string $configKey, string $envKey, mixed $default = null): mixed
    {
        $value = $_ENV[$envKey] ?? $_SERVER[$envKey] ?? getenv($envKey);

        if ($value !== false && $value !== null && $value !== '') {
            return $this->normalizePaymobEnvValue((string) $value);
        }

        $value = $this->readPaymobEnvFileValue($envKey);

        if ($value !== null && $value !== '') {
            return $value;
        }

        $value = config('services.paymob.'.$configKey);

        return $value !== null && $value !== '' ? $value : $default;
    }

    private function readPaymobEnvFileValue(string $key): ?string
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
                return $this->normalizePaymobEnvValue($value);
            }
        }

        return null;
    }

    private function normalizePaymobEnvValue(string $value): string
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

    /**
     * التحقق من HMAC للـ Webhook
     */
    public function verifyPaymobHmac(array $payload): bool
    {
        $hmac = $payload['hmac'] ?? '';
        unset($payload['hmac']);

        ksort($payload);
        $concatenated = collect($payload)->flatten()->implode('');
        $secret = config('services.paymob.hmac_secret');

        return hash_equals(hash_hmac('sha512', $concatenated, $secret), $hmac);
    }

    /**
     * معالجة Webhook من PayMob
     */
    public function handlePaymobWebhook(Request $request)
    {
        if (!$this->verifyPaymobHmac($request->all())) {
            return response('Invalid HMAC', 400);
        }

        $type = $request->input('type');
        $obj  = $request->input('obj');

        // دفع ناجح + تم السحب (Capture)
        if ($type === 'TRANSACTION' && $obj['success'] === true && $obj['is_capture'] === true) {

            // الحقل الصح في KSA هو merchant_reference
            $orderNumber = $obj['merchant_reference'] ?? null;

            if (!$orderNumber) {
                \Illuminate\Support\Facades\Log::error('PayMob Webhook: No merchant_reference', $obj);
                return response('No reference', 400);
            }

            $order = Order::where('order_number', $orderNumber)->first();

            if ($order && $order->status === 'pending') {
                $order->update([
                    'status'         => 'paid',
                    'payment_method' => 'paymob',
                    'transaction_id' => $obj['id'],
                    'paid_at'         => now(),
                ]);

                // هنا نفذ كل حاجة: تفريغ المخزون، إرسال إيميل، إشعار واتساب، إلخ
            }
        }

        return response('OK', 200);
    }
}
