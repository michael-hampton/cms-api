<?php

namespace App\Tests\Unit\Services\Billing\Stripe;

use App\Services\Billing\Stripe\StripeSubscriptionBillingCycleService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Stripe\Service\SubscriptionService;
use Stripe\StripeClient;

class StripeSubscriptionBillingCycleServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_calculate_billing_date_proration_returns_preview_shape(): void
    {
        $subscription = (object) [
            'items' => (object) [
                'data' => [
                    (object) [
                        'current_period_end' => strtotime('+10 days'),
                        'price' => (object) ['unit_amount' => 1200],
                    ],
                ],
            ],
        ];

        $subscriptions = Mockery::mock(SubscriptionService::class);
        $subscriptions->shouldReceive('retrieve')
            ->once()
            ->with('sub_123')
            ->andReturn($subscription);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->subscriptions = $subscriptions;

        $service = new StripeSubscriptionBillingCycleService($stripe);
        $result = $service->calculateBillingDateProration('sub_123', 20);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('proration_amount', $result);
        $this->assertArrayHasKey('new_billing_date', $result);
    }
}
