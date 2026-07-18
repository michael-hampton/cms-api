<?php

declare(strict_types=1);

namespace App\Actions\Stripe;

use App\Services\Billing\Stripe\StripeEventParser;
use App\Services\Subscriptions\SubscriptionInvoiceHandler;
use Stripe\Event;

/**
 * Handles invoice.upcoming.
 *
 * Thin adapter between the Stripe webhook transport layer and
 * SubscriptionInvoiceHandler — this class only translates the raw Stripe
 * event into a typed DTO and delegates.
 */
class HandleInvoiceUpcoming
{
    public function __construct(
        private readonly StripeEventParser         $eventParser,
        private readonly SubscriptionInvoiceHandler $invoiceHandler,
    ) {
    }

    public function handle(Event $event): void
    {
        /** @var \Stripe\Invoice $invoice */
        $invoice = $event->data->object;

        $this->invoiceHandler->handleUpcoming(
            $this->eventParser->parseInvoice($event->type, $invoice)
        );
    }
}
