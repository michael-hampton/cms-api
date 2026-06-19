<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Models\Subscription;
use App\Services\Subscriptions\SubscriptionCancellationFlowProvider;
use App\Tests\Support\MocksSubscriptionModels;
use DateTime;
use PHPUnit\Framework\TestCase;

final class SubscriptionCancellationFlowProviderTest extends TestCase
{
    use MocksSubscriptionModels;

    private SubscriptionCancellationFlowProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new SubscriptionCancellationFlowProvider();
    }

    public function test_active_subscription_gets_backend_driven_cancellation_flow(): void
    {
        $subscription = $this->subscription([
            'delivery_type' => 'digital',
            'premium_access' => ['newsletter'],
        ], ['isDigital' => true]);

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
        $subscription = $this->subscription([
            'delivery_type' => 'printed',
            'includes_digital_access' => false,
            'premium_access' => [],
        ], ['isDigital' => false]);

        $flow = $this->provider->for($subscription);

        $this->assertNotContains('Digital archive access', $flow['lost_benefits']);
        $this->assertNotContains('Premium subscriber benefits', $flow['lost_benefits']);
    }

    public function test_scheduled_cancellation_is_not_cancellable_again(): void
    {
        $subscription = $this->subscription([], ['isCancellationScheduled' => true]);

        $this->assertFalse($this->provider->canCancel($subscription));
        $this->assertNull($this->provider->for($subscription));
    }

    public function test_cancelled_or_expired_subscription_has_no_flow(): void
    {
        $cancelled = $this->subscription(
            ['status' => 'cancelled'],
            ['isActive' => false, 'isCancelled' => true]
        );
        $expired = $this->subscription(
            ['end_date' => new DateTime('-1 day')],
            ['isActive' => false, 'isExpired' => true]
        );

        $this->assertNull($this->provider->for($cancelled));
        $this->assertNull($this->provider->for($expired));
    }

    public function test_paused_subscription_can_cancel(): void
    {
        $subscription = $this->subscription(
            ['status' => 'paused'],
            ['isActive' => false]
        );

        $this->assertTrue($this->provider->canCancel($subscription));
    }

    private function subscription(array $attributes = [], array $methods = []): Subscription
    {
        return $this->mockSubscription(array_merge([
            'id' => 1,
            'status' => 'active',
            'start_date' => new DateTime('-1 month'),
            'end_date' => new DateTime('+1 month'),
            'auto_renew' => true,
            'includes_digital_access' => false,
            'premium_access' => [],
        ], $attributes), array_merge([
            'isActive' => true,
            'isCancellationScheduled' => false,
            'isCancelled' => false,
            'isExpired' => false,
            'isDigital' => false,
        ], $methods));
    }
}
