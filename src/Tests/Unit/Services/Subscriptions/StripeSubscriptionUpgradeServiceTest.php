<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Exceptions\Subscriptions\MissingStripePriceException;
use App\Exceptions\Subscriptions\StripeUpdateFailedException;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use App\Services\Subscriptions\StripeSubscriptionUpgradeService;
use Mockery;
use PHPUnit\Framework\TestCase;

class StripeSubscriptionUpgradeServiceTest extends TestCase
{
    private StripeSubscriptionUpgradeService $service;
    private $stripeProcessor;

    public function testUpdateSubscriptionPlanSuccess(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->plan_id = 1;
        $subscription->shouldReceive('getStripeSubscriptionId')->andReturn('sub_123');

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium Plan';
        $upgradePlan->stripe_price_id = 'price_abc123';

        $this->stripeProcessor->shouldReceive('updateSubscriptionPlan')
            ->with('sub_123', 'price_abc123', Mockery::type('array'))
            ->once()
            ->andReturn(['success' => true]);

        $this->service->updateSubscriptionPlan($subscription, $upgradePlan);

        $this->assertTrue(true); // No exception thrown
    }

    public function testUpdateSubscriptionPlanThrowsForMissingStripePriceId(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('getStripeSubscriptionId')->andReturn('sub_123');

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->name = 'Premium Plan';
        $upgradePlan->stripe_price_id = null; // Missing!

        $this->expectException(MissingStripePriceException::class);
        $this->expectExceptionMessage("Cannot upgrade: Plan 'Premium Plan' is missing Stripe price ID");

        $this->service->updateSubscriptionPlan($subscription, $upgradePlan);
    }

    public function testUpdateSubscriptionPlanThrowsWhenStripeUpdateFails(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('getStripeSubscriptionId')->andReturn('sub_123');

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->stripe_price_id = 'price_abc123';

        $this->stripeProcessor->shouldReceive('updateSubscriptionPlan')
            ->once()
            ->andReturn(['success' => false, 'error' => 'Card declined']);

        $this->expectException(StripeUpdateFailedException::class);
        $this->expectExceptionMessage('Failed to update Stripe subscription: Card declined');

        $this->service->updateSubscriptionPlan($subscription, $upgradePlan);
    }

    public function testUpdateSubscriptionPlanThrowsWhenStripeReturnsUnknownError(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('getStripeSubscriptionId')->andReturn('sub_123');

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->stripe_price_id = 'price_abc123';

        $this->stripeProcessor->shouldReceive('updateSubscriptionPlan')
            ->once()
            ->andReturn(['success' => false]); // No error message

        $this->expectException(StripeUpdateFailedException::class);
        $this->expectExceptionMessage('Failed to update Stripe subscription: Unknown error');

        $this->service->updateSubscriptionPlan($subscription, $upgradePlan);
    }

    public function testUpdateSubscriptionPlanSkipsWhenNoStripeSubscriptionId(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('getStripeSubscriptionId')->andReturn(null);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->stripe_price_id = 'price_abc123';

        // Should not call stripe processor
        $this->stripeProcessor->shouldReceive('updateSubscriptionPlan')->never();

        $this->service->updateSubscriptionPlan($subscription, $upgradePlan);

        $this->assertTrue(true); // No exception, no call
    }

    public function testUpdateSubscriptionPlanSkipsInTestingEnvironment(): void
    {
        $_ENV['APP_ENV'] = 'testing';

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('getStripeSubscriptionId')->andReturn('sub_123');

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->stripe_price_id = 'price_abc123';

        // Should not call stripe processor in testing
        $this->stripeProcessor->shouldReceive('updateSubscriptionPlan')->never();

        $this->service->updateSubscriptionPlan($subscription, $upgradePlan);

        $this->assertTrue(true);
    }

    public function testUpdateSubscriptionPlanIncludesMetadata(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->plan_id = 1;
        $subscription->shouldReceive('getStripeSubscriptionId')->andReturn('sub_123');

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->stripe_price_id = 'price_abc123';

        $this->stripeProcessor->shouldReceive('updateSubscriptionPlan')
            ->with('sub_123', 'price_abc123', Mockery::on(function ($metadata) {
                return isset($metadata['upgraded_at'])
                    && isset($metadata['original_plan_id'])
                    && $metadata['original_plan_id'] === 1;
            }))
            ->once()
            ->andReturn(['success' => true]);

        $this->service->updateSubscriptionPlan($subscription, $upgradePlan);

        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->stripeProcessor = Mockery::mock(StripePaymentProcessor::class);
        $this->service = new StripeSubscriptionUpgradeService($this->stripeProcessor);

        $_ENV['APP_ENV'] = 'production';
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
        unset($_ENV['APP_ENV']);
    }
}