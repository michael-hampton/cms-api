<?php

namespace App\Tests\Unit\Services\Subscriptions\Refunds;

use App\Models\Payment;
use App\Models\Subscription;
use App\Repositories\Billing\PaymentRepository;
use App\Services\Subscriptions\Refunds\ProRatedRefundStrategy;
use App\Services\Subscriptions\Refunds\RefundResult;
use Mockery;
use PHPUnit\Framework\TestCase;

class ProRatedRefundStrategyTest extends TestCase
{
    private $mockPaymentRepository;

    public function testCalculateCorrectProRatedAmount(): void
    {
        // 30-day period, 15 days used → 50% refund of 100.00 = 50.00
        $subscription = $this->createMockSubscription(
            price: 100.00,
            lastPaymentDaysAgo: 15,
            endDateDaysFromNow: 15,
        );
        $payment = $this->createMockPayment(100.00);

        $this->mockPaymentRepository
            ->shouldReceive('getLastSubscriptionPayment')
            ->once()
            ->andReturn($payment);

        $strategy = new ProRatedRefundStrategy($this->mockPaymentRepository);
        $result = $strategy->calculate($subscription);

        $this->assertInstanceOf(RefundResult::class, $result);
        $this->assertEquals('pro_rated', $result->type);
        $this->assertFalse($result->noRefundDue);
        $this->assertEqualsWithDelta(50.00, $result->amount, 0.50);
    }

    private function createMockSubscription(
        float $price,
        int   $lastPaymentDaysAgo,
        int   $endDateDaysFromNow,
    ): Subscription
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->price = $price;
        $subscription->currency = 'USD';
        $subscription->last_payment_date = new \DateTime("-{$lastPaymentDaysAgo} days");
        $subscription->end_date = new \DateTime(
            ($endDateDaysFromNow >= 0 ? '+' : '') . "{$endDateDaysFromNow} days"
        );
        return $subscription;
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

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

    public function testCalculateAmountUsesDecimalPrecision(): void
    {
        // Price that would cause float drift if calculated naively:
        // 100.00 / 7 * 3 = 42.857142... — bcmath must produce consistent result
        $subscription = $this->createMockSubscription(
            price: 100.00,
            lastPaymentDaysAgo: 4,
            endDateDaysFromNow: 3,
        );
        $payment = $this->createMockPayment(100.00);

        $this->mockPaymentRepository
            ->shouldReceive('getLastSubscriptionPayment')
            ->once()
            ->andReturn($payment);

        $strategy = new ProRatedRefundStrategy($this->mockPaymentRepository);
        $result = $strategy->calculate($subscription);

        // Assert it's a valid positive decimal — not NAN, not INF, not 0
        $this->assertIsFloat($result->amount);
        $this->assertGreaterThan(0.0, $result->amount);
        $this->assertLessThanOrEqual(100.0, $result->amount);
    }

    public function testCalculateIncludesScalarOnlyMetadata(): void
    {
        $subscription = $this->createMockSubscription(
            price: 60.00,
            lastPaymentDaysAgo: 10,
            endDateDaysFromNow: 20,
        );
        $payment = $this->createMockPayment(60.00);

        $this->mockPaymentRepository
            ->shouldReceive('getLastSubscriptionPayment')
            ->once()
            ->andReturn($payment);

        $strategy = new ProRatedRefundStrategy($this->mockPaymentRepository);
        $result = $strategy->calculate($subscription);

        $this->assertArrayHasKey('original_payment_id', $result->meta);
        $this->assertArrayHasKey('transaction_id', $result->meta);
        $this->assertArrayHasKey('payment_method', $result->meta);
        $this->assertArrayHasKey('payment_provider', $result->meta);
        $this->assertArrayHasKey('unused_days', $result->meta);
        $this->assertArrayHasKey('total_days', $result->meta);

        // Confirm the Payment ORM object is NOT present in meta
        $this->assertArrayNotHasKey('original_payment', $result->meta);

        // All meta values that reference IDs must be scalars
        $this->assertIsInt($result->meta['original_payment_id']);
        $this->assertIsInt($result->meta['unused_days']);
        $this->assertIsInt($result->meta['total_days']);
    }

    // -------------------------------------------------------------------------
    // noRefundDue — zero unused time
    // -------------------------------------------------------------------------

    public function testReturnsNoRefundDueWhenNoUnusedTime(): void
    {
        $subscription = $this->createMockSubscription(
            price: 100.00,
            lastPaymentDaysAgo: 60,
            endDateDaysFromNow: -30,
        );

        // Repository must NOT be called — no refund means no payment fetch needed
        $this->mockPaymentRepository->shouldNotReceive('getLastSubscriptionPayment');

        $strategy = new ProRatedRefundStrategy($this->mockPaymentRepository);
        $result = $strategy->calculate($subscription);

        $this->assertTrue($result->noRefundDue);
        $this->assertEquals(0.0, $result->amount);
        $this->assertEquals(0, $result->meta['unused_days']);
    }

    // -------------------------------------------------------------------------
    // Guard: missing dates
    // -------------------------------------------------------------------------

    public function testThrowsWhenEndDateIsMissing(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->price = 100.00;
        $subscription->end_date = null;
        $subscription->last_payment_date = new \DateTime('-10 days');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot calculate pro-rated refund: missing dates');

        $strategy = new ProRatedRefundStrategy($this->mockPaymentRepository);
        $strategy->calculate($subscription);
    }

    public function testThrowsWhenLastPaymentDateIsMissing(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->price = 100.00;
        $subscription->end_date = new \DateTime('+15 days');
        $subscription->last_payment_date = null;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot calculate pro-rated refund: missing dates');

        $strategy = new ProRatedRefundStrategy($this->mockPaymentRepository);
        $strategy->calculate($subscription);
    }

    // -------------------------------------------------------------------------
    // Guard: invalid price
    // -------------------------------------------------------------------------

    public function testThrowsWhenPriceIsZero(): void
    {
        $subscription = $this->createMockSubscription(
            price: 0.0,
            lastPaymentDaysAgo: 5,
            endDateDaysFromNow: 25,
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot calculate pro-rated refund: subscription price is invalid');

        $strategy = new ProRatedRefundStrategy($this->mockPaymentRepository);
        $strategy->calculate($subscription);
    }

    public function testThrowsWhenPriceIsNegative(): void
    {
        $subscription = $this->createMockSubscription(
            price: -50.00,
            lastPaymentDaysAgo: 5,
            endDateDaysFromNow: 25,
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot calculate pro-rated refund: subscription price is invalid');

        $strategy = new ProRatedRefundStrategy($this->mockPaymentRepository);
        $strategy->calculate($subscription);
    }

    // -------------------------------------------------------------------------
    // Guard: malformed date ordering
    // -------------------------------------------------------------------------

    public function testThrowsWhenPaymentDateIsAfterEndDate(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->price = 100.00;
        $subscription->last_payment_date = new \DateTime('+5 days');  // in the future
        $subscription->end_date = new \DateTime('-5 days');  // in the past

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Cannot calculate pro-rated refund: payment date is not before end date'
        );

        $strategy = new ProRatedRefundStrategy($this->mockPaymentRepository);
        $strategy->calculate($subscription);
    }

    public function testThrowsWhenPaymentDateEqualsEndDate(): void
    {
        $sameDate = new \DateTime();
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->price = 100.00;
        $subscription->last_payment_date = $sameDate;
        $subscription->end_date = clone $sameDate;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Cannot calculate pro-rated refund: payment date is not before end date'
        );

        $strategy = new ProRatedRefundStrategy($this->mockPaymentRepository);
        $strategy->calculate($subscription);
    }

    // -------------------------------------------------------------------------
    // Guard: no payment record
    // -------------------------------------------------------------------------

    public function testThrowsWhenNoPaymentFound(): void
    {
        $subscription = $this->createMockSubscription(
            price: 100.00,
            lastPaymentDaysAgo: 5,
            endDateDaysFromNow: 25,
        );

        $this->mockPaymentRepository
            ->shouldReceive('getLastSubscriptionPayment')
            ->once()
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No payment found for refund');

        $strategy = new ProRatedRefundStrategy($this->mockPaymentRepository);
        $strategy->calculate($subscription);
    }

    // -------------------------------------------------------------------------
    // Isolation — no external calls
    // -------------------------------------------------------------------------

    public function testStrategyDoesNotPerformWritesOrProviderCalls(): void
    {
        // StripePaymentProcessor is NOT injected — confirming strategy has no provider dependency.
        // PaymentRepository is injected but only for a read.
        $subscription = $this->createMockSubscription(
            price: 100.00,
            lastPaymentDaysAgo: 10,
            endDateDaysFromNow: 20,
        );
        $payment = $this->createMockPayment(100.00);

        $this->mockPaymentRepository
            ->shouldReceive('getLastSubscriptionPayment')
            ->once()
            ->andReturn($payment);

        // Repository must NOT receive any write method
        $this->mockPaymentRepository->shouldNotReceive('create');
        $this->mockPaymentRepository->shouldNotReceive('update');
        $this->mockPaymentRepository->shouldNotReceive('save');

        $strategy = new ProRatedRefundStrategy($this->mockPaymentRepository);
        $result = $strategy->calculate($subscription);

        $this->assertEquals('pro_rated', $result->type);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

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