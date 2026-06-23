<?php

namespace App\Tests\Unit\Services\Billing;

use App\Models\Subscription;
use App\Repositories\Billing\PaymentRepository;
use App\Services\Billing\PaymentRefundPreviewService;
use DateTimeImmutable;
use Mockery;
use PHPUnit\Framework\TestCase;

class PaymentRefundPreviewServiceTest extends TestCase
{
    private PaymentRepository $payments;
    private PaymentRefundPreviewService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->payments = Mockery::mock(PaymentRepository::class);
        $this->service = new PaymentRefundPreviewService($this->payments);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testSummaryMarksSubscriptionPaymentRefundable(): void
    {
        $payment = $this->payment(amount: 30.00, subscriptionId: 10);

        $summary = $this->service->summaryForPayment($payment);

        self::assertTrue($summary['eligible']);
        self::assertSame('subscription', $summary['mode']);
        self::assertSame(30.00, $summary['max_refundable_amount']);
        self::assertSame('pro_rated', $summary['suggested_refund_type']);
    }

    public function testSummaryMarksOrderPaymentRefundableSeparatelyFromSubscriptionPayment(): void
    {
        $payment = $this->payment(amount: 42.50, orderId: 77);

        $summary = $this->service->summaryForPayment($payment);

        self::assertTrue($summary['eligible']);
        self::assertSame('order', $summary['mode']);
        self::assertSame(42.50, $summary['max_refundable_amount']);
        self::assertSame('full', $summary['suggested_refund_type']);
    }

    public function testSummaryRejectsRefundRowsAndManualRows(): void
    {
        $payment = $this->payment(amount: -10.00, status: 'completed');

        $summary = $this->service->summaryForPayment($payment);

        self::assertFalse($summary['eligible']);
        self::assertNull($summary['mode']);
        self::assertNotEmpty($summary['reason_not_eligible']);
    }

    public function testSubscriptionPreviewCalculatesProRatedUnusedTermAgainstRemainingRefundableAmount(): void
    {
        $payment = $this->payment(amount: 30.00, subscriptionId: 10);
        $subscription = new Subscription();
        $subscription->id = 10;
        $subscription->member_id = 5;
        $subscription->currency = 'GBP';
        $subscription->last_payment_date = new DateTimeImmutable('-15 days');
        $subscription->end_date = new DateTimeImmutable('+15 days');

        $preview = $this->service->subscriptionPreview($payment, $subscription);

        self::assertSame(10, $preview['subscription_id']);
        self::assertSame(30.00, $preview['max_refundable_amount']);
        self::assertSame(30, $preview['total_days']);
        self::assertSame(15, $preview['unused_days']);
        self::assertSame(15.00, $preview['suggested_refund_amount']);
        self::assertContains('pro_rated', $preview['available_actions']);
        self::assertContains('manual', $preview['available_actions']);
    }

    public function testSubscriptionPreviewWarnsWhenDatesAreMissing(): void
    {
        $payment = $this->payment(amount: 30.00, subscriptionId: 10);
        $subscription = new Subscription();
        $subscription->id = 10;
        $subscription->member_id = 5;
        $subscription->currency = 'GBP';

        $preview = $this->service->subscriptionPreview($payment, $subscription);

        self::assertNull($preview['total_days']);
        self::assertSame(0.00, $preview['suggested_refund_amount']);
        self::assertContains('cancel_at_period_end', $preview['available_actions']);
        self::assertNotEmpty($preview['warnings']);
    }

    private function payment(
        float $amount,
        string $status = 'completed',
        ?int $subscriptionId = null,
        ?int $orderId = null,
    ): object {
        return (object) [
            'id' => 99,
            'site_id' => 1,
            'amount' => $amount,
            'currency' => 'GBP',
            'status' => $status,
            'subscription_id' => $subscriptionId,
            'order_id' => $orderId,
            'payment_provider' => 'stripe',
            'payment_intent_id' => 'pi_123',
            'transaction_id' => 'ch_123',
            'stripe_invoice_id' => 'in_123',
            'paid_at' => new DateTimeImmutable('-15 days'),
        ];
    }
}
