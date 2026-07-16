<?php

namespace App\Tests\Unit\Services\Billing\Stripe;

use App\DTO\Billing\PaymentMethodDto;
use App\Enums\Billing\PaymentMethodStatus;
use App\Services\Billing\Stripe\StripePaymentMethodWarningService;
use PHPUnit\Framework\TestCase;

class StripePaymentMethodWarningServiceStatusTest extends TestCase
{
    private StripePaymentMethodWarningService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StripePaymentMethodWarningService();
    }

    public function test_status_for_active_card(): void
    {
        $farFuture = (int) date('Y') + 5;
        $card = new PaymentMethodDto('pm_1', 'card', 'visa', '4242', 1, $farFuture);

        $this->assertSame(PaymentMethodStatus::Active, $this->service->statusFor($card));
    }

    public function test_status_for_expired_card(): void
    {
        $card = new PaymentMethodDto('pm_1', 'card', 'visa', '4242', 1, 2020);

        $this->assertSame(PaymentMethodStatus::Expired, $this->service->statusFor($card));
        $this->assertTrue(PaymentMethodStatus::Expired->requiresUpdateAction());
    }

    public function test_status_for_expiring_soon_card(): void
    {
        $nextMonth = (int) date('n') + 1;
        $year = (int) date('Y');

        if ($nextMonth > 12) {
            $nextMonth = 1;
            $year++;
        }

        $card = new PaymentMethodDto('pm_1', 'card', 'visa', '4242', $nextMonth, $year);

        $this->assertSame(PaymentMethodStatus::ExpiringSoon, $this->service->statusFor($card));
        $this->assertTrue(PaymentMethodStatus::ExpiringSoon->requiresUpdateAction());
    }

    public function test_active_status_does_not_require_update_action(): void
    {
        $this->assertFalse(PaymentMethodStatus::Active->requiresUpdateAction());
        $this->assertSame('Active', PaymentMethodStatus::Active->label());
        $this->assertSame('active', PaymentMethodStatus::Active->value);
    }
}
