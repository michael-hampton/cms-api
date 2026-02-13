<?php

namespace App\Tests\Unit\Services\Billing\Preorder\Validators;

use App\Enums\Subscriptions\BillingPeriod;
use App\Exceptions\Subscriptions\InvalidDeliveryTypeException;
use App\Exceptions\Subscriptions\InvalidSubscriptionPlanException;
use App\Models\SubscriptionPlan;
use App\Services\Subscriptions\Validators\OneTimePlanValidator;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class OneTimePlanValidatorTest extends TestCase
{
    private OneTimePlanValidator $validator;

    public function testValidatePlanForSubscriptionSuccess(): void
    {
        $plan = m::mock(SubscriptionPlan::class);
        $plan->shouldReceive('isOneTime')->andReturn(true);
        $plan->shouldReceive('hasDigitalOption')->andReturn(true);

        $this->validator->validatePlanForSubscription($plan, 'digital');

        $this->assertTrue(true); // No exception thrown
    }

    public function testValidatePlanForSubscriptionThrowsForNonOneTimePlan(): void
    {
        $plan = m::mock(SubscriptionPlan::class);
        $plan->shouldReceive('isOneTime')->andReturn(false);

        $this->expectException(InvalidSubscriptionPlanException::class);
        $this->expectExceptionMessage('Invalid one-time subscription plan');

        $this->validator->validatePlanForSubscription($plan, 'digital');
    }

    public function testValidatePlanForSubscriptionThrowsForNullPlan(): void
    {
        $this->expectException(InvalidSubscriptionPlanException::class);
        $this->expectExceptionMessage('Invalid one-time subscription plan');

        $this->validator->validatePlanForSubscription(null, 'digital');
    }

    public function testValidateDeliveryTypeThrowsForDigitalWhenNotAvailable(): void
    {
        $plan = m::mock(SubscriptionPlan::class);
        $plan->shouldReceive('isOneTime')->andReturn(true);
        $plan->shouldReceive('hasDigitalOption')->andReturn(false);

        $this->expectException(InvalidDeliveryTypeException::class);
        $this->expectExceptionMessage('Digital delivery not available for this plan');

        $this->validator->validatePlanForSubscription($plan, 'digital');
    }

    public function testValidateDeliveryTypeThrowsForPrintWhenNotAvailable(): void
    {
        $plan = m::mock(SubscriptionPlan::class);
        $plan->shouldReceive('isOneTime')->andReturn(true);
        $plan->shouldReceive('hasPrintOption')->andReturn(false);

        $this->expectException(InvalidDeliveryTypeException::class);
        $this->expectExceptionMessage('Print delivery not available for this plan');

        $this->validator->validatePlanForSubscription($plan, 'print');
    }

    public function testValidateBillingPeriodReturnsEnum(): void
    {
        $result = $this->validator->validateBillingPeriod('monthly');

        $this->assertInstanceOf(BillingPeriod::class, $result);
        $this->assertEquals(BillingPeriod::MONTHLY, $result);
    }

    public function testValidateBillingPeriodThrowsForInvalidPeriod(): void
    {
        $this->expectException(InvalidSubscriptionPlanException::class);
        $this->expectExceptionMessage('Invalid billing period: invalid');

        $this->validator->validateBillingPeriod('invalid');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new OneTimePlanValidator();
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}