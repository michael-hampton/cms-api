<?php

declare(strict_types=1);

namespace App\Tests\Unit\Actions\Stripe;

use App\Actions\Stripe\HandleInvoiceFailed;
use App\DTO\Stripe\StripeInvoiceEvent;
use App\Services\Billing\Stripe\StripeEventParser;
use App\Services\Subscriptions\SubscriptionInvoiceHandler;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class HandleInvoiceFailedTest extends FunctionalTestCase
{
    private StripeEventParser&MockInterface $eventParser;
    private SubscriptionInvoiceHandler&MockInterface $invoiceHandler;
    private HandleInvoiceFailed $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventParser = Mockery::mock(StripeEventParser::class);
        $this->invoiceHandler = Mockery::mock(SubscriptionInvoiceHandler::class);

        $this->action = new HandleInvoiceFailed(
            eventParser: $this->eventParser,
            invoiceHandler: $this->invoiceHandler,
        );
    }

    private function makeFailedInvoiceEvent(array $overrides = []): \Stripe\Event
    {
        $invoiceData = array_merge([
            'id'                      => 'in_fail123',
            'object'                  => 'invoice',
            'subscription'            => 'sub_fail123',
            'payment_intent'          => 'pi_fail123',
            'amount_due'              => 2999,
            'currency'                => 'gbp',
            'hosted_invoice_url'      => 'https://invoice.stripe.com/inv_fail',
            'status'                  => 'open',
            'last_finalization_error' => ['message' => 'Your card has insufficient funds.'],
        ], $overrides);

        return \Stripe\Event::constructFrom([
            'id'          => 'evt_fail_test',
            'type'        => 'invoice.payment_failed',
            'data'        => ['object' => $invoiceData],
            'api_version' => '2023-10-16',
        ]);
    }

    public function test_it_parses_the_event_and_delegates_to_the_invoice_handler(): void
    {
        $event = $this->makeFailedInvoiceEvent();
        $dto = new StripeInvoiceEvent(
            type: 'invoice.payment_failed',
            invoiceId: 'in_fail123',
            stripeSubscriptionId: 'sub_fail123',
            paymentIntentId: 'pi_fail123',
            amountPaid: 0,
            currency: 'GBP',
            periodStart: null,
            periodEnd: null,
            failureReason: 'Your card has insufficient funds.',
            failureCode: null,
        );

        $this->eventParser
            ->shouldReceive('parseInvoice')
            ->once()
            ->with('invoice.payment_failed', Mockery::type(\Stripe\Invoice::class))
            ->andReturn($dto);

        $this->invoiceHandler
            ->shouldReceive('handlePaymentFailed')
            ->once()
            ->with($dto);

        $this->action->handle($event);

        $this->assertTrue(true);
    }
}
