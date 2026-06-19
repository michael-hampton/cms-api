<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Models\Subscription;
use App\Services\Subscriptions\SubscriptionCancellationFlowProvider;
use DateTime;
use PHPUnit\Framework\TestCase;

final class SubscriptionCancellationFlowProviderTest extends TestCase
{
    private SubscriptionCancellationFlowProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new SubscriptionCancellationFlowProvider();
    }

    public function test_active_subscription_gets_backend_driven_cancellation_flow(): void
    {
        $subscription = $this->subscription();
        $subscription->delivery_type = 'digital';
        $subscription->premium_access = ['newsletter'];

        $flow = $this->provider->for($subscription);
        $reasons = $flow['reasons'];
        $other = end($reasons);

        $this->assertNotNull($flow);
        $this->assertSame('Cancel renewal', $flow['action_label']);
        $this->assertSame('No further renewal payment will be taken.', $flow['billing_message']);
        $this->assertContains('Digital archive access', $flow['lost_benefits']);
        $this->assertContains('Premium subscriber benefits', $flow['lost_benefits']);
        $this->assertNotEmpty($reasons);
        $this->assertSame('other', $other['value']);
        $this->assertTrue($other['requires_note']);
    }

    public function test_print_only_subscription_does_not_claim_digital_archive_loss(): void
    {
        $subscription = $this->subscription();
        $subscription->delivery_type = 'printed';
        $subscription->includes_digital_access = false;
        $subscription->premium_access = [];

        $flow = $this->provider->for($subscription);

        $this->assertNotContains('Digital archive access', $flow['lost_benefits']);
        $this->assertNotContains('Premium subscriber benefits', $flow['lost_benefits']);
    }

    public function test_scheduled_cancellation_is_not_cancellable_again(): void
    {
        $subscription = $this->subscription();
        $subscription->cancel_at_period_end = true;

        $this->assertFalse($this->provider->canCancel($subscription));
        $this->assertNull($this->provider->for($subscription));
    }

    public function test_cancelled_or_expired_subscription_has_no_flow(): void
    {
        $cancelled = $this->subscription();
        $cancelled->status = 'cancelled';

        $expired = $this->subscription();
        $expired->end_date = new DateTime('-1 day');

        $this->assertNull($this->provider->for($cancelled));
        $this->assertNull($this->provider->for($expired));
    }

    private function subscription(): Subscription
    {
        $subscription = new Subscription();
        $subscription->id = 1;
        $subscription->status = 'active';
        $subscription->start_date = new DateTime('-1 month');
        $subscription->end_date = new DateTime('+1 month');
        $subscription->auto_renew = true;
        $subscription->cancel_at_period_end = false;
        $subscription->includes_digital_access = false;
        $subscription->premium_access = [];

        return $subscription;
    }
}
