<?php

namespace App\Tests\Unit\Models;

use App\Enums\Subscriptions\SubscriptionType;
use App\Models\Subscription;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class SubscriptionDeliveryPauseTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_is_delivery_paused_returns_true_when_currently_paused(): void
    {
        $subscription = $this->createPrintSubscription();
        $subscription->delivery_paused = true;
        $subscription->delivery_pause_start = new \DateTime('-1 day');
        $subscription->delivery_pause_end = new \DateTime('+5 days');
        $subscription->save();

        $this->assertTrue($subscription->isDeliveryPaused());
    }

    private function createPrintSubscription(): Subscription
    {
        $member = $this->createMember();
        $plan = $this->createSubscriptionPlan();

        return Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD',
            'delivery_type' => SubscriptionType::PRINTED->value
        ]);
    }

    public function test_is_delivery_paused_returns_false_when_pause_not_started(): void
    {
        $subscription = $this->createPrintSubscription();
        $subscription->delivery_paused = true;
        $subscription->delivery_pause_start = new \DateTime('+5 days');
        $subscription->delivery_pause_end = new \DateTime('+10 days');
        $subscription->save();

        $this->assertFalse($subscription->isDeliveryPaused());
    }

    public function test_is_delivery_paused_returns_false_when_pause_ended(): void
    {
        $subscription = $this->createPrintSubscription();
        $subscription->delivery_paused = true;
        $subscription->delivery_pause_start = new \DateTime('-10 days');
        $subscription->delivery_pause_end = new \DateTime('-1 day');
        $subscription->save();

        $this->assertFalse($subscription->isDeliveryPaused());
    }

    public function test_can_pause_delivery_returns_true_for_active_print_subscription(): void
    {
        $subscription = $this->createPrintSubscription();

        $this->assertTrue($subscription->canPauseDelivery());
    }

    public function test_can_pause_delivery_returns_false_for_digital_subscription(): void
    {
        $subscription = $this->createDigitalSubscription();

        $this->assertFalse($subscription->canPauseDelivery());
    }

    private function createDigitalSubscription(): Subscription
    {
        $member = $this->createMember();
        $plan = $this->createSubscriptionPlan();

        return Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 19.99,
            'currency' => 'USD',
            'delivery_type' => SubscriptionType::DIGITAL->value
        ]);
    }

    public function test_can_pause_delivery_returns_false_when_already_paused(): void
    {
        $subscription = $this->createPrintSubscription();
        $subscription->delivery_paused = true;
        $subscription->delivery_pause_start = new \DateTime('-1 day');
        $subscription->delivery_pause_end = new \DateTime('+5 days');
        $subscription->save();

        $this->assertFalse($subscription->canPauseDelivery());
    }

    public function test_can_resume_delivery_returns_true_when_paused(): void
    {
        $subscription = $this->createPrintSubscription();
        $subscription->delivery_paused = true;
        $subscription->delivery_pause_start = new \DateTime('-1 day');
        $subscription->delivery_pause_end = new \DateTime('+5 days');
        $subscription->save();

        $this->assertTrue($subscription->canResumeDelivery());
    }

    public function test_can_resume_delivery_returns_false_when_not_paused(): void
    {
        $subscription = $this->createPrintSubscription();

        $this->assertFalse($subscription->canResumeDelivery());
    }

    public function test_get_days_until_pause_ends(): void
    {
        $subscription = $this->createPrintSubscription();
        $subscription->delivery_paused = true;
        $subscription->delivery_pause_start = new \DateTime('-1 day');
        $subscription->delivery_pause_end = new \DateTime('+5 days');
        $subscription->save();

        $days = $subscription->getDaysUntilPauseEnds();

        $this->assertGreaterThanOrEqual(4, $days);
        $this->assertLessThanOrEqual(5, $days);
    }
}