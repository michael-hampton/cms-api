<?php

declare(strict_types=1);

namespace App\Actions\Stripe;

use App\Services\Billing\Stripe\StripeEventParser;
use App\Services\Subscriptions\SubscriptionInvoiceHandler;
use Stripe\Event;

/**
 * Handles invoice.payment_failed.
 *
 * Thin adapter between the Stripe webhook transport layer and the
 * contract-compliant billing logic. All persistence, transaction
 * boundaries, and side-effect events live in SubscriptionInvoiceHandler
 * (App\Services\Subscriptions) — this class only translates the raw
 * Stripe event into a typed DTO and delegates.
 */
class HandleInvoiceFailed
{
    public function __construct(
        private readonly StripeEventParser        $eventParser,
        private readonly SubscriptionInvoiceHandler $invoiceHandler,
    )
    {
    }

    public function handle(Event $event): void
    {
        /** @var \Stripe\Invoice $invoice */
        $invoice = $event->data->object;

        $this->invoiceHandler->handlePaymentFailed(
            $this->eventParser->parseInvoice($event->type, $invoice)
        );
    }
}
