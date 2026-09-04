<?php

namespace Tests\Unit;

use App\Models\PaymentMethod;
use Tests\TestCase;

class PaymentMethodIconTest extends TestCase
{
    public function test_fontawesome_icon_does_not_generate_storage_url(): void
    {
        $method = new PaymentMethod(['icon' => 'fas fa-coins']);

        $this->assertTrue($method->hasFontAwesomeIcon());
        $this->assertSame('fontawesome', $method->icon_type);
        $this->assertNull($method->icon_url);
    }

    public function test_image_icon_generates_icon_url(): void
    {
        $method = new PaymentMethod(['icon' => 'uploaded-file.png']);

        $this->assertFalse($method->hasFontAwesomeIcon());
        $this->assertSame('image', $method->icon_type);
        $this->assertStringContainsString('payment-methods/uploaded-file.png', $method->icon_url);
    }
}
