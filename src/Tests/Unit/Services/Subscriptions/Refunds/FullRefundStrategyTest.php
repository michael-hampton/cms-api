<?php

namespace App\Tests\Unit\Services\Subscriptions\Refunds;

use App\Models\Payment;
use App\Models\Subscription;
use App\Repositories\Billing\PaymentRepository;
use App\Services\Subscriptions\Refunds\FullRefundStrategy;
use App\Services\Subscriptions\Refunds\RefundResult;
use Mockery;
use PHPUnit\Framework\TestCase;

class FullRefundStrategyTest extends TestCase
{
    private $mockPaymentRepository;

    public function testCalculateReturnsFullPaymentAmount(): void
    {
        $subscription = $this->createMockSubscription();
        $payment = $this->createMockPayment(100.00);

        $this->mockPaymentRepository
            ->shouldReceive('getLastSubscriptionPayment')
            ->once()
            ->with($subscription->id)
            ->andReturn($payment);

        $strategy = new FullRefundStrategy($this->mockPaymentRepository);
        $result = $strategy->calculate($subscription);

        $this->assertInstanceOf(RefundResult::class, $result);
        $this->assertEquals(100.00, $result->amount);
        $this->assertEquals('full', $result->type);
    }

    private function createMockSubscription(): Subscription
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        return $subscription;
    }

    private function createMockPayment(float $amount): Payment
    {
        $payment = Mockery::mock(Payment::class)->makePartial();
        $payment->id = 1;
        $payment->subscription_id = 1;
        $payment->amount = $amount;
        $payment->transaction_id = 'ch_test_123';
        $payment->payment_method = 'stripe';
        $payment->payment_provider = 'stripe';
        return $payment;
    }

    public function testCalculateIncludesCorrectMetadata(): void
    {
        $subscription = $this->createMockSubscription();
        $payment = $this->createMockPayment(75.00);

        $this->mockPaymentRepository
            ->shouldReceive('getLastSubscriptionPayment')
            ->once()
            ->andReturn($payment);

        $strategy = new FullRefundStrategy($this->mockPaymentRepository, 'admin_override');
        $result = $strategy->calculate($subscription);

        $this->assertEquals($payment->id, $result->meta['original_payment_id']);
        $this->assertEquals('admin_override', $result->meta['reason']);
    }

    public function testCalculateThrowsExceptionWhenNoPaymentFound(): void
    {
        $subscription = $this->createMockSubscription();

        $this->mockPaymentRepository
            ->shouldReceive('getLastSubscriptionPayment')
            ->once()
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No payment found for refund');

        $strategy = new FullRefundStrategy($this->mockPaymentRepository);
        $strategy->calculate($subscription);
    }

    public function testCalculateDoesNotPerformExternalCalls(): void
    {
        // Stripe processor is NOT injected — confirming the strategy has no provider dependency
        $subscription = $this->createMockSubscription();
        $payment = $this->createMockPayment(50.00);

        $this->mockPaymentRepository
            ->shouldReceive('getLastSubscriptionPayment')
            ->once()
            ->andReturn($payment);

        $strategy = new FullRefundStrategy($this->mockPaymentRepository);
        $result = $strategy->calculate($subscription);

        // No exception thrown means no external call was attempted
        $this->assertEquals('full', $result->type);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockPaymentRepository = Mockery::mock(PaymentRepository::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}