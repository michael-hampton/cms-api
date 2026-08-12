<?php

declare(strict_types=1);

namespace App\Tests\Unit\Listeners\Subscriptions;

use App\Enums\Subscriptions\PaymentCommunicationEventType;
use App\Events\Subscriptions\InvoiceUpcoming;
use App\Framework\Support\Logger;
use App\Listeners\Subscriptions\OnInvoiceUpcomingSendPaymentCommunication;
use App\Models\Subscription;
use App\Services\Subscriptions\Communications\PaymentCommunicationDispatchService;
use Mockery;
use PHPUnit\Framework\TestCase;

class OnInvoiceUpcomingSendPaymentCommunicationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_dispatches_renewal_intent_to_debit_communication(): void
    {
        $dispatcher = Mockery::mock(PaymentCommunicationDispatchService::class);
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 100;

        $event = new InvoiceUpcoming(
            subscription: $subscription,
            amountDue: 999,
            currency: 'GBP',
            invoiceId: 'in_upcoming_1',
        );

        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->with(
                PaymentCommunicationEventType::RENEWAL_INTENT_TO_DEBIT,
                $subscription,
                [
                    'amount_due' => 999,
                    'currency' => 'GBP',
                    'invoice_id' => 'in_upcoming_1',
                ],
            );

        $listener = new OnInvoiceUpcomingSendPaymentCommunication($dispatcher, $logger);
        $listener->handle($event);

        $this->assertTrue(true);
    }

    public function test_logs_and_swallows_dispatch_failure(): void
    {
        $dispatcher = Mockery::mock(PaymentCommunicationDispatchService::class);
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 100;

        $event = new InvoiceUpcoming(subscription: $subscription, amountDue: 0, currency: 'GBP');

        $dispatcher->shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('boom'));
        $logger->shouldReceive('error')->once();

        $listener = new OnInvoiceUpcomingSendPaymentCommunication($dispatcher, $logger);
        $listener->handle($event);

        $this->assertTrue(true);
    }
}
