<?php

namespace App\Tests\Unit\Services\Billing\Stripe;

use App\Services\Billing\Stripe\StripeSubscriptionPlanUpdater;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Stripe\Service\SubscriptionService;
use Stripe\StripeClient;

class StripeSubscriptionPlanUpdaterTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_update_replaces_first_subscription_item_price(): void
    {
        $subscriptions = Mockery::mock(SubscriptionService::class);
        $subscriptions->shouldReceive('retrieve')
            ->once()
            ->with('sub_123')
            ->andReturn((object) [
                'items' => (object) [
                    'data' => [
                        (object) ['id' => 'si_123'],
                    ],
                ],
            ]);

        $subscriptions->shouldReceive('update')
            ->once()
            ->with('sub_123', Mockery::on(fn ($payload) => $payload['items'][0]['id'] === 'si_123' && $payload['items'][0]['price'] === 'price_new'))
            ->andReturn((object) ['id' => 'sub_123']);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->subscriptions = $subscriptions;

        $service = new StripeSubscriptionPlanUpdater($stripe);

        $this->assertSame(['success' => true, 'stripe_subscription_id' => 'sub_123'], $service->update('sub_123', 'price_new'));
    }
}
