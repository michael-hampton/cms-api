<?php

namespace App\Tests\Unit\Services\Subscriptions\Refunds;

use App\Models\Payment;
use App\Models\Subscription;
use App\Repositories\Billing\PaymentRepository;
use App\Services\Subscriptions\Refunds\ManualRefundStrategy;
use App\Services\Subscriptions\Refunds\RefundResult;
use Mockery;
use PHPUnit\Framework\TestCase;

class ManualRefundStrategyTest extends TestCase
{
    private $mockPaymentRepository;

    public function testCalculateReturnsExactOverrideAmount(): void
    {
        $subscription = $this->createMockSubscription();
        $payment = $this->createMockPayment(100.00);

        $this->mockPaymentRepository
            ->shouldReceive('getLastSubscriptionPayment')
            ->once()
            ->with($subscription->id)
            ->andReturn($payment);

        $strategy = new ManualRefundStrategy($this->mockPaymentRepository, 40.00);
        $result = $strategy->calculate($subscription);

        $this->assertInstanceOf(RefundResult::class, $result);
        $this->assertEquals(40.00, $result->amount);
        $this->assertEquals('manual', $result->type);
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

    public function testCalculateIncludesOriginalPaymentIdAndAmountInMetadata(): void
    {
        $subscription = $this->createMockSubscription();
        $payment = $this->createMockPayment(100.00);

        $this->mockPaymentRepository
            ->shouldReceive('getLastSubscriptionPayment')
            ->once()
            ->andReturn($payment);

        $strategy = new ManualRefundStrategy($this->mockPaymentRepository, 40.00);
        $result = $strategy->calculate($subscription);

        $this->assertEquals($payment->id, $result->meta['original_payment_id']);
        $this->assertEquals(100.00, $result->meta['original_amount']);
    }

    public function testCalculateThrowsExceptionWhenOverrideAmountIsZero(): void
    {
        $subscription = $this->createMockSubscription();
        $payment = $this->createMockPayment(100.00);

        $this->mockPaymentRepository
            ->shouldReceive('getLastSubscriptionPayment')
            ->once()
            ->andReturn($payment);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Refund amount must be greater than zero');

        $strategy = new ManualRefundStrategy($this->mockPaymentRepository, 0.0);
        $strategy->calculate($subscription);
    }

    public function testCalculateThrowsExceptionWhenOverrideAmountIsNegative(): void
    {
        $subscription = $this->createMockSubscription();
        $payment = $this->createMockPayment(100.00);

        $this->mockPaymentRepository
            ->shouldReceive('getLastSubscriptionPayment')
            ->once()
            ->andReturn($payment);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Refund amount must be greater than zero');

        $strategy = new ManualRefundStrategy($this->mockPaymentRepository, -10.00);
        $strategy->calculate($subscription);
    }

    public function testCalculateThrowsExceptionWhenOverrideExceedsOriginalPayment(): void
    {
        $subscription = $this->createMockSubscription();
        $payment = $this->createMockPayment(100.00);

        $this->mockPaymentRepository
            ->shouldReceive('getLastSubscriptionPayment')
            ->once()
            ->andReturn($payment);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Refund amount cannot exceed original payment');

        $strategy = new ManualRefundStrategy($this->mockPaymentRepository, 150.00);
        $strategy->calculate($subscription);
    }

    public function testCalculateAllowsOverrideEqualToOriginalPayment(): void
    {
        $subscription = $this->createMockSubscription();
        $payment = $this->createMockPayment(100.00);

        $this->mockPaymentRepository
            ->shouldReceive('getLastSubscriptionPayment')
            ->once()
            ->andReturn($payment);

        $strategy = new ManualRefundStrategy($this->mockPaymentRepository, 100.00);
        $result = $strategy->calculate($subscription);

        $this->assertEquals(100.00, $result->amount);
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

        $strategy = new ManualRefundStrategy($this->mockPaymentRepository, 50.00);
        $strategy->calculate($subscription);
    }

    public function testCalculateDoesNotPerformExternalCalls(): void
    {
        // Confirm strategy has no Stripe or DB-write dependency
        $subscription = $this->createMockSubscription();
        $payment = $this->createMockPayment(100.00);

        $this->mockPaymentRepository
            ->shouldReceive('getLastSubscriptionPayment')
            ->once()
            ->andReturn($payment);

        $strategy = new ManualRefundStrategy($this->mockPaymentRepository, 25.00);
        $result = $strategy->calculate($subscription);

        $this->assertEquals('manual', $result->type);
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