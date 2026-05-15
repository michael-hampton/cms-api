<?php

namespace App\Tests\Unit\Actions\Stripe;

use App\Actions\Stripe\HandleSubscriptionUpdated;
use App\Models\Subscription;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class HandleSubscriptionUpdatedTest extends FunctionalTestCase
{
    use CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();
    }

    private function makeSubscriptionEvent(string $stripeId, string $status, array $overrides = []): \Stripe\Event
    {
        $subData = array_merge([
            'id'                   => $stripeId,
            'object'               => 'subscription',
            'status'               => $status,
            'customer'             => 'cus_test',
            'current_period_start' => strtotime('-1 month'),
            'current_period_end'   => strtotime('+1 month'),
            'cancel_at_period_end' => false,
        ], $overrides);

        return \Stripe\Event::constructFrom([
            'id'          => 'evt_sub_upd',
            'type'        => 'customer.subscription.updated',
            'data'        => ['object' => $subData],
            'api_version' => '2023-10-16',
        ]);
    }

    public function test_it_updates_subscription_status(): void
    {
        $subscription = $this->createSubscription([
            'payment_subscription_id' => 'sub_upd123',
            'status'                 => 'active',
        ]);

        $event = $this->makeSubscriptionEvent('sub_upd123', 'past_due');

        (new HandleSubscriptionUpdated())->handle($event);

        $subscription->refresh();

        $this->assertSame('past_due', $subscription->status);
    }

    public function test_it_maps_trialing_to_active(): void
    {
        $subscription = $this->createSubscription([
            'payment_subscription_id' => 'sub_trial',
            'status'                 => 'active',
        ]);

        $event = $this->makeSubscriptionEvent('sub_trial', 'trialing');

        (new HandleSubscriptionUpdated())->handle($event);

        $subscription->refresh();

        $this->assertSame('active', $subscription->status);
    }

    public function test_it_sets_cancel_at_period_end_flag(): void
    {
        $subscription = $this->createSubscription([
            'payment_subscription_id' => 'sub_cancel',
            'cancel_at_period_end'   => false,
        ]);

        $event = $this->makeSubscriptionEvent('sub_cancel', 'active', [
            'cancel_at_period_end' => true,
        ]);

        (new HandleSubscriptionUpdated())->handle($event);

        $subscription->refresh();

        $this->assertTrue((bool)$subscription->cancel_at_period_end);
        $this->assertNotNull($subscription->cancelled_at);
    }

    public function test_it_does_not_overwrite_cancelled_at_when_already_set(): void
    {
        $originalTime = '2024-01-01 12:00:00';

        $subscription = $this->createSubscription([
            'payment_subscription_id' => 'sub_keepcancel',
            'cancel_at_period_end'   => true,
            'cancelled_at'           => $originalTime,
        ]);

        $event = $this->makeSubscriptionEvent('sub_keepcancel', 'active', [
            'cancel_at_period_end' => true,
        ]);

        (new HandleSubscriptionUpdated())->handle($event);

        $subscription->refresh();

        $this->assertSame($originalTime, $subscription->cancelled_at->format('Y-m-d H:i:s'));
    }

    public function test_it_silently_skips_unknown_subscription(): void
    {
        $event = $this->makeSubscriptionEvent('sub_unknown_xyz', 'active');

        // Must not throw
        (new HandleSubscriptionUpdated())->handle($event);

        $this->assertTrue(true);
    }
}