<?php

namespace App\Tests\Unit\Models;

use App\Models\Member;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionWindow;
use App\Models\Voucher;
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

    public function test_subscription_has_voucher_relationship()
    {
        $member = $this->createMember();
        $plan = $this->createSubscriptionPlan();

        $voucher = Voucher::create([
            'code' => 'SUB10',
            'name' => 'Subscription Discount',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active',
            'site_id' => $this->siteId,
            'applies_to_subscriptions' => true
        ]);

        $subscription = Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD',
            'voucher_id' => $voucher->id,
            'discount_amount' => 2.99
        ]);

        $loadedVoucher = $subscription->voucher;

        $this->assertNotNull($loadedVoucher);
        $this->assertEquals($voucher->id, $loadedVoucher->id);
        $this->assertEquals('SUB10', $loadedVoucher->code);
    }

    public function test_get_discounted_price()
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD',
            'discount_amount' => 5.00
        ]);

        $this->assertEquals(24.99, $subscription->getDiscountedPrice());
    }

    public function test_get_discounted_price_with_no_discount()
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD',
            'discount_amount' => 0
        ]);

        $this->assertEquals(29.99, $subscription->getDiscountedPrice());
    }

    public function test_has_voucher_returns_true()
    {
        $voucher = $this->createVoucher();

        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD',
            'voucher_id' => $voucher->id,
            'discount_amount' => 5.00
        ]);

        $this->assertTrue($subscription->hasVoucher());
    }

    public function test_has_voucher_returns_false()
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD',
            'voucher_id' => null,
            'discount_amount' => 0
        ]);

        $this->assertFalse($subscription->hasVoucher());
    }

    public function test_original_price_is_stored()
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 24.99,
            'currency' => 'USD',
            'original_price' => 29.99,
            'discount_amount' => 5.00
        ]);

        $this->assertEquals(29.99, $subscription->original_price);
        $this->assertEquals(24.99, $subscription->price);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->member = $this->createMember();
    }

    public function test_is_one_time_returns_true_for_onetime_plan(): void
    {
        $plan = $this->createSubscriptionPlan(['plan_type' => 'onetime']);

        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'plan_name' => 'One Time Plan',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 50.00,
            'currency' => 'USD'
        ]);

        $this->assertTrue($subscription->isOneTime());
    }

    public function test_is_one_time_returns_false_for_recurring_plan(): void
    {
        $plan = $this->createSubscriptionPlan(['plan_type' => 'recurring']);

        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'plan_name' => 'Monthly Plan',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 10.00,
            'currency' => 'USD'
        ]);

        $this->assertFalse($subscription->isOneTime());
    }

    public function test_is_digital_returns_true(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Digital Plan',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 10.00,
            'currency' => 'USD',
            'delivery_type' => 'digital'
        ]);

        $this->assertTrue($subscription->isDigital());
    }

    public function test_is_digital_returns_false(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Print Plan',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 15.00,
            'currency' => 'USD',
            'delivery_type' => 'print'
        ]);

        $this->assertFalse($subscription->isDigital());
    }

    public function test_is_print_returns_true(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Print Plan',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 15.00,
            'currency' => 'USD',
            'delivery_type' => 'print'
        ]);

        $this->assertTrue($subscription->isPrint());
    }

    public function test_is_print_returns_false(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Digital Plan',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 10.00,
            'currency' => 'USD',
            'delivery_type' => 'digital'
        ]);

        $this->assertFalse($subscription->isPrint());
    }

    public function test_has_valid_download_returns_true(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Digital Plan',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 10.00,
            'currency' => 'USD',
            'delivery_type' => 'digital',
            'download_url' => 'https://example.com/download/file.pdf',
            'download_expires_at' => date('Y-m-d H:i:s', strtotime('+7 days'))
        ]);

        $this->assertTrue($subscription->hasValidDownload());
    }

    public function test_has_valid_download_returns_false_when_expired(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Digital Plan',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 10.00,
            'currency' => 'USD',
            'delivery_type' => 'digital',
            'download_url' => 'https://example.com/download/file.pdf',
            'download_expires_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ]);

        $this->assertFalse($subscription->hasValidDownload());
    }

    public function test_has_valid_download_returns_false_when_no_url(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Digital Plan',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 10.00,
            'currency' => 'USD',
            'delivery_type' => 'digital',
            'download_url' => null,
            'download_expires_at' => date('Y-m-d H:i:s', strtotime('+7 days'))
        ]);

        $this->assertFalse($subscription->hasValidDownload());
    }

    public function test_generate_download_url_sets_url_and_expiration(): void
    {
        $plan = $this->createSubscriptionPlan([
            'plan_type' => 'onetime',
            'digital_download_url' => 'https://example.com/magazine.pdf'
        ]);

        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'plan_name' => 'Digital Plan',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 10.00,
            'currency' => 'USD',
            'delivery_type' => 'digital'
        ]);

        $subscription->generateDownloadUrl('https://example.com');

        $this->assertNotNull($subscription->download_url);
        $this->assertEquals('https://example.com/magazine.pdf', $subscription->download_url);
        $this->assertNotNull($subscription->download_expires_at);

        $expiresAt = $subscription->download_expires_at;
        $expectedExpiry = new \DateTime('+30 days');
        $diff = $expiresAt->diff($expectedExpiry);
        $this->assertLessThanOrEqual(1, $diff->days);
    }

    public function test_generate_download_url_does_nothing_for_print(): void
    {
        $plan = $this->createSubscriptionPlan([
            'plan_type' => 'onetime',
            'digital_download_url' => 'https://example.com/magazine.pdf'
        ]);

        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'plan_name' => 'Print Plan',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 15.00,
            'currency' => 'USD',
            'delivery_type' => 'print'
        ]);

        $subscription->generateDownloadUrl('https://example.com');

        $this->assertNull($subscription->download_url);
        $this->assertNull($subscription->download_expires_at);
    }

    public function testPaidSubscriptionCreatesWindowOnCreation()
    {
        $member = $this->createMember();

        $subscription = Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'type' => 'paid',
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        // Window should be auto-created
        $window = SubscriptionWindow::where('subscription_id', $subscription->id)->first();

        $this->assertNotNull($window);
        $this->assertEquals($member->id, $window->member_id);
        $this->assertEquals('paid', $window->type);
    }

    public function testTrialSubscriptionDoesNotCreateWindow()
    {
        $member = $this->createMember();

        $subscription = Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Trial',
            'status' => 'active',
            'type' => 'trial',
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'price' => 0,
            'currency' => 'USD'
        ]);

        // No window should be created for trials
        $window = SubscriptionWindow::where('subscription_id', $subscription->id)->first();

        $this->assertNull($window);
    }

    public function testCloseWindowSetsEndDateToNow()
    {
        $member = $this->createMember();

        $subscription = Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'type' => 'paid',
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $originalWindow = SubscriptionWindow::where('subscription_id', $subscription->id)->first();
        $originalEndDate = $originalWindow->window_end;

        // Close the window
        $subscription->closeWindow();

        $updatedWindow = SubscriptionWindow::where('subscription_id', $subscription->id)->first();

        $this->assertNotEquals($originalEndDate, $updatedWindow->window_end);
        $this->assertEqualsWithDelta(
            time(),
            $updatedWindow->window_end->getTimestamp(),
            5 // Within 5 seconds
        );
    }

    public function testUpdateWindowChangesEndDate()
    {
        $member = $this->createMember();

        $subscription = Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'type' => 'paid',
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $window = SubscriptionWindow::where('subscription_id', $subscription->id)->first();
        $originalEnd = $window->window_end;

        // Update subscription end date
        $newEndDate = date('Y-m-d H:i:s', strtotime('+2 months'));
        $subscription->end_date = $newEndDate;
        $subscription->save();

        $window = $window->fresh();

        $this->assertNotEquals($originalEnd, $window->window_end);

        $this->assertEquals($newEndDate, $window->window_end->format('Y-m-d H:i:s'));
    }
}