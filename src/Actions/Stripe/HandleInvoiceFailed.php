<?php

namespace App\Actions\Stripe;

use App\Enums\PaymentStatus;
use App\Framework\Support\Logger;
use App\Models\Payment;
use App\Models\Subscription;
use Stripe\Event;

/**
 * Handles invoice.payment_failed
 *
 * 1. Creates or updates a Payment record with failed status.
 * 2. Marks the linked subscription as past_due.
 */
class HandleInvoiceFailed
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

        $errorMessage = $invoice->last_finalization_error?->message
            ?? 'Payment failed';

        Payment::updateOrCreate(
            ['stripe_invoice_id' => $invoice->id],
            [
                'subscription_id'   => $subscription?->id,
                'member_id'         => $subscription?->member_id ?? null,
                'site_id'           => $subscription?->site_id ?? null,
                'payment_provider'  => 'stripe',
                'payment_method'    => 'stripe',
                'transaction_id'    => $invoice->id,
                'payment_intent_id' => $paymentIntentId,
                'stripe_invoice_id' => $invoice->id,
                'amount'            => ($invoice->amount_due ?? 0) / 100,
                'currency'          => strtoupper($invoice->currency),
                'status'            => PaymentStatus::FAILED->value,
                'error_message'     => $errorMessage,
                'hosted_invoice_url'=> $invoice->hosted_invoice_url ?? null,
                'raw_payload'       => json_encode($invoice->toArray()),
                'failed_at'         => date('Y-m-d H:i:s'),
            ]
        );

        // ── 3. Update subscription to past_due ───────────────────────────────
        if ($subscription === null) {
            Logger::info('HandleInvoiceFailed: no local subscription linked', [
                'stripe_invoice_id'      => $invoice->id,
                'payment_subscription_id' => $invoice->subscription,
            ]);
            return;
        }

        $subscription->status = 'past_due';
        $subscription->save();
    }
}