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
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class SubscriptionInvoiceHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private SubscriptionInvoiceHandler $handler;
    private $subscriptionRepository;
    private $paymentRepository;
    private $eventDispatcher;
    private $logger;
    private $database;

    public function testHandlePaymentSucceeded(): void
    {
        $event = new StripeInvoiceEvent(
            type: 'invoice.payment_succeeded',
            invoiceId: 'in_123',
            stripeSubscriptionId: 'sub_123',
            paymentIntentId: 'pi_123',
            amountPaid: 1000,
            currency: 'usd',
            periodStart: 1600000000,
            periodEnd: 1600003600,
            failureReason: null,
            failureCode: null
        );

        $subscription = Mockery::mock(Subscription::class);
        $subscription->shouldReceive('setAttribute')->andReturnNull();
        $subscription->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $subscription->shouldReceive('getAttribute')->andReturnNull();
        $subscription->shouldReceive('relationLoaded')->andReturn(false);
        $subscription->id = 1;

        $this->subscriptionRepository->shouldReceive('findSubscriptionByStripeId')
            ->once()
            ->with('sub_123')
            ->andReturn($subscription);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $payment = Mockery::mock(Payment::class);
        $payment->shouldReceive('setAttribute')->andReturnNull();
        $payment->shouldReceive('getAttribute')->andReturnNull();

        $this->paymentRepository->shouldReceive('recordInvoicePaymentSucceeded')
            ->once()
            ->andReturn($payment);

        $subscription->shouldReceive('update')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return $arg['status'] === SubscriptionStatus::ACTIVE->value;
            }));

        $this->eventDispatcher->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::type(InvoicePaymentSucceeded::class));

        $this->logger->shouldReceive('info')->once();

        $this->handler->handlePaymentSucceeded($event);
    }

    public function testHandlePaymentFailed(): void
    {
        $event = new StripeInvoiceEvent(
            type: 'invoice.payment_failed',
            invoiceId: 'in_123',
            stripeSubscriptionId: 'sub_123',
            paymentIntentId: 'pi_123',
            amountPaid: 1000,
            currency: 'usd',
            periodStart: 1600000000,
            periodEnd: 1600003600,
            failureReason: 'card_declined',
            failureCode: 'declined'
        );

        $subscription = Mockery::mock(Subscription::class);
        $subscription->shouldReceive('setAttribute')->andReturnNull();
        $subscription->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $subscription->shouldReceive('getAttribute')->andReturnNull();
        $subscription->shouldReceive('relationLoaded')->andReturn(false);
        $subscription->id = 1;

        $this->subscriptionRepository->shouldReceive('findSubscriptionByStripeId')
            ->once()
            ->with('sub_123')
            ->andReturn($subscription);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $payment = Mockery::mock(Payment::class);
        $payment->shouldReceive('setAttribute')->andReturnNull();
        $payment->shouldReceive('getAttribute')->andReturnNull();

        $this->paymentRepository->shouldReceive('recordInvoicePaymentFailed')
            ->once()
            ->andReturn($payment);

        $subscription->shouldReceive('update')
            ->once()
            ->with(['status' => SubscriptionStatus::PAST_DUE->value]);

        $this->eventDispatcher->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::type(InvoicePaymentFailed::class));

        $this->logger->shouldReceive('warning')->once();

        $this->handler->handlePaymentFailed($event);
    }

    public function testFindSubscriptionOrAbortThrowsExceptionWhenIdMissing(): void
    {
        $event = new StripeInvoiceEvent(
            type: 'invoice.payment_succeeded',
            invoiceId: 'in_123',
            stripeSubscriptionId: null,
            paymentIntentId: 'pi_123',
            amountPaid: 1000,
            currency: 'usd',
            periodStart: null,
            periodEnd: null,
            failureReason: null,
            failureCode: null
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invoice event missing stripe subscription ID — cannot reconcile.');

        $this->handler->handlePaymentSucceeded($event);
    }

    public function testFindSubscriptionOrAbortThrowsExceptionWhenNotFound(): void
    {
        $event = new StripeInvoiceEvent(
            type: 'invoice.payment_succeeded',
            invoiceId: 'in_123',
            stripeSubscriptionId: 'sub_not_found',
            paymentIntentId: 'pi_123',
            amountPaid: 1000,
            currency: 'usd',
            periodStart: null,
            periodEnd: null,
            failureReason: null,
            failureCode: null
        );

        $this->subscriptionRepository->shouldReceive('findSubscriptionByStripeId')
            ->once()
            ->with('sub_not_found')
            ->andReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No subscription found for Stripe ID: sub_not_found');

        $this->handler->handlePaymentSucceeded($event);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->paymentRepository = Mockery::mock(PaymentRepository::class);
        $this->eventDispatcher = Mockery::mock(EventDispatcher::class);
        $this->logger = Mockery::mock(Logger::class);
        $this->database = Mockery::mock(Database::class);

        $this->handler = new SubscriptionInvoiceHandler(
            $this->subscriptionRepository,
            $this->paymentRepository,
            $this->eventDispatcher,
            $this->logger,
            $this->database
        );
    }
}
