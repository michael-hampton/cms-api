<?php

namespace App\Tests\Unit\Models;

use App\Models\Member;
use App\Models\Payment;
use App\Models\Subscription;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class SubscriptionModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $member;

    public function test_can_create_subscription(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD',
            'auto_renew' => true
        ]);

        $this->assertNotNull($subscription);
        $this->assertEquals('Premium', $subscription->plan_name);
        $this->assertEquals('active', $subscription->status);
        $this->assertEquals(29.99, $subscription->price);
        $this->assertTrue($subscription->auto_renew);
    }

    public function test_is_active_returns_true_for_active_subscription(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 year')),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $this->assertTrue($subscription->isActive());
    }

    public function test_is_active_returns_false_for_cancelled_subscription(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'cancelled',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $this->assertFalse($subscription->isActive());
    }

    public function test_is_active_returns_false_for_expired_subscription(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 year')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $this->assertFalse($subscription->isActive());
    }

    public function test_is_cancelled_returns_true_for_cancelled_status(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'cancelled',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $this->assertTrue($subscription->isCancelled());
    }

    public function test_is_expired_returns_true_for_expired_status(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'expired',
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 year')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $this->assertTrue($subscription->isExpired());
    }

    public function test_is_expired_returns_true_when_end_date_passed(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 year')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $this->assertTrue($subscription->isExpired());
    }

    public function test_member_relationship(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $member = $subscription->member;

        $this->assertNotNull($member);
        $this->assertEquals($this->member->id, $member->id);
        $this->assertEquals($this->member->email, $member->email);
    }

    public function test_price_casts_to_float(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => '29.99',
            'currency' => 'USD'
        ]);

        $this->assertIsFloat($subscription->price);
        $this->assertEquals(29.99, $subscription->price);
    }

    public function test_auto_renew_casts_to_boolean(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD',
            'auto_renew' => 1
        ]);

        $this->assertIsBool($subscription->auto_renew);
        $this->assertTrue($subscription->auto_renew);
    }

    public function test_dates_cast_to_datetime(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => '2024-01-01 10:00:00',
            'end_date' => '2025-01-01 10:00:00',
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $this->assertInstanceOf(\DateTime::class, $subscription->start_date);
        $this->assertInstanceOf(\DateTime::class, $subscription->end_date);

    }

    public function test_payments_relationship(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        Payment::create([
            'subscription_id' => $subscription->id,
            'site_id' => $this->siteId,
            'payment_method' => 'stripe',
            'status' => 'completed',
            'amount' => 29.99,
            'currency' => 'USD'
        ]);

        Payment::create([
            'subscription_id' => $subscription->id,
            'site_id' => $this->siteId,
            'payment_method' => 'stripe',
            'status' => 'completed',
            'amount' => 29.99,
            'currency' => 'USD'
        ]);

        $payments = $subscription->payments;

        $this->assertCount(2, $payments);
    }

    public function test_last_payment_relationship(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $oldPayment = Payment::create([
            'subscription_id' => $subscription->id,
            'site_id' => $this->siteId,
            'payment_method' => 'stripe',
            'status' => 'completed',
            'amount' => 29.99,
            'currency' => 'USD',
            'paid_at' => date('Y-m-d H:i:s', strtotime('-1 month'))
        ]);

        $lastPayment = Payment::create([
            'subscription_id' => $subscription->id,
            'site_id' => $this->siteId,
            'payment_method' => 'stripe',
            'status' => 'completed',
            'amount' => 29.99,
            'currency' => 'USD',
            'paid_at' => date('Y-m-d H:i:s')
        ]);

        $result = $subscription->lastPayment;

        $this->assertNotNull($result);
        $this->assertEquals($lastPayment->id, $result->id);
    }

    public function test_is_due_for_renewal_returns_true_when_due(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'next_billing_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'auto_renew' => true,
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $this->assertTrue($subscription->isDueForRenewal());
    }

    public function test_is_due_for_renewal_returns_false_when_not_due(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'next_billing_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'auto_renew' => true,
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $this->assertFalse($subscription->isDueForRenewal());
    }

    public function test_is_due_for_renewal_returns_false_when_auto_renew_disabled(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'next_billing_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'auto_renew' => false,
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $this->assertFalse($subscription->isDueForRenewal());
    }

    public function test_get_days_until_renewal(): void
    {
        $nextBillingDate = new \DateTime('+30 days');

        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'next_billing_date' => $nextBillingDate->format('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $days = $subscription->getDaysUntilRenewal();

        $this->assertNotNull($days);
        $this->assertGreaterThanOrEqual(29, $days);
        $this->assertLessThanOrEqual(30, $days);
    }

    public function test_get_days_until_renewal_returns_zero_when_overdue(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'next_billing_date' => date('Y-m-d H:i:s', strtotime('-5 days')),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $days = $subscription->getDaysUntilRenewal();

        $this->assertEquals(0, $days);
    }

    public function test_next_billing_date_casts_to_datetime(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'next_billing_date' => '2025-01-01 10:00:00',
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $this->assertInstanceOf(\DateTime::class, $subscription->next_billing_date);
    }

    public function test_last_payment_date_casts_to_datetime(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'last_payment_date' => '2024-12-01 10:00:00',
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $this->assertInstanceOf(\DateTime::class, $subscription->last_payment_date);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->member = $this->createMember();
    }
}