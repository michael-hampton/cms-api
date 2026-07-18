<?php

declare(strict_types=1);

namespace App\Tests\Unit\Listeners\Subscriptions;

use App\Enums\Subscriptions\PaymentCommunicationEventType;
use App\Events\Subscriptions\InvoicePaymentFailed;
use App\Framework\Support\Logger;
use App\Listeners\Subscriptions\OnInvoicePaymentFailedSendLetter;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Subscriptions\Communications\PaymentCommunicationDispatchService;
use Mockery;
use PHPUnit\Framework\TestCase;

class OnInvoicePaymentFailedSendLetterTest extends TestCase
{
    public function test_dispatches_payment_failed_communication(): void
    {
        $dispatcher = Mockery::mock(PaymentCommunicationDispatchService::class);
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 100;

        $event = new InvoicePaymentFailed(
            subscription: $subscription,
            payment: Mockery::mock(Payment::class)->makePartial(),
            failureReason: 'card_declined',
            failureCode: 'card_declined',
        );

        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->with(
                eventType: PaymentCommunicationEventType::PAYMENT_FAILED,
                subscription: $subscription,
                metadata: ['failure_reason' => 'card_declined', 'failure_code' => 'card_declined'],
            );

        $listener = new OnInvoicePaymentFailedSendLetter($dispatcher, $logger);
        $listener->handle($event);

        $this->assertTrue(true);
    }

    public function test_logs_and_swallows_dispatch_failure(): void
    {
        $dispatcher = Mockery::mock(PaymentCommunicationDispatchService::class);
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 100;

        $event = new InvoicePaymentFailed(
            subscription: $subscription,
            payment: Mockery::mock(Payment::class)->makePartial(),
            failureReason: null,
            failureCode: null,
        );

        $dispatcher->shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('boom'));
        $logger->shouldReceive('error')->once()->with(
            'OnInvoicePaymentFailedSendLetter: dispatch failed',
            Mockery::type('array'),
        );

        $listener = new OnInvoicePaymentFailedSendLetter($dispatcher, $logger);

        // Should not throw.
        $listener->handle($event);

        $this->assertTrue(true);
    }
}
