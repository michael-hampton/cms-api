<?php

namespace App\Tests\Unit\Repositories;

use App\Models\Payment;
use App\Models\Subscription;
use App\Repositories\PaymentRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PaymentRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private PaymentRepository $repository;

    public function test_find_by_order_id_returns_payments(): void
    {
        $order = $this->createOrder();
        $payment1 = $this->createPayment(['order_id' => $order->id, 'status' => 'completed']);
        $payment2 = $this->createPayment(['order_id' => $order->id, 'status' => 'failed']);

        $payments = $this->repository->findByOrderId($order->id);

        $this->assertCount(2, $payments);
        $this->assertTrue($payments->contains('id', $payment1->id));
        $this->assertTrue($payments->contains('id', $payment2->id));
    }

    protected function createPayment(array $overrides = []): Payment
    {
        $order = $this->createOrder();

        return Payment::create(array_merge([
            'order_id' => $order->id,
            'site_id' => $this->siteId,
            'payment_method' => 'stripe',
            'payment_provider' => 'stripe',
            'status' => 'pending',
            'amount' => 100.00,
            'currency' => 'GBP',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    public function test_find_by_transaction_id_returns_payment(): void
    {
        $payment = $this->createPayment(['transaction_id' => 'txn_12345']);

        $found = $this->repository->findByTransactionId('txn_12345');

        $this->assertNotNull($found);
        $this->assertEquals($payment->id, $found->id);
    }

    public function test_find_by_transaction_id_returns_null_when_not_found(): void
    {
        $found = $this->repository->findByTransactionId('non_existent');

        $this->assertNull($found);
    }

    public function test_find_by_payment_intent_id_returns_payment(): void
    {
        $payment = $this->createPayment(['payment_intent_id' => 'pi_12345']);

        $found = $this->repository->findByPaymentIntentId('pi_12345');

        $this->assertNotNull($found);
        $this->assertEquals($payment->id, $found->id);
    }

    public function test_get_by_status_returns_filtered_payments(): void
    {
        $this->createPayment(['status' => 'pending']);
        $this->createPayment(['status' => 'pending']);
        $this->createPayment(['status' => 'completed']);

        $payments = $this->repository->getByStatus('pending');

        $this->assertGreaterThanOrEqual(2, $payments->count());
        foreach ($payments as $payment) {
            $this->assertEquals('pending', $payment->status);
        }
    }

    public function test_get_pending_payments_returns_only_pending(): void
    {
        $this->createPayment(['status' => 'pending']);
        $this->createPayment(['status' => 'completed']);

        $payments = $this->repository->getPendingPayments();

        $this->assertGreaterThanOrEqual(1, $payments->count());
        foreach ($payments as $payment) {
            $this->assertEquals('pending', $payment->status);
        }
    }

    public function test_get_failed_payments_returns_only_failed(): void
    {
        $this->createPayment(['status' => 'failed']);
        $this->createPayment(['status' => 'completed']);

        $payments = $this->repository->getFailedPayments();

        $this->assertGreaterThanOrEqual(1, $payments->count());
        foreach ($payments as $payment) {
            $this->assertEquals('failed', $payment->status);
        }
    }

    public function test_get_total_collected_calculates_completed_payments(): void
    {
        $this->createPayment([
            'status' => 'completed',
            'amount' => 100.00,
            'paid_at' => '2024-06-01 12:00:00'
        ]);

        $this->createPayment([
            'status' => 'completed',
            'amount' => 200.00,
            'paid_at' => '2024-06-15 12:00:00'
        ]);

        // Should not be counted
        $this->createPayment([
            'status' => 'pending',
            'amount' => 150.00
        ]);

        $total = $this->repository->getTotalCollected();

        $this->assertEquals(300.00, $total);
    }

    public function test_get_total_collected_filters_by_start_date(): void
    {
        $this->createPayment([
            'status' => 'completed',
            'amount' => 100.00,
            'paid_at' => '2024-05-01 12:00:00'
        ]);

        $this->createPayment([
            'status' => 'completed',
            'amount' => 200.00,
            'paid_at' => '2024-07-01 12:00:00'
        ]);

        $total = $this->repository->getTotalCollected('2024-06-01 00:00:00');

        $this->assertEquals(200.00, $total);
    }

    public function test_get_total_collected_filters_by_end_date(): void
    {
        $this->createPayment([
            'status' => 'completed',
            'amount' => 100.00,
            'paid_at' => '2024-05-01 12:00:00'
        ]);

        $this->createPayment([
            'status' => 'completed',
            'amount' => 200.00,
            'paid_at' => '2024-07-01 12:00:00'
        ]);

        $total = $this->repository->getTotalCollected(null, '2024-06-01 00:00:00');

        $this->assertEquals(100.00, $total);
    }

    public function test_get_total_collected_filters_by_date_range(): void
    {
        $this->createPayment([
            'status' => 'completed',
            'amount' => 100.00,
            'paid_at' => '2024-05-01 12:00:00'
        ]);

        $this->createPayment([
            'status' => 'completed',
            'amount' => 200.00,
            'paid_at' => '2024-06-15 12:00:00'
        ]);

        $this->createPayment([
            'status' => 'completed',
            'amount' => 150.00,
            'paid_at' => '2024-08-01 12:00:00'
        ]);

        $total = $this->repository->getTotalCollected('2024-06-01 00:00:00', '2024-07-01 00:00:00');

        $this->assertEquals(200.00, $total);
    }

    public function test_get_by_payment_method_returns_filtered_payments(): void
    {
        $this->createPayment(['payment_method' => 'stripe']);
        $this->createPayment(['payment_method' => 'stripe']);
        $this->createPayment(['payment_method' => 'paypal']);

        $payments = $this->repository->getByPaymentMethod('stripe');

        $this->assertGreaterThanOrEqual(2, $payments->count());
        foreach ($payments as $payment) {
            $this->assertEquals('stripe', $payment->payment_method);
        }
    }

    public function test_find_by_subscription_id_returns_payments(): void
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

        $payment1 = $this->createPayment([
            'subscription_id' => $subscription->id,
            'status' => 'completed'
        ]);

        $payment2 = $this->createPayment([
            'subscription_id' => $subscription->id,
            'status' => 'completed'
        ]);

        $payments = $this->repository->findBySubscriptionId($subscription->id);

        $this->assertCount(2, $payments);
        $this->assertTrue($payments->contains('id', $payment1->id));
        $this->assertTrue($payments->contains('id', $payment2->id));
    }

    public function test_get_last_subscription_payment_returns_most_recent(): void
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

        $oldPayment = $this->createPayment([
            'subscription_id' => $subscription->id,
            'status' => 'completed',
            'paid_at' => '2024-01-01 12:00:00'
        ]);

        $recentPayment = $this->createPayment([
            'subscription_id' => $subscription->id,
            'status' => 'completed',
            'paid_at' => '2024-06-01 12:00:00'
        ]);

        $result = $this->repository->getLastSubscriptionPayment($subscription->id);

        $this->assertNotNull($result);
        $this->assertEquals($recentPayment->id, $result->id);
    }

    public function test_get_failed_subscription_payments(): void
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

        $this->createPayment([
            'subscription_id' => $subscription->id,
            'status' => 'failed'
        ]);

        $this->createPayment([
            'subscription_id' => $subscription->id,
            'status' => 'completed'
        ]);

        // Regular payment without subscription
        $this->createPayment(['status' => 'failed']);

        $payments = $this->repository->getFailedSubscriptionPayments();

        $this->assertGreaterThanOrEqual(1, $payments->count());

        foreach ($payments as $payment) {
            $this->assertEquals('failed', $payment->status);
            $this->assertNotNull($payment->subscription_id);
        }
    }

    public function test_count_subscription_payments(): void
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

        $this->createPayment([
            'subscription_id' => $subscription->id,
            'status' => 'completed'
        ]);

        $this->createPayment([
            'subscription_id' => $subscription->id,
            'status' => 'failed'
        ]);

        $this->createPayment([
            'subscription_id' => $subscription->id,
            'status' => 'completed'
        ]);

        $totalCount = $this->repository->countSubscriptionPayments($subscription->id);
        $failedCount = $this->repository->countSubscriptionPayments($subscription->id, 'failed');

        $this->assertEquals(3, $totalCount);
        $this->assertEquals(1, $failedCount);
    }


    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PaymentRepository();
    }
}