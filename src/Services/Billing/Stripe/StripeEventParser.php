<?php

declare(strict_types=1);

namespace App\Services\Billing\Stripe;


use App\DTO\Stripe\StripeInvoiceEvent;
use App\DTO\Stripe\StripeSubscriptionDeletedEvent;

/**
 * Translates raw Stripe objects (from webhook payloads) into typed DTOs.
 *
 * Centralising this traversal means the handlers never touch raw Stripe
 * objects, and the parser is the single place to update when Stripe changes
 * its payload shape.
 */
class StripeEventParser
{
    /**
     * @param \Stripe\Invoice $invoice The `data.object` from an invoice.* event.
     */
    public function parseInvoice(string $eventType, \Stripe\Invoice $invoice): StripeInvoiceEvent
    {
        $paymentIntentId = null;
        $paymentIntent = $invoice->payment_intent ?? null;

        if (is_string($paymentIntent)) {
            $paymentIntentId = $paymentIntent;
        } elseif (is_object($paymentIntent) && isset($paymentIntent->id)) {
            $paymentIntentId = $paymentIntent->id;
        }

        $failureReason = null;
        $failureCode = null;

        // last_payment_error is present on invoices when the charge failed
        if (isset($invoice->last_payment_error) && $invoice->last_payment_error) {
            $failureReason = $invoice->last_payment_error->message ?? null;
            $failureCode = $invoice->last_payment_error->code ?? null;
        }

        $subscriptionId = null;
        if (isset($invoice->subscription)) {
            $subscriptionId = is_string($invoice->subscription)
                ? $invoice->subscription
                : ($invoice->subscription->id ?? null);
        }

        // Prefer lines[0] period for the billing window; fall back to invoice-level fields
        $periodStart = $invoice->lines->data[0]->period->start ?? $invoice->period_start ?? null;
        $periodEnd = $invoice->lines->data[0]->period->end ?? $invoice->period_end ?? null;

        return new StripeInvoiceEvent(
            type: $eventType,
            invoiceId: $invoice->id ?? '',
            stripeSubscriptionId: $subscriptionId,
            paymentIntentId: $paymentIntentId,
            // invoice.upcoming previews have no amount_paid (nothing has
            // been charged yet) — amount_due is the relevant figure there.
            amountPaid: (int)($invoice->amount_paid ?? $invoice->amount_due ?? 0),
            currency: strtoupper($invoice->currency ?? 'usd'),
            periodStart: $periodStart ? (int)$periodStart : null,
            periodEnd: $periodEnd ? (int)$periodEnd : null,
            failureReason: $failureReason,
            failureCode: $failureCode,
            hostedInvoiceUrl: $invoice->hosted_invoice_url ?? null,
            rawPayload: json_encode($invoice->toArray()) ?: null,
        );
    }

    /**
     * @param \Stripe\Subscription $subscription The `data.object` from a
     *                                             customer.subscription.deleted event.
     */
    public function parseSubscriptionDeleted(\Stripe\Subscription $subscription): StripeSubscriptionDeletedEvent
    {
        return new StripeSubscriptionDeletedEvent(
            stripeSubscriptionId: $subscription->id,
            stripeStatus: $subscription->status,
            canceledAt: $subscription->canceled_at ? (int)$subscription->canceled_at : null,
            currentPeriodEnd: $subscription->current_period_end ? (int)$subscription->current_period_end : null,
        );
    }
}