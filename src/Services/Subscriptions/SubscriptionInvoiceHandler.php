<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\DTO\Stripe\StripeInvoiceEvent;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Events\Subscriptions\InvoicePaymentFailed;
use App\Events\Subscriptions\InvoicePaymentSucceeded;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Repositories\Billing\PaymentRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

/**
 * Handles Stripe invoice webhook events for subscription billing.
 *
 * Responsibilities:
 *   - Record the payment (audit log)
 *   - Update billing-related subscription fields only
 *   - Emit domain events for access management (handled by listeners)
 *
 * Explicitly NOT responsible for:
 *   - Granting/revoking access (listeners)
 *   - Sending notifications (listeners)
 *   - Any Stripe API calls
 */
class SubscriptionInvoiceHandler
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly PaymentRepository      $paymentRepository,
        private readonly EventDispatcher        $eventDispatcher,
        private readonly Logger                 $logger,
        private readonly Database               $database
    )
    {
    }

    // ── invoice.payment_succeeded ──────────────────────────────────────────

    public function handlePaymentSucceeded(StripeInvoiceEvent $event): void
    {
        $subscription = $this->findSubscriptionOrAbort($event->stripeSubscriptionId);

        $result = $this->database->transaction(function () use ($event, $subscription) {
            $payment = $this->paymentRepository->recordInvoicePaymentSucceeded(
                subscriptionId: $subscription->id,
                stripeInvoiceId: $event->invoiceId,
                stripePaymentIntentId: $event->paymentIntentId,
                amountCents: $event->amountPaid,
                currency: $event->currency,
                paidAt: $event->paidAt(),
                memberId: $subscription->member_id,
            );

            // Only update billing-related fields — never blindly overwrite.
            $billingUpdate = [
                'status' => SubscriptionStatus::ACTIVE->value,
                'last_payment_date' => $event->paidAt()->format('Y-m-d H:i:s'),
            ];

            if ($event->currentPeriodEnd()) {
                $billingUpdate['current_period_end'] = $event->currentPeriodEnd()->format('Y-m-d H:i:s');
                $billingUpdate['next_billing_date'] = $event->currentPeriodEnd()->format('Y-m-d H:i:s');
                // end_date tracks the subscription's access boundary
                $billingUpdate['end_date'] = $event->currentPeriodEnd()->format('Y-m-d H:i:s');
            }

            if ($event->currentPeriodStart()) {
                $billingUpdate['current_period_start'] = $event->currentPeriodStart()->format('Y-m-d H:i:s');
            }

            $subscription->update($billingUpdate);

            return ['payment' => $payment, 'subscription' => $subscription];
        });

        $this->eventDispatcher->dispatch(
            new InvoicePaymentSucceeded(
                subscription: $result['subscription'],
                payment: $result['payment'],
            )
        );

        $this->logger->info('invoice.payment_succeeded processed', [
            'subscription_id' => $subscription->id,
            'stripe_subscription' => $event->stripeSubscriptionId,
            'stripe_invoice' => $event->invoiceId,
            'amount_cents' => $event->amountPaid,
            'currency' => $event->currency,
        ]);
    }

    // ── invoice.payment_failed ─────────────────────────────────────────────

    private function findSubscriptionOrAbort(?string $stripeSubscriptionId): Subscription
    {
        if (!$stripeSubscriptionId) {
            throw new \RuntimeException('Invoice event missing stripe subscription ID — cannot reconcile.');
        }

        $subscription = $this->subscriptionRepository->findSubscriptionByStripeId($stripeSubscriptionId);

        if (!$subscription) {
            throw new \RuntimeException(
                "No subscription found for Stripe ID: {$stripeSubscriptionId}"
            );
        }

        return $subscription;
    }

    // ── Private helpers ────────────────────────────────────────────────────

    public function handlePaymentFailed(StripeInvoiceEvent $event): void
    {
        $subscription = $this->findSubscriptionOrAbort($event->stripeSubscriptionId);

        $result = $this->database->transaction(function () use ($event, $subscription) {
            $payment = $this->paymentRepository->recordInvoicePaymentFailed(
                subscriptionId: $subscription->id,
                stripeInvoiceId: $event->invoiceId,
                stripePaymentIntentId: $event->paymentIntentId,
                amountCents: $event->amountPaid,
                currency: $event->currency,
                failureReason: $event->failureReason,
                failureCode: $event->failureCode,
                memberId: $subscription->member_id,
            );

            // PAST_DUE, not cancelled — Stripe retries automatically.
            // Do not touch period dates or access boundaries here.
            $subscription->update([
                'status' => SubscriptionStatus::PAST_DUE->value,
            ]);

            return ['payment' => $payment, 'subscription' => $subscription];
        });

        $this->eventDispatcher->dispatch(
            new InvoicePaymentFailed(
                subscription: $result['subscription'],
                payment: $result['payment'],
                failureReason: $event->failureReason,
                failureCode: $event->failureCode,
            )
        );

        $this->logger->warning('invoice.payment_failed processed', [
            'subscription_id' => $subscription->id,
            'stripe_subscription' => $event->stripeSubscriptionId,
            'stripe_invoice' => $event->invoiceId,
            'failure_reason' => $event->failureReason,
            'failure_code' => $event->failureCode,
        ]);
    }
}
