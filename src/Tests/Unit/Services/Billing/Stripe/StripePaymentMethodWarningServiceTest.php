<?php

namespace App\Tests\Unit\Services\Billing\Stripe;

use App\DTO\Billing\PaymentMethodDto;
use App\Services\Billing\Stripe\StripePaymentMethodWarningService;
use PHPUnit\Framework\TestCase;

class StripePaymentMethodWarningServiceTest extends TestCase
{
    public function test_get_payment_methods_with_warnings_marks_expired_cards(): void
    {
        $service = new StripePaymentMethodWarningService();
        $expiredCard = new PaymentMethodDto(
            id: 'pm_expired',
            type: 'card',
            brand: 'visa',
            last4: '0000',
            expMonth: 1,
            expYear: 2020,
        );

        $result = $service->getPaymentMethodsWithWarnings([
            'success' => true,
            'payment_methods' => [$expiredCard],
            'default_payment_method_id' => null,
        ]);

        $this->assertTrue($result['has_warnings']);
        $this->assertSame('expired', $result['warnings'][0]['status']);
    }

    public function test_get_payment_methods_with_warnings_preserves_failed_lookup_result(): void
    {
        $service = new StripePaymentMethodWarningService();

        $result = $service->getPaymentMethodsWithWarnings([
            'success' => false,
            'message' => 'Failed to fetch payment methods',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('Failed to fetch payment methods', $result['message']);
    }
}
