<?php

namespace App\Services\Payment;

use App\Exceptions\NowPaymentsRequestException;
use App\Support\PaymentLogger;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NowPaymentsService
{
    public function status(): array
    {
        return $this->get('status');
    }

    public function getAvailableCurrencies(): array
    {
        return Cache::remember('nowpayments.currencies', now()->addMinutes(10), function () {
            $response = $this->get('currencies');

            return array_values(array_map(
                fn ($currency) => strtolower((string) $currency),
                $response['currencies'] ?? []
            ));
        });
    }

    public function getMinimumAmount(string $currencyFrom, string $currencyTo, string $fiatEquivalent): array
    {
        return $this->get('min-amount', [
            'currency_from' => strtolower($currencyFrom),
            'currency_to' => strtolower($currencyTo),
            'fiat_equivalent' => strtolower($fiatEquivalent),
            'is_fixed_rate' => $this->boolParam($this->isFixedRate()),
            'is_fee_paid_by_user' => $this->boolParam($this->isFeePaidByUser()),
        ]);
    }

    public function getEstimate(string $amount, string $currencyFrom, string $currencyTo): array
    {
        return $this->get('estimate', [
            'amount' => $amount,
            'currency_from' => strtolower($currencyFrom),
            'currency_to' => strtolower($currencyTo),
        ]);
    }

    public function createPayment(array $payload): array
    {
        return $this->post('payment', $payload);
    }

    public function createInvoice(array $payload): array
    {
        return $this->post('invoice', $payload);
    }

    public function getPaymentStatus(string $paymentId): array
    {
        return $this->get("payment/{$paymentId}");
    }

    public function verifyIpnSignature(array $payload, ?string $receivedSignature): bool
    {
        $secret = $this->ipnSecret();

        if (! $receivedSignature || ! $secret) {
            return false;
        }

        $this->recursiveKeySort($payload);
        $canonicalJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $expected = hash_hmac('sha512', $canonicalJson, $secret);

        return hash_equals($expected, $receivedSignature);
    }

    public function normalizeStatus(string $providerStatus): string
    {
        return match (strtolower($providerStatus)) {
            'waiting' => 'waiting',
            'confirming' => 'confirming',
            'confirmed' => 'confirmed',
            'sending' => 'sending',
            'partially_paid' => 'partially_paid',
            'finished' => 'paid',
            'failed' => 'failed',
            'expired' => 'expired',
            'refunded' => 'refunded',
            default => 'unknown',
        };
    }

    public function assertConfigured(): void
    {
        if (! $this->apiKey()) {
            throw new RuntimeException('NOWPayments API key is not configured.');
        }

        if (! $this->ipnSecret()) {
            throw new RuntimeException('NOWPayments IPN secret is not configured.');
        }
    }

    public function isFixedRate(): bool
    {
        return $this->boolValue($this->setting('services.nowpayments.is_fixed_rate', 'NOWPAYMENTS_FIXED_RATE', false));
    }

    public function isFeePaidByUser(): bool
    {
        return $this->boolValue($this->setting('services.nowpayments.is_fee_paid_by_user', 'NOWPAYMENTS_FEE_PAID_BY_USER', false));
    }

    public function storeCurrency(): string
    {
        return strtoupper((string) $this->setting('services.nowpayments.store_currency', 'NOWPAYMENTS_STORE_CURRENCY', 'EGP'));
    }

    public function priceCurrency(): string
    {
        return strtolower((string) $this->setting('services.nowpayments.price_currency', 'NOWPAYMENTS_PRICE_CURRENCY', 'usd'));
    }

    public function payoutCurrency(): string
    {
        return strtolower((string) $this->setting('services.nowpayments.payout_currency', 'NOWPAYMENTS_PAYOUT_CURRENCY', 'usdttrc20'));
    }

    public function defaultPayCurrency(): string
    {
        return strtolower((string) $this->setting('services.nowpayments.default_pay_currency', 'NOWPAYMENTS_DEFAULT_PAY_CURRENCY', 'usdttrc20'));
    }

    public function allowedPayCurrencies(): array
    {
        $value = $this->setting('services.nowpayments.allowed_pay_currencies', 'NOWPAYMENTS_ALLOWED_PAY_CURRENCIES', 'usdttrc20,usdtbsc');

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

    private function get(string $uri, array $query = []): array
    {
        return $this->send('get', $uri, $query, true);
    }

    private function post(string $uri, array $payload = []): array
    {
        return $this->send('post', $uri, $payload, false);
    }

    private function send(string $method, string $uri, array $data, bool $safeToRetry): array
    {
        $this->assertConfigured();
        $startedAt = microtime(true);

        try {
            $request = $this->client();

            if ($safeToRetry) {
                $request = $request->retry(2, 200);
            }

            $response = $method === 'get'
                ? $request->get($uri, $data)
                : $request->post($uri, $data);

            PaymentLogger::info('NOWPayments HTTP response', [
                'gateway' => 'nowpayments',
                'method' => strtoupper($method),
                'uri' => $uri,
                'http_status' => $response->status(),
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);

            if ($response->failed()) {
                $body = $response->json();

                if (! is_array($body)) {
                    $body = $response->body();
                }

                PaymentLogger::warning('NOWPayments error response', [
                    'gateway' => 'nowpayments',
                    'method' => strtoupper($method),
                    'uri' => $uri,
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
                throw new RuntimeException('NOWPayments returned malformed JSON.');
            }

            return $json;
        } catch (ConnectionException $e) {
            PaymentLogger::warning('NOWPayments connection failed', [
                'gateway' => 'nowpayments',
                'method' => strtoupper($method),
                'uri' => $uri,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('تعذر الاتصال ببوابة NOWPayments حاليًا.');
        } catch (RuntimeException $e) {
            PaymentLogger::warning('NOWPayments request failed', [
                'gateway' => 'nowpayments',
                'method' => strtoupper($method),
                'uri' => $uri,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->setting('services.nowpayments.base_url', 'NOWPAYMENTS_BASE_URL', 'https://api.nowpayments.io/v1'), '/'))
            ->withHeaders(['x-api-key' => $this->apiKey()])
            ->acceptJson()
            ->asJson()
            ->connectTimeout((int) $this->setting('services.nowpayments.connect_timeout', 'NOWPAYMENTS_CONNECT_TIMEOUT', 5))
            ->timeout((int) $this->setting('services.nowpayments.timeout', 'NOWPAYMENTS_TIMEOUT', 20));
    }

    private function apiKey(): ?string
    {
        return $this->setting('services.nowpayments.api_key', 'NOWPAYMENTS_API_KEY');
    }

    private function ipnSecret(): ?string
    {
        return $this->setting('services.nowpayments.ipn_secret', 'NOWPAYMENTS_IPN_SECRET');
    }

    private function setting(string $configKey, string $envKey, mixed $default = null): mixed
    {
        if (app()->runningUnitTests()) {
            $value = config($configKey);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        $value = $_ENV[$envKey] ?? $_SERVER[$envKey] ?? getenv($envKey);

        if ($value !== false && $value !== null && $value !== '') {
            return $this->normalizeEnvValue((string) $value);
        }

        $value = $this->readEnvFileValue($envKey);

        if ($value !== null && $value !== '') {
            return $value;
        }

        $value = config($configKey);

        return $value !== null && $value !== '' ? $value : $default;
    }

    private function readEnvFileValue(string $key): ?string
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
                return $this->normalizeEnvValue($value);
            }
        }

        return null;
    }

    private function normalizeEnvValue(string $value): string
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

    private function recursiveKeySort(array &$payload): void
    {
        ksort($payload);

        foreach ($payload as &$value) {
            if (is_array($value)) {
                $this->recursiveKeySort($value);
            }
        }
    }

    private function boolParam(mixed $value): string
    {
        return $this->boolValue($value) ? 'true' : 'false';
    }

    private function boolValue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
