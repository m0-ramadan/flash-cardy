<?php
// app/Services/Like4AppService.php

namespace App\Services;

use App\Models\OrderLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Like4AppService
{
    protected $deviceId;
    protected $securityCode;
    protected $email;
    protected $langId;
    protected $baseUrl = 'https://taxes.like4app.com/online';

    public function __construct()
    {
        $this->deviceId = config('services.like4app.device_id');
        $this->securityCode = config('services.like4app.security_code');
        $this->email = config('services.like4app.email');
        $this->langId = config('services.like4app.lang_id', 1);
    }

    /**
     * Get categories from Like4App
     */
    public function getCategories($page = 1, $referenceId = null, $quantity = null, $time = null)
    {
        return $this->makeRequest('categories', [
            'page' => $page,
            'referenceId' => $referenceId,
            'quantity' => $quantity,
            'time' => $time,
        ]);
    }

    /**
     * Get products from Like4App
     */
    /**
     * Get products from Like4App
     */
    public function getProducts($page = 1)
    {
        return $this->makeRequest('products', [
            'page' => (string) $page,
        ], 'POST');
    }

    /**
     * Create order in Like4App
     */
    public function createOrder($productId, $quantity = 1, $referenceId = null)
    {
        $payload = [
            'productId' => (string) $productId,
            'quantity' => (string) $quantity,
            'referenceId' => $referenceId ?: ('ORDER_' . time()),
        ];

        $response = $this->makeRequest('create_order', $payload, 'POST');

        Log::info('Like4App Create Order Response', ['response' => $response]);

        return $response;
    }

    /**
     * Make HTTP request to Like4App API
     */
    protected function makeRequest($endpoint, $data = [], $method = 'GET')
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        $email = strtolower(trim($this->email));
        $time = (string) time();

        $phone = '201007930374';
        $hashKey = '8Tyr4EDw!2sN';

        $baseParams = [
            'deviceId' => (string) $this->deviceId,
            'securityCode' => (string) $this->securityCode,
            'email' => $email,
            'langId' => (string) $this->langId,
        ];

        if ($endpoint === 'create_order') {
            $baseParams['time'] = $time;
            $baseParams['hash'] = hash('sha256', $time . $email . $phone . $hashKey);
        }

        $params = array_merge($baseParams, $data);

        try {
            $startTime = microtime(true);

            if (strtoupper($method) === 'POST') {
                $response = Http::withOptions([
                    'curl' => [
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                    ],
                ])->asMultipart()->post($url, $params);
            } else {
                $response = Http::withOptions([
                    'curl' => [
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                    ],
                ])->get($url, $params);
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $jsonBody = null;
            $rawBody = $response->body();

            try {
                $jsonBody = $response->json();
            } catch (\Throwable $e) {
                $jsonBody = null;
            }

            Log::info('Like4App API Request', [
                'url' => $url,
                'endpoint' => $endpoint,
                'method' => $method,
                'request_params' => array_merge($params, [
                    'securityCode' => '***hidden***',
                    'hash' => isset($params['hash']) ? '***hidden***' : null,
                ]),
                'response_code' => $response->status(),
                'response_json' => $jsonBody,
                'response_body' => $rawBody,
                'duration_ms' => $duration,
            ]);

            return [
                'success' => $response->successful() && is_array($jsonBody),
                'data' => $jsonBody,
                'raw_body' => $rawBody,
                'status_code' => $response->status(),
                'duration' => $duration,
            ];
        } catch (\Throwable $e) {
            Log::error('Like4App API Error', [
                'url' => $url,
                'endpoint' => $endpoint,
                'method' => $method,
                'request_params' => array_merge($params, [
                    'securityCode' => '***hidden***',
                    'hash' => isset($params['hash']) ? '***hidden***' : null,
                ]),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'raw_body' => null,
                'status_code' => 500,
                'duration' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getProductsDirect($page = 1)
    {
        try {
            $url = rtrim($this->baseUrl, '/') . '/products';

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                // 'Cookie' => $this->cookie,
            ])->send('POST', $url, [
                'multipart' => [
                    [
                        'name' => 'email',
                        'contents' => (string) $this->email,
                    ],
                    [
                        'name' => 'langId',
                        'contents' => (string) $this->langId,
                    ],
                    [
                        'name' => 'securityCode',
                        'contents' => (string) $this->securityCode,
                    ],
                    [
                        'name' => 'deviceId',
                        'contents' => (string) $this->deviceId,
                    ],
                    [
                        'name' => 'page',
                        'contents' => (string) $page,
                    ],
                ],
            ]);

            $json = $response->json();

            Log::info('Like4App Products Direct Request', [
                'url' => $url,
                'page' => $page,
                'response_code' => $response->status(),
                'response_json' => $json,
                'response_body' => $response->body(),
            ]);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => 'HTTP request failed',
                    'status_code' => $response->status(),
                    'response' => 0,
                    'data' => [],
                    'raw' => null,
                    'raw_body' => $response->body(),
                ];
            }

            if (!is_array($json)) {
                return [
                    'success' => false,
                    'message' => 'Invalid JSON response',
                    'status_code' => $response->status(),
                    'response' => 0,
                    'data' => [],
                    'raw' => null,
                    'raw_body' => $response->body(),
                ];
            }

            return [
                // المهم هنا: success لا تعتمد على response=1
                'success' => true,
                'message' => $json['message'] ?? null,
                'status_code' => $response->status(),
                'response' => (int) ($json['response'] ?? 0),
                'data' => $json['data'] ?? [],
                'raw' => $json,
                'raw_body' => $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::error('Like4App getProductsDirect error', [
                'page' => $page,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'status_code' => 500,
                'response' => 0,
                'data' => [],
                'raw' => null,
                'raw_body' => null,
            ];
        }
    }
    /**
     * Parse Like4App response and extract serial codes
     */
    public function parseSerialCodes($response)
    {
        $serials = [];

        if (isset($response['serials']) && is_array($response['serials'])) {
            foreach ($response['serials'] as $serial) {
                $serials[] = [
                    'serial_id' => $serial['serialId'] ?? null,
                    'serial_code' => $serial['serialCode'] ?? null,
                    'serial_number' => $serial['serialNumber'] ?? null,
                    'valid_to' => $serial['validTo'] ?? null,
                ];
            }
        }

        return $serials;
    }
}
