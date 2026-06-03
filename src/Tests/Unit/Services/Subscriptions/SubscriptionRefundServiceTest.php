<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Models\Payment;
use App\Models\Subscription;
use App\Repositories\Billing\PaymentRepository;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use App\Services\Billing\Stripe\StripeRefundGateway;
use App\Services\Subscriptions\SubscriptionRefundService;
use DateTime;
use Exception;
use Mockery;
use PHPUnit\Framework\TestCase;

class SubscriptionRefundServiceTest extends TestCase
{
    private SubscriptionRefundService $service;
    private $mockPaymentRepository;
    private $mockStripeProcessor;
    private $mockDatabase;
    private StripeRefundGateway $stripeRefundGateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockPaymentRepository = Mockery::mock(PaymentRepository::class);
        $this->mockStripeProcessor = Mockery::mock(StripePaymentProcessor::class);
        $this->mockDatabase = Mockery::mock(Database::class);
        $this->stripeRefundGateway = Mockery::mock(StripeRefundGateway::class);

        $this->service = new SubscriptionRefundService(
            $this->mockPaymentRepository,
            $this->stripeRefundGateway,
            $this->mockDatabase
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testCreateFullRefundSuccessfully()
    {
        $subscription = $this->createMockSubscription();
        $lastPayment = $this->createMockPayment();
        $lastPayment->transaction_id = 'ch_123';
        $reason = 'customer_request';

        // Mock database transaction
        $this->mockDatabase
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        // Mock getting last payment
        $this->mockPaymentRepository
            ->shouldReceive('getLastSubscriptionPayment')
            ->once()
            ->with($subscription->id)
            ->andReturn($lastPayment);

        // Mock Stripe refund
        $this->stripeRefundGateway
            ->shouldReceive('refund')
            ->once()
            ->with(
                $lastPayment->transaction_id,
                $lastPayment->amount,
                ['reason' => $reason]
            )
            ->andReturn([
                'success' => true,
                'refund_id' => 'refund_123',
                'amount' => $lastPayment->amount
            ]);

        // Mock creating refund payment
        $refundPayment = $this->createMockPayment();
        $refundPayment->id = 999;
        $this->mockPaymentRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($refundPayment);

        $result = $this->service->createFullRefund($subscription, $reason);

        $this->assertTrue($result['success']);
        $this->assertEquals($refundPayment, $result['refund_payment']);
        $this->assertEquals($lastPayment->amount, $result['amount']);
        $this->assertNotNull($result['provider_refund']);
    }

    public function testCreateFullRefundUsesPaymentIntentWhenTransactionIdIsStripeSubscription(): void
    {
        $subscription = $this->createMockSubscription();
        $subscription->member_id = 44;

        $lastPayment = $this->createMockPayment();
        $lastPayment->transaction_id = 'sub_1TdvRvGvaZO1S9EXmJA1uRME';
        $lastPayment->payment_intent_id = 'pi_refundable_123';

        $this->mockDatabase
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->mockPaymentRepository
            ->shouldReceive('getLastSubscriptionPayment')
            ->once()
            ->with($subscription->id)
            ->andReturn($lastPayment);

        $this->stripeRefundGateway
            ->shouldReceive('refund')
            ->once()
            ->with('pi_refundable_123', $lastPayment->amount, ['reason' => 'customer_request'])
            ->andReturn([
                'success' => true,
                'refund_id' => 're_123',
                'amount' => $lastPayment->amount,
            ]);

        $refundPayment = $this->createMockPayment();

        $this->mockPaymentRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) use ($subscription) {
                return $data['member_id'] === $subscription->member_id
                    && $data['transaction_id'] === 're_123'
                    && $data['metadata']['original_transaction_id'] === 'sub_1TdvRvGvaZO1S9EXmJA1uRME'
                    && $data['metadata']['payment_intent_id'] === 'pi_refundable_123'
                    && $data['metadata']['provider_transaction_id'] === 'pi_refundable_123';
            }))
            ->andReturn($refundPayment);

        $result = $this->service->createFullRefund($subscription);

        $this->assertTrue($result['success']);
    }

    public function testCreateFullRefundResolvesPaymentIntentFromInvoiceWhenPaymentRowOnlyHasSubscriptionId(): void
    {
        $subscription = $this->createMockSubscription();
        $subscription->member_id = 44;

        $lastPayment = $this->createMockPayment();
        $lastPayment->transaction_id = 'sub_1TeAvQGvaZO1S9EXRHFrys06';
        $lastPayment->payment_intent_id = null;
        $lastPayment->stripe_invoice_id = 'in_1TeAvQGvaZO1S9EXiQOBs2cO';

        $this->mockDatabase
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->mockPaymentRepository
            ->shouldReceive('getLastSubscriptionPayment')
            ->once()
            ->with($subscription->id)
            ->andReturn($lastPayment);

        $this->stripeRefundGateway
            ->shouldReceive('findRefundableTransactionForInvoice')
            ->once()
            ->with('in_1TeAvQGvaZO1S9EXiQOBs2cO')
            ->andReturn('pi_from_invoice_123');

        $this->stripeRefundGateway
            ->shouldReceive('refund')
            ->once()
            ->with('pi_from_invoice_123', $lastPayment->amount, ['reason' => 'customer_request'])
            ->andReturn([
                'success' => true,
                'refund_id' => 're_123',
                'amount' => $lastPayment->amount,
            ]);

        $refundPayment = $this->createMockPayment();

        $this->mockPaymentRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['metadata']['original_transaction_id'] === 'sub_1TeAvQGvaZO1S9EXRHFrys06'
                    && $data['metadata']['stripe_invoice_id'] === 'in_1TeAvQGvaZO1S9EXiQOBs2cO'
                    && $data['metadata']['provider_transaction_id'] === 'pi_from_invoice_123';
            }))
            ->andReturn($refundPayment);

        $result = $this->service->createFullRefund($subscription);

        $this->assertTrue($result['success']);
    }

    public function testCreateFullRefundFailsWhenInvoiceDoesNotExposePaymentIntent(): void
    {
        $subscription = $this->createMockSubscription();

        $lastPayment = $this->createMockPayment();
        $lastPayment->transaction_id = 'sub_1TeAvQGvaZO1S9EXRHFrys06';
        $lastPayment->payment_intent_id = null;
        $lastPayment->stripe_invoice_id = 'in_1TeAvQGvaZO1S9EXiQOBs2cO';

        $this->mockDatabase
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->mockPaymentRepository
            ->shouldReceive('getLastSubscriptionPayment')
            ->once()
            ->with($subscription->id)
            ->andReturn($lastPayment);

        $this->stripeRefundGateway
            ->shouldReceive('findRefundableTransactionForInvoice')
            ->once()
            ->with('in_1TeAvQGvaZO1S9EXiQOBs2cO')
            ->andReturn(null);

        $this->stripeRefundGateway->shouldNotReceive('refund');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('invoice in_1TeAvQGvaZO1S9EXiQOBs2cO did not expose a refundable payment intent or charge');

        $this->service->createFullRefund($subscription);
    }

    public function testCreateFullRefundFailsBeforeStripeWhenNoRefundableProviderIdExists(): void
    {
        $subscription = $this->createMockSubscription();
        $lastPayment = $this->createMockPayment();
        $lastPayment->transaction_id = 'sub_1TdvRvGvaZO1S9EXmJA1uRME';
        $lastPayment->payment_intent_id = null;

        $this->mockDatabase
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->mockPaymentRepository
            ->shouldReceive('getLastSubscriptionPayment')
            ->once()
            ->with($subscription->id)
            ->andReturn($lastPayment);

        $this->stripeRefundGateway->shouldNotReceive('refund');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No refundable Stripe charge or payment intent found for payment #1');

        $this->service->createFullRefund($subscription);
    }

    public function testCreateFullRefundThrowsExceptionWhenNoPaymentFound()
    {
        $subscription = $this->createMockSubscription();

        $this->mockDatabase
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->mockPaymentRepository
            ->shouldReceive('getLastSubscriptionPayment')
            ->once()
            ->with($subscription->id)
            ->andReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No payment found for refund');

        $this->service->createFullRefund($subscription);
    }

    public function testCreateFullRefundThrowsExceptionWhenProviderRefundFails()
    {
        $subscription = $this->createMockSubscription();
        $lastPayment = $this->createMockPayment();

        $this->mockDatabase
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->mockPaymentRepository
            ->shouldReceive('getLastSubscriptionPayment')
            ->once()
            ->andReturn($lastPayment);

        $this->stripeRefundGateway
            ->shouldReceive('refund')
            ->once()
            ->andReturn([
                'success' => false,
                'message' => 'Payment already refunded'
            ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Provider refund failed: Payment already refunded');

        $this->service->createFullRefund($subscription);
    }

    public function testCreateFullRefundWithoutStripeSubscription()
    {
        $subscription = $this->createMockSubscription();
        $subscription->stripe_subscription_id = null;
        $subscription->shouldReceive('hasStripeSubscription')
            ->andReturn(false);
        $lastPayment = $this->createMockPayment();
        $lastPayment->transaction_id = null;

        $this->mockDatabase
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->mockPaymentRepository
            ->shouldReceive('getLastSubscriptionPayment')
            ->once()
            ->andReturn($lastPayment);

        $refundPayment = $this->createMockPayment();
        $this->mockPaymentRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) use ($lastPayment) {
                return $data['amount'] === -$lastPayment->amount
                    && $data['subscription_id'] === $lastPayment->subscription_id
                    && $data['metadata']['provider_refund'] === false;
            }))
            ->andReturn($refundPayment);

        $result = $this->service->createFullRefund($subscription);

        $this->assertTrue($result['success']);
        $this->assertNull($result['provider_refund']);
    }

    public function testCreateProRatedRefundSuccessfully(): void
    {
        $subscription = $this->createMockSubscriptionWithDates();
        $lastPayment = $this->createMockPayment();

        $this->mockDatabase
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        // Strategy calculate() is called twice: once as a pre-check outside the
        // transaction, and once inside. Both calls go through getLastSubscriptionPayment.
        $this->mockPaymentRepository
            ->shouldReceive('getLastSubscriptionPayment')
            ->andReturn($lastPayment);

        $expectedRefundAmount = ($subscription->price / 30) * 15;

        $this->stripeRefundGateway
            ->shouldReceive('refund')
            ->once()
            ->andReturn([
                'success' => true,
                'refund_id' => 'refund_123',
            ]);

        $refundPayment = $this->createMockPayment();

        $this->mockPaymentRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) use ($expectedRefundAmount) {
                return abs($data['amount'] + $expectedRefundAmount) < 0.01
                    && $data['metadata']['refund_type'] === 'pro_rated_cancellation';
            }))
            ->andReturn($refundPayment);

        $result = $this->service->createProRatedRefund($subscription);

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['unused_days']);
        $this->assertGreaterThan(0, $result['amount']);
    }

    public function testCreateProRatedRefundFailsWithNoUnusedTime()
    {
        $subscription = $this->createMockSubscriptionWithDates();

        // Set dates so there's no unused time
        $subscription->last_payment_date = new DateTime('-60 days');
        $subscription->end_date = new DateTime('-30 days');

        $payment = Mockery::mock(Payment::class)->makePartial();
        $payment->amount = 100;
        $payment->transaction_id = 'txn_123';

        $this->mockPaymentRepository
            ->shouldReceive('getLastSubscriptionPayment')
            ->once()
            ->with($subscription->id)
            ->andReturn($payment);

        $result = $this->service->createProRatedRefund($subscription);

        $this->assertFalse($result['success']);
        $this->assertEquals('No unused time remaining', $result['message']);
        $this->assertEquals(0, $result['unused_days']);
    }

    public function testCreateProRatedRefundThrowsExceptionForMissingDates()
    {
        $subscription = $this->createMockSubscription();
        $subscription->end_date = null;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot calculate pro-rated refund: missing dates');

        $this->service->createProRatedRefund($subscription);
    }

    public function testCreateProRatedRefundIncludesMetadata(): void
    {
        $subscription = $this->createMockSubscriptionWithDates();
        $lastPayment = $this->createMockPayment();

        $this->mockDatabase
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->mockPaymentRepository
            ->shouldReceive('getLastSubscriptionPayment')
            ->andReturn($lastPayment);

        $this->stripeRefundGateway
            ->shouldReceive('refund')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::any(),
                Mockery::on(function ($options) {
                    return isset($options['metadata']['unused_days'])
                        && isset($options['metadata']['total_days']);
                }),
            )
            ->andReturn(['success' => true, 'refund_id' => 'refund_123']);

        $refundPayment = $this->createMockPayment();

        $this->mockPaymentRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($refundPayment);

        $result = $this->service->createProRatedRefund($subscription);

        $this->assertTrue($result['success']);
    }

    private function createMockSubscription()
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->site_id = 1;
        $subscription->price = 100.00;
        $subscription->currency = 'USD';
        $subscription->stripe_subscription_id = 'sub_123';

        $subscription->shouldReceive('hasStripeSubscription')
            ->andReturn(true)
            ->byDefault();

        return $subscription;
    }

    private function createMockSubscriptionWithDates()
    {
        $subscription = $this->createMockSubscription();
        $subscription->last_payment_date = new DateTime('-15 days');
        $subscription->end_date = new DateTime('+15 days');
        return $subscription;
    }

    private function createMockPayment()
    {
        $payment = Mockery::mock(Payment::class)->makePartial();
        $payment->id = 1;
        $payment->subscription_id = 1;
        $payment->amount = 100.00;
        $payment->transaction_id = 'ch_123';
        $payment->payment_intent_id = null;
        $payment->stripe_invoice_id = null;
        $payment->payment_method = 'stripe';
        $payment->payment_provider = 'stripe';
        return $payment;
    }
}
