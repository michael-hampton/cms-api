<?php

namespace App\Tests\Unit\Actions\Stripe;

use App\Actions\Stripe\HandleSubscriptionDeleted;
use App\Models\Subscription;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class HandleSubscriptionDeletedTest extends FunctionalTestCase
{
    use CreatesTestData;
    
    protected function setUp(): void
    {
        parent::setUp();
    }
    private function makeEvent(string $stripeId, array $overrides = []): \Stripe\Event
    {
        $subData = array_merge([
            'id'                   => $stripeId,
            'object'               => 'subscription',
            'status'               => 'canceled',
            'customer'             => 'cus_test',
            'current_period_end'   => strtotime('+1 day'),
            'ended_at'             => null,
            'cancel_at_period_end' => false,
        ], $overrides);

        return \Stripe\Event::constructFrom([
            'id'          => 'evt_deleted_' . uniqid(),
            'type'        => 'customer.subscription.deleted',
            'data'        => ['object' => $subData],
            'api_version' => '2023-10-16',
        ]);
    }

    public function test_it_marks_subscription_as_canceled(): void
    {
        $subscription = $this->createSubscription([
            'payment_subscription_id' => 'sub_del1',
            'status'                 => 'active',
            'auto_renew'             => true,
        ]);

        (new HandleSubscriptionDeleted())->handle($this->makeEvent('sub_del1'));

        $subscription->refresh();

        $this->assertSame('cancelled', $subscription->status);
        $this->assertFalse((bool)$subscription->auto_renew);
    }

    public function test_it_sets_cancelled_at_when_not_already_present(): void
    {
        $subscription = $this->createSubscription([
            'payment_subscription_id' => 'sub_del2',
            'cancelled_at'           => null,
        ]);

        (new HandleSubscriptionDeleted())->handle($this->makeEvent('sub_del2'));

        $subscription->refresh();

        $this->assertNotNull($subscription->cancelled_at);
    }

    public function test_it_does_not_overwrite_existing_cancelled_at(): void
    {
        $original = '2024-01-15 10:00:00';

        $subscription = $this->createSubscription([
            'payment_subscription_id' => 'sub_del3',
            'cancelled_at'           => $original,
        ]);

        (new HandleSubscriptionDeleted())->handle($this->makeEvent('sub_del3'));

        $subscription->refresh();

        $this->assertSame($original, $subscription->cancelled_at->format('Y-m-d H:i:s'));
    }

    public function test_it_sets_end_date_from_ended_at_when_present(): void
    {
        $endedAt = strtotime('2024-02-01 00:00:00');

        $subscription = $this->createSubscription([
            'payment_subscription_id' => 'sub_del4',
        ]);

        $event = $this->makeEvent('sub_del4', ['ended_at' => $endedAt]);

        (new HandleSubscriptionDeleted())->handle($event);

        $subscription->refresh();

        $this->assertSame('2024-02-01 00:00:00', $subscription->end_date->format('Y-m-d H:i:s'));
    }

    public function test_it_falls_back_to_current_period_end_when_ended_at_is_null(): void
    {
        $periodEnd = strtotime('2024-03-01 00:00:00');

        $subscription = $this->createSubscription([
            'payment_subscription_id' => 'sub_del5',
        ]);

        $event = $this->makeEvent('sub_del5', [
            'ended_at'           => null,
            'current_period_end' => $periodEnd,
        ]);

        (new HandleSubscriptionDeleted())->handle($event);

        $subscription->refresh();

        $this->assertSame('2024-03-01 00:00:00', $subscription->end_date->format('Y-m-d H:i:s'));
    }

    public function test_it_silently_skips_unknown_subscription(): void
    {
        $event = $this->makeEvent('sub_nonexistent_xyz');

        // Must not throw
        (new HandleSubscriptionDeleted())->handle($event);

        $this->assertTrue(true);
    }
}