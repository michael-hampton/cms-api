<?php

namespace App\Tests\Unit\Services\Billing\Preorder\Calculators;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Subscriptions\Calculators\UpgradeProrationCalculator;
use App\Tests\Unit\UnitTestCase;
use Mockery;

class UpgradeProrationCalculatorTest extends UnitTestCase
{
    private UpgradeProrationCalculator $calculator;

    public function testCalculateUpgradeQuoteWithoutProration(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->price = 19.99;
        $subscription->currency = 'USD';
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->price = 39.99;

        $quote = $this->calculator->calculateUpgradeQuote($subscription, $upgradePlan);

        $this->assertEquals(20.00, $quote->getAmount()->toDecimal());
        $this->assertFalse($quote->isProrated());
        $this->assertNull($quote->getRemainingDays());
        $this->assertFalse($quote->isEstimate());
    }

    public function testCalculateUpgradeQuoteWithProration(): void
    {
        $startDate = new \DateTime('-20 days');
        $nextBilling = new \DateTime('+10 days');

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->price = 30.00;
        $subscription->currency = 'USD';
        $subscription->start_date = $startDate;
        $subscription->next_billing_date = $nextBilling;
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(true);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->price = 60.00;

        $quote = $this->calculator->calculateUpgradeQuote($subscription, $upgradePlan);

        // Price difference is $30, prorated to 10/30 days = $10
        $this->assertLessThan(15.00, $quote->getAmount()->toDecimal());
        $this->assertGreaterThan(5.00, $quote->getAmount()->toDecimal());
        $this->assertTrue($quote->isProrated());
        $this->assertEquals(9, $quote->getRemainingDays());
        $this->assertTrue($quote->isEstimate()); // Stripe subscriptions are estimates
    }

    public function testCalculateUpgradeQuoteReturnsZeroForNegativeDifference(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->price = 39.99;
        $subscription->currency = 'USD';
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->price = 19.99; // Lower price

        $quote = $this->calculator->calculateUpgradeQuote($subscription, $upgradePlan);

        $this->assertEquals(0, $quote->getAmount()->toDecimal());
        $this->assertFalse($quote->isProrated());
    }

    public function testCalculateUpgradeQuoteThrowsForCurrencyMismatch(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->price = 19.99;
        $subscription->currency = 'USD';
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);

        $upgradePlan = new SubscriptionPlan(['price' => 39.99, 'currency' => 'EUR']);
        $upgradePlan->price = 39.99;
        $upgradePlan->currency = 'EUR';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency mismatch');

        $this->calculator->calculateUpgradeQuote($subscription, $upgradePlan);
    }

    public function testCalculateUpgradeQuoteThrowsForPastBillingDate(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->price = 30.00;
        $subscription->currency = 'USD';
        $subscription->start_date = new \DateTime('-60 days');
        $subscription->next_billing_date = new \DateTime('-10 days'); // Past date
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(true);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->price = 60.00;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Next billing date is in the past');

        $this->calculator->calculateUpgradeQuote($subscription, $upgradePlan);
    }

    public function testCalculateUpgradeQuoteThrowsForInvalidDateRange(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->price = 30.00;
        $subscription->currency = 'USD';
        $subscription->start_date = new \DateTime('+30 days'); // Future start
        $subscription->next_billing_date = new \DateTime('+10 days'); // Before start
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(true);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->price = 60.00;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Start date cannot be after next billing date');

        $this->calculator->calculateUpgradeQuote($subscription, $upgradePlan);
    }

    public function testCalculateUpgradeQuoteHandlesSameDayUpgrade(): void
    {
        $now = new \DateTime();
        $nextBilling = new \DateTime('+30 days');

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->price = 30.00;
        $subscription->currency = 'USD';
        $subscription->start_date = $now;
        $subscription->next_billing_date = $nextBilling;
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(true);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->price = 60.00;

        $quote = $this->calculator->calculateUpgradeQuote($subscription, $upgradePlan);

        // Should calculate full proration
        $this->assertGreaterThan(0, $quote->getAmount()->toDecimal());
        $this->assertTrue($quote->isProrated());
        $this->assertTrue($quote->isEstimate());
    }

    public function testCalculateUpgradeQuoteMarksNonStripeAsNotEstimate(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->price = 19.99;
        $subscription->currency = 'USD';
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->price = 39.99;

        $quote = $this->calculator->calculateUpgradeQuote($subscription, $upgradePlan);

        $this->assertFalse($quote->isEstimate());
    }

    public function testCalculateUpgradeQuoteWithZeroRemainingDays(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->price = 30.00;
        $subscription->currency = 'USD';
        $subscription->start_date = new \DateTime('-30 days');
        $subscription->next_billing_date = new \DateTime('today'); // 0 days remaining
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(true);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->price = 60.00;

        $quote = $this->calculator->calculateUpgradeQuote($subscription, $upgradePlan);

        // With 0 days remaining, proration should result in 0 charge
        $this->assertEquals(0, $quote->getAmount()->toDecimal());
    }

    protected function setUp(): void
    {
        $this->calculator = new UpgradeProrationCalculator();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}