<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Stripe\StripeInvoiceEvent;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Events\Subscriptions\InvoicePaymentFailed;
use App\Events\Subscriptions\InvoicePaymentSucceeded;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Support\Logger;
use App\Models\Payment;
use App\Models\Subscription;
use App\Repositories\Billing\PaymentRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionInvoiceHandler;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class SubscriptionInvoiceHandlerTest extends FunctionalTestCase
{
    private SubscriptionRepository&MockInterface $subscriptionRepository;
    private PaymentRepository&MockInterface $paymentRepository;
    private EventDispatcher&MockInterface $eventDispatcher;
    private Logger&MockInterface $logger;
    private SubscriptionInvoiceHandler $handler;
    private Database $databaseMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->paymentRepository = Mockery::mock(PaymentRepository::class);
        $this->eventDispatcher = Mockery::mock(EventDispatcher::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();
        $this->databaseMock = Mockery::mock(Database::class);

        $this->handler = new SubscriptionInvoiceHandler(
            subscriptionRepository: $this->subscriptionRepository,
            paymentRepository: $this->paymentRepository,
            eventDispatcher: $this->eventDispatcher,
            logger: $this->logger,
            database: $this->databaseMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── invoice.payment_succeeded ──────────────────────────────────────────

    public function test_it_records_payment_and_updates_billing_fields_on_success(): void
    {
        $subscription = $this->makeSubscription('sub_abc123')->makePartial();
        $payment = Mockery::mock(Payment::class);
        $event = $this->makeSucceededEvent('sub_abc123');

        $this->mockTransactionReturning(['payment' => $payment, 'subscription' => $subscription]);

        $this->paymentRepository
            ->shouldReceive('recordInvoicePaymentSucceeded')
            ->once()
            ->with(
                $subscription->id,
                'in_test123',
                'pi_test123',
                Mockery::type('int'),
                'GBP',
                Mockery::type(\DateTimeImmutable::class),
                $subscription->member_id,
                null,
                null,
            )
            ->andReturn($payment);

        $subscription->shouldReceive('update')
            ->once()
            ->with(Mockery::on(fn($data) => $data['status'] === SubscriptionStatus::ACTIVE->value &&
                isset($data['last_payment_date']) &&
                isset($data['current_period_end']) &&
                isset($data['end_date'])
            ));

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::type(InvoicePaymentSucceeded::class));

        $this->handler->handlePaymentSucceeded($event);
        $this->assertTrue(true);
    }

    public function test_it_forwards_hosted_invoice_url_and_raw_payload_to_the_repository(): void
    {
        $subscription = $this->makeSubscription('sub_abc123');
        $payment = Mockery::mock(Payment::class);
        $event = new StripeInvoiceEvent(
            type: 'invoice.payment_succeeded',
            invoiceId: 'in_test123',
            stripeSubscriptionId: 'sub_abc123',
            paymentIntentId: 'pi_test123',
            amountPaid: 2900,
            currency: 'GBP',
            periodStart: null,
            periodEnd: null,
            failureReason: null,
            failureCode: null,
            hostedInvoiceUrl: 'https://invoice.stripe.com/inv_test123',
            rawPayload: '{"id":"in_test123"}',
        );

        $this->subscriptionRepository
            ->shouldReceive('findSubscriptionByStripeId')
            ->with('sub_abc123')
            ->andReturn($subscription);

        $this->mockTransactionReturning(['payment' => $payment, 'subscription' => $subscription]);

        $this->paymentRepository
            ->shouldReceive('recordInvoicePaymentSucceeded')
            ->once()
            ->with(
                $subscription->id,
                'in_test123',
                'pi_test123',
                Mockery::type('int'),
                'GBP',
                Mockery::type(\DateTimeImmutable::class),
                $subscription->member_id,
                'https://invoice.stripe.com/inv_test123',
                '{"id":"in_test123"}',
            )
            ->andReturn($payment);

        $subscription->shouldReceive('update');
        $this->eventDispatcher->shouldReceive('dispatch');

        $this->handler->handlePaymentSucceeded($event);

        $this->assertTrue(true);
    }

    public function test_it_wraps_payment_succeeded_in_a_transaction(): void
    {
        $subscription = $this->makeSubscription('sub_abc123');
        $payment = Mockery::mock(Payment::class);
        $event = $this->makeSucceededEvent('sub_abc123');

        $transactionCalled = false;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function (callable $callback) use (&$transactionCalled, $payment, $subscription) {
                $transactionCalled = true;
                return $callback();
            });

        $this->paymentRepository->shouldReceive('recordInvoicePaymentSucceeded')->andReturn($payment);
        $subscription->shouldReceive('update');
        $this->eventDispatcher->shouldReceive('dispatch');

        $this->handler->handlePaymentSucceeded($event);

        $this->assertTrue($transactionCalled, 'Expected Database::transaction() to be called');
    }

    public function test_it_emits_invoice_payment_succeeded_event_with_correct_data(): void
    {
        $subscription = $this->makeSubscription('sub_abc123');
        $payment = Mockery::mock(Payment::class);
        $event = $this->makeSucceededEvent('sub_abc123');

        $this->mockTransactionReturning(['payment' => $payment, 'subscription' => $subscription]);
        $this->paymentRepository->shouldReceive('recordInvoicePaymentSucceeded')->andReturn($payment);
        $subscription->shouldReceive('update');

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(function ($dispatched) use ($subscription, $payment) {
                return $dispatched instanceof InvoicePaymentSucceeded
                    && $dispatched->subscription === $subscription
                    && $dispatched->payment === $payment;
            }));

        $this->handler->handlePaymentSucceeded($event);
        $this->assertTrue(true);
    }

    public function test_it_throws_when_no_subscription_found_for_succeeded_event(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/No subscription found/');

        $this->subscriptionRepository->shouldReceive('findSubscriptionByStripeId')->andReturn(null);

        // Subscription::where() returns null — simulated by not setting up a match
        $event = $this->makeSucceededEvent('sub_not_found');

        $this->handler->handlePaymentSucceeded($event);
    }

    public function test_it_throws_when_stripe_subscription_id_is_missing_on_succeeded(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/missing stripe subscription ID/');

        $event = new StripeInvoiceEvent(
            type: 'invoice.payment_succeeded',
            invoiceId: 'in_test',
            stripeSubscriptionId: null,   // ← missing
            paymentIntentId: 'pi_test',
            amountPaid: 1000,
            currency: 'GBP',
            periodStart: null,
            periodEnd: null,
            failureReason: null,
            failureCode: null,
        );

        $this->handler->handlePaymentSucceeded($event);
    }

    // ── invoice.payment_failed ─────────────────────────────────────────────

    public function test_it_records_failure_and_sets_past_due_status(): void
    {
        $subscription = $this->makeSubscription('sub_abc123');
        $payment = Mockery::mock(Payment::class);
        $event = $this->makeFailedEvent('sub_abc123');

        $this->mockTransactionReturning(['payment' => $payment, 'subscription' => $subscription]);

        $this->paymentRepository
            ->shouldReceive('recordInvoicePaymentFailed')
            ->once()
            ->with(
                $subscription->id,
                'in_failed123',
                'pi_failed123',
                Mockery::type('int'),
                'GBP',
                'Your card was declined.',
                'card_declined',
                $subscription->member_id,
                null,
                null,
            )
            ->andReturn($payment);

        $subscription->shouldReceive('update')
            ->once()
            ->with(['status' => SubscriptionStatus::PAST_DUE->value]);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::type(InvoicePaymentFailed::class));

        $this->handler->handlePaymentFailed($event);
        $this->assertTrue(true);
    }

    public function test_it_wraps_payment_failed_in_a_transaction(): void
    {
        $subscription = $this->makeSubscription('sub_abc123');
        $payment = Mockery::mock(Payment::class);
        $event = $this->makeFailedEvent('sub_abc123');

        $transactionCalled = false;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function (callable $callback) use (&$transactionCalled, $payment, $subscription) {
                $transactionCalled = true;
                return $callback();
            });

        $this->paymentRepository->shouldReceive('recordInvoicePaymentFailed')->andReturn($payment);
        $subscription->shouldReceive('update');
        $this->eventDispatcher->shouldReceive('dispatch');

        $this->handler->handlePaymentFailed($event);

        $this->assertTrue($transactionCalled, 'Expected Database::transaction() to be called');
    }

    public function test_it_emits_invoice_payment_failed_event_with_failure_details(): void
    {
        $subscription = $this->makeSubscription('sub_abc123');
        $payment = Mockery::mock(Payment::class);
        $event = $this->makeFailedEvent('sub_abc123');

        $this->mockTransactionReturning(['payment' => $payment, 'subscription' => $subscription]);
        $this->paymentRepository->shouldReceive('recordInvoicePaymentFailed')->andReturn($payment);
        $subscription->shouldReceive('update');

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(function ($dispatched) use ($subscription, $payment) {
                return $dispatched instanceof InvoicePaymentFailed
                    && $dispatched->subscription === $subscription
                    && $dispatched->payment === $payment
                    && $dispatched->failureReason === 'Your card was declined.'
                    && $dispatched->failureCode === 'card_declined';
            }));

        $this->handler->handlePaymentFailed($event);
        $this->assertTrue(true);
    }

    public function test_it_does_not_update_period_dates_on_payment_failure(): void
    {
        $subscription = $this->makeSubscription('sub_abc123');
        $payment = Mockery::mock(Payment::class);
        $event = $this->makeFailedEvent('sub_abc123');

        $this->mockTransactionReturning(['payment' => $payment, 'subscription' => $subscription]);
        $this->paymentRepository->shouldReceive('recordInvoicePaymentFailed')->andReturn($payment);

        $subscription->shouldReceive('update')
            ->once()
            ->with(Mockery::on(function ($data) {
                // Must only touch status — not period dates, not end_date
                return array_keys($data) === ['status'];
            }));

        $this->eventDispatcher->shouldReceive('dispatch');

        $this->handler->handlePaymentFailed($event);
        $this->assertTrue(true);
    }

    // ── Factories ──────────────────────────────────────────────────────────

    private function makeSubscription(string $stripeSubscriptionId): Subscription&MockInterface
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 99;
        $subscription->member_id = 77;
        $subscription->payment_subscription_id = $stripeSubscriptionId;

        // Wire Subscription::where() to return this mock when matching the stripe ID.
        // In a real test suite you'd use a database or model fake; this keeps the
        // unit test free of infrastructure.
        $this->subscriptionRepository->shouldReceive('findSubscriptionByStripeId')->andReturn($subscription);

        return $subscription;
    }

    private function makeSucceededEvent(string $stripeSubscriptionId): StripeInvoiceEvent
    {
        return new StripeInvoiceEvent(
            type: 'invoice.payment_succeeded',
            invoiceId: 'in_test123',
            stripeSubscriptionId: $stripeSubscriptionId,
            paymentIntentId: 'pi_test123',
            amountPaid: 2900,
            currency: 'GBP',
            periodStart: strtotime('2025-01-01'),
            periodEnd: strtotime('2025-02-01'),
            failureReason: null,
            failureCode: null,
        );
    }

    private function makeFailedEvent(string $stripeSubscriptionId): StripeInvoiceEvent
    {
        return new StripeInvoiceEvent(
            type: 'invoice.payment_failed',
            invoiceId: 'in_failed123',
            stripeSubscriptionId: $stripeSubscriptionId,
            paymentIntentId: 'pi_failed123',
            amountPaid: 0,
            currency: 'GBP',
            periodStart: null,
            periodEnd: null,
            failureReason: 'Your card was declined.',
            failureCode: 'card_declined',
        );
    }

    /**
     * Stubs Database::transaction() to immediately invoke the callback and
     * return the given value — without touching a real database.
     */
    private function mockTransactionReturning(mixed $returnValue): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function (callable $callback) use ($returnValue) {
                $callback();
                return $returnValue;
            });
    }
}
