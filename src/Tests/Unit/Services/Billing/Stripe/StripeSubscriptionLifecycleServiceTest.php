<?php

namespace App\Tests\Unit\Services\Billing\Stripe;

use App\Services\Billing\Stripe\StripeSubscriptionLifecycleService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Stripe\Service\SubscriptionService;
use Stripe\StripeClient;

class StripeSubscriptionLifecycleServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_cancel_updates_subscription_at_period_end(): void
    {
        $subscriptions = Mockery::mock(SubscriptionService::class);
        $subscriptions->shouldReceive('update')
            ->once()
            ->with('sub_123', ['cancel_at_period_end' => true])
            ->andReturn((object) [
                'status' => 'active',
                'cancel_at_period_end' => true,
                'canceled_at' => null,
                'current_period_end' => 1234567890,
            ]);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->subscriptions = $subscriptions;

        $service = new StripeSubscriptionLifecycleService($stripe);

        $this->assertSame(true, $service->cancel('sub_123')['success']);
    }

    public function test_reactivate_rejects_already_canceled_subscription(): void
    {
        $subscriptions = Mockery::mock(SubscriptionService::class);
        $subscriptions->shouldReceive('retrieve')
            ->once()
            ->with('sub_123')
            ->andReturn((object) [
                'status' => 'canceled',
                'cancel_at_period_end' => true,
            ]);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->subscriptions = $subscriptions;

        $service = new StripeSubscriptionLifecycleService($stripe);

        $this->assertSame('subscription_already_canceled', $service->reactivate('sub_123')['error_code']);
    }
}
