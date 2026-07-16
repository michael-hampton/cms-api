<?php

declare(strict_types=1);

namespace App\Tests\Unit\Actions\Stripe;

use App\Actions\Stripe\HandleInvoicePaid;
use App\DTO\Stripe\StripeInvoiceEvent;
use App\Services\Billing\Stripe\StripeEventParser;
use App\Services\Subscriptions\SubscriptionInvoiceHandler;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class HandleInvoicePaidTest extends FunctionalTestCase
{
    private StripeEventParser&MockInterface $eventParser;
    private SubscriptionInvoiceHandler&MockInterface $invoiceHandler;
    private HandleInvoicePaid $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventParser = Mockery::mock(StripeEventParser::class);
        $this->invoiceHandler = Mockery::mock(SubscriptionInvoiceHandler::class);

        $this->action = new HandleInvoicePaid(
            eventParser: $this->eventParser,
            invoiceHandler: $this->invoiceHandler,
        );
    }

    private function makeInvoiceEvent(array $overrides = []): \Stripe\Event
    {
        $invoiceData = array_merge([
            'id'                 => 'in_test123',
            'object'             => 'invoice',
            'subscription'       => 'sub_test123',
            'payment_intent'     => 'pi_test123',
            'amount_paid'        => 1999,
            'amount_due'         => 1999,
            'currency'           => 'gbp',
            'hosted_invoice_url' => 'https://invoice.stripe.com/inv_test',
            'status'             => 'paid',
            'status_transitions' => ['paid_at' => time()],
            'lines'              => [
                'data' => [
                    ['period' => ['end' => strtotime('+1 month')]],
                ],
            ],
        ], $overrides);

        return \Stripe\Event::constructFrom([
            'id'          => 'evt_test',
            'type'        => 'invoice.paid',
            'data'        => ['object' => $invoiceData],
            'api_version' => '2023-10-16',
        ]);
    }

    public function test_it_parses_the_event_and_delegates_to_the_invoice_handler(): void
    {
        $event = $this->makeInvoiceEvent();
        $dto = new StripeInvoiceEvent(
            type: 'invoice.paid',
            invoiceId: 'in_test123',
            stripeSubscriptionId: 'sub_test123',
            paymentIntentId: 'pi_test123',
            amountPaid: 1999,
            currency: 'GBP',
            periodStart: null,
            periodEnd: strtotime('+1 month'),
            failureReason: null,
            failureCode: null,
        );

        $this->eventParser
            ->shouldReceive('parseInvoice')
            ->once()
            ->with('invoice.paid', Mockery::type(\Stripe\Invoice::class))
            ->andReturn($dto);

        $this->invoiceHandler
            ->shouldReceive('handlePaymentSucceeded')
            ->once()
            ->with($dto);

        $this->action->handle($event);

        // No assertion-free tests: the mock expectations above are the
        // behavioural assertions (parser called, handler invoked with the
        // parsed DTO).
        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
