<?php

namespace App\Actions\Stripe;

use App\Enums\PaymentStatus;
use App\Framework\Support\Logger;
use App\Models\Payment;
use App\Models\Subscription;
use Stripe\Event;

/**
 * Handles invoice.paid
 *
 * 1. Creates or updates a Payment record for this invoice.
 * 2. Ensures the linked subscription is in "active" status and has an
 *    updated current_period_end (renewal window extended).
 *
 * Uses updateOrCreate keyed on stripe_invoice_id so re-delivery is safe.
 */
class HandleInvoicePaid
{
    public function handle(Event $event): void
    {
        /** @var \Stripe\Invoice $invoice */
        $invoice = $event->data->object;

        // ── 1. Resolve linked subscription ──────────────────────────────────
        $subscription = null;

        if ($invoice->subscription) {
            $subscription = Subscription::where('payment_subscription_id', $invoice->subscription)->first();
        }

        // ── 2. Upsert Payment record ─────────────────────────────────────────
        $paymentIntentId = is_string($invoice->payment_intent)
            ? $invoice->payment_intent
            : ($invoice->payment_intent->id ?? null);

        $amountPaid = $invoice->amount_paid ?? 0; // in cents

        Payment::updateOrCreate(
            ['stripe_invoice_id' => $invoice->id],
            [
                'subscription_id'    => $subscription?->id,
                'member_id'          => $subscription?->member_id ?? null, // nullable; set if resolvable
                'site_id'            => $subscription?->site_id ?? null,
                'payment_provider'   => 'stripe',
                'payment_method'     => 'stripe',
                'transaction_id'     => $invoice->id,
                'payment_intent_id'  => $paymentIntentId,
                'stripe_invoice_id'  => $invoice->id,
                'amount'             => $amountPaid / 100,
                'currency'           => strtoupper($invoice->currency),
                'status'             => PaymentStatus::COMPLETED->value,
                'hosted_invoice_url' => $invoice->hosted_invoice_url ?? null,
                'raw_payload'        => json_encode($invoice->toArray()),
                'paid_at'            => $invoice->status_transitions->paid_at
                    ? date('Y-m-d H:i:s', $invoice->status_transitions->paid_at)
                    : date('Y-m-d H:i:s'),
            ]
        );

        // ── 3. Extend subscription access ───────────────────────────────────
        if ($subscription === null) {
            Logger::info('HandleInvoicePaid: no local subscription linked', [
                'stripe_invoice_id'      => $invoice->id,
                'stripe_subscription_id' => $invoice->subscription,
            ]);
            return;
        }

        $periodEnd = null;

        if (!empty($invoice->lines->data)) {
            foreach ($invoice->lines->data as $line) {
                if ($line->period?->end) {
                    $periodEnd = date('Y-m-d H:i:s', $line->period->end);
                    break;
                }
            }
        }

        $subscription->status             = 'active';
        $subscription->last_payment_date  = date('Y-m-d H:i:s');

        if ($periodEnd) {
            $subscription->current_period_end  = $periodEnd;
            $subscription->next_billing_date   = $periodEnd;
            $subscription->end_date            = $periodEnd;
        }

        $subscription->save();
    }
}