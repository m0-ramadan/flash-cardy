<?php

namespace Tests\Unit;

use App\Services\Payment\NowPaymentsService;
use Tests\TestCase;

class NowPaymentsServiceTest extends TestCase
{
    public function test_it_maps_finished_as_paid_only_final_success_status(): void
    {
        $service = new NowPaymentsService;

        $this->assertSame('paid', $service->normalizeStatus('finished'));
        $this->assertSame('confirmed', $service->normalizeStatus('confirmed'));
        $this->assertSame('partially_paid', $service->normalizeStatus('partially_paid'));
        $this->assertSame('unknown', $service->normalizeStatus('mystery'));
    }

    public function test_it_verifies_ipn_signature_with_recursive_key_sorting(): void
    {
        config(['services.nowpayments.ipn_secret' => 'test-secret']);

        $payload = [
            'payment_status' => 'finished',
            'nested' => [
                'z' => 'last',
                'a' => 'first',
            ],
            'payment_id' => 123,
        ];

        $canonical = [
            'nested' => [
                'a' => 'first',
                'z' => 'last',
            ],
            'payment_id' => 123,
            'payment_status' => 'finished',
        ];

        $signature = hash_hmac(
            'sha512',
            json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'test-secret'
        );

        $service = new NowPaymentsService;

        $this->assertTrue($service->verifyIpnSignature($payload, $signature));
        $this->assertFalse($service->verifyIpnSignature($payload, 'bad-signature'));
    }
}
