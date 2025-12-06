<?php

namespace App\Tests\Unit\Models;

use App\Models\Payment;
use App\Models\Subscription;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PaymentModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testPaymentHasCorrectTable()
    {
        $payment = new Payment();
        $this->assertEquals('payments', $payment->getTable());
    }

    public function testIsPendingReturnsTrueWhenStatusIsPending()
    {
        $payment = new Payment(['status' => 'pending']);
        $this->assertTrue($payment->isPending());
    }

    public function testIsProcessingReturnsTrueWhenStatusIsProcessing()
    {
        $payment = new Payment(['status' => 'processing']);
        $this->assertTrue($payment->isProcessing());
    }

    public function testIsCompletedReturnsTrueWhenStatusIsCompleted()
    {
        $payment = new Payment(['status' => 'completed']);
        $this->assertTrue($payment->isCompleted());
    }

    public function testIsFailedReturnsTrueWhenStatusIsFailed()
    {
        $payment = new Payment(['status' => 'failed']);
        $this->assertTrue($payment->isFailed());
    }

    public function testIsCancelledReturnsTrueWhenStatusIsCancelled()
    {
        $payment = new Payment(['status' => 'cancelled']);
        $this->assertTrue($payment->isCancelled());
    }

    public function testIsRefundedReturnsTrueWhenStatusIsRefunded()
    {
        $payment = new Payment(['status' => 'refunded']);
        $this->assertTrue($payment->isRefunded());
    }

    public function testCanBeRetriedReturnsTrueForFailedPayments()
    {
        $payment = new Payment(['status' => 'failed']);
        $this->assertTrue($payment->canBeRetried());
    }

    public function testCanBeRetriedReturnsTrueForCancelledPayments()
    {
        $payment = new Payment(['status' => 'cancelled']);
        $this->assertTrue($payment->canBeRetried());
    }

    public function testCanBeRetriedReturnsFalseForCompletedPayments()
    {
        $payment = new Payment(['status' => 'completed']);
        $this->assertFalse($payment->canBeRetried());
    }

    public function testCanBeRefundedReturnsTrueForCompletedPayments()
    {
        $payment = new Payment(['status' => 'completed']);
        $this->assertTrue($payment->canBeRefunded());
    }

    public function testCanBeRefundedReturnsFalseForPendingPayments()
    {
        $payment = new Payment(['status' => 'pending']);
        $this->assertFalse($payment->canBeRefunded());
    }

    public function testMarkAsPaidUpdatesStatusAndTimestamp()
    {
        $order = $this->createOrder();
        $payment = Payment::create([
            'order_id' => $order->id,
            'site_id' => $this->siteId,
            'payment_method' => 'stripe',
            'status' => 'pending',
            'amount' => 100.00,
            'currency' => 'GBP'
        ]);

        $result = $payment->markAsPaid();

        $this->assertTrue($result);
        $this->assertEquals('completed', $payment->status);
        $this->assertNotNull($payment->paid_at);
    }

    public function testMarkAsFailedUpdatesStatusAndError()
    {
        $order = $this->createOrder();
        $payment = Payment::create([
            'order_id' => $order->id,
            'site_id' => $this->siteId,
            'payment_method' => 'stripe',
            'status' => 'pending',
            'amount' => 100.00,
            'currency' => 'GBP'
        ]);

        $result = $payment->markAsFailed('Card declined');

        $this->assertTrue($result);
        $this->assertEquals('failed', $payment->status);
        $this->assertEquals('Card declined', $payment->error_message);
        $this->assertNotNull($payment->failed_at);
    }

    public function testToArrayIncludesStatusFlags()
    {
        $payment = new Payment([
            'status' => 'completed',
            'amount' => 100.00,
            'currency' => 'GBP'
        ]);

        $array = $payment->toArray();

        $this->assertArrayHasKey('is_pending', $array);
        $this->assertArrayHasKey('is_processing', $array);
        $this->assertArrayHasKey('is_completed', $array);
        $this->assertArrayHasKey('is_failed', $array);
        $this->assertArrayHasKey('can_be_retried', $array);
        $this->assertArrayHasKey('can_be_refunded', $array);
    }

    public function testOrderRelationshipLoads()
    {
        $order = $this->createOrder();
        $payment = Payment::create([
            'order_id' => $order->id,
            'site_id' => $this->siteId,
            'payment_method' => 'stripe',
            'status' => 'pending',
            'amount' => 100.00,
            'currency' => 'GBP'
        ]);

        $payment->load(['order']);

        $this->assertTrue($payment->relationLoaded('order'));
        $this->assertEquals($order->id, $payment->order->id);
    }

    public function testPaymentCanHaveSubscription()
    {
        $member = $this->createMember();
        $subscription = Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $payment = Payment::create([
            'subscription_id' => $subscription->id,
            'site_id' => $this->siteId,
            'payment_method' => 'stripe',
            'status' => 'completed',
            'amount' => 29.99,
            'currency' => 'USD'
        ]);

        $this->assertNotNull($payment->subscription_id);
        $this->assertEquals($subscription->id, $payment->subscription_id);
    }

    public function testIsSubscriptionPaymentReturnsTrueWhenSubscriptionIdPresent()
    {
        $payment = new Payment([
            'subscription_id' => 1,
            'status' => 'completed'
        ]);

        $this->assertTrue($payment->isSubscriptionPayment());
    }

    public function testIsSubscriptionPaymentReturnsFalseWhenSubscriptionIdNull()
    {
        $payment = new Payment([
            'subscription_id' => null,
            'status' => 'completed'
        ]);

        $this->assertFalse($payment->isSubscriptionPayment());
    }

    public function testSubscriptionRelationshipLoads()
    {
        $member = $this->createMember();
        $subscription = Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $payment = Payment::create([
            'subscription_id' => $subscription->id,
            'site_id' => $this->siteId,
            'payment_method' => 'stripe',
            'status' => 'completed',
            'amount' => 29.99,
            'currency' => 'USD'
        ]);

        $payment->load(['subscription']);

        $this->assertTrue($payment->relationLoaded('subscription'));
        $this->assertEquals($subscription->id, $payment->subscription->id);
    }

    protected function setUp(): void
    {
        parent::setUp();
    }
}