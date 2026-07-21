<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\DTO\Stripe\StripeInvoiceEvent;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Events\Subscriptions\InvoicePaymentFailed;
use App\Events\Subscriptions\InvoicePaymentSucceeded;
use App\Events\Subscriptions\InvoiceUpcoming;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Repositories\Billing\PaymentRepository;
use App\Repositories\Subscriptions\SubscriptionPlanPricingRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

/**
 * Handles Stripe invoice webhook events for subscription billing.
 *
 * Responsibilities:
 *   - Record the payment (audit log)
 *   - Update billing-related subscription fields only
 *   - Extend print fulfilments on subscription_cycle renewals
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
        private readonly Database               $database,
        private readonly RenewalIssueSchedulingService $renewalIssueSchedulingService,
        private readonly SubscriptionPlanPricingRepository $subscriptionPlanPricingRepository,
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
                hostedInvoiceUrl: $event->hostedInvoiceUrl,
                rawPayload: $event->rawPayload,
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

            $this->extendFulfilmentsForCycleRenewal($subscription, $event);

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
            'billing_reason' => $event->billingReason,
        ]);
    }

    // ── invoice.upcoming ────────────────────────────────────────────────────
    //
    // Notification-only: no payment record, no billing/status writes, so no
    // Database::transaction() needed here. Unlike handlePaymentSucceeded/
    // handlePaymentFailed, a missing subscription is logged and skipped
    // rather than thrown — this event's only job is a downstream letter
    // communication, and a hard failure here would cause Stripe to retry
    // the webhook indefinitely for what may be a legitimately gone
    // subscription (e.g. deleted between preview and webhook delivery).

    public function handleUpcoming(StripeInvoiceEvent $event): void
    {
        if (!$event->stripeSubscriptionId) {
            $this->logger->warning('invoice.upcoming missing stripe subscription ID — skipping', [
                'stripe_invoice' => $event->invoiceId,
            ]);
            return;
        }

        $subscription = $this->subscriptionRepository->findSubscriptionByStripeId($event->stripeSubscriptionId);

        if (!$subscription) {
            $this->logger->info('invoice.upcoming: no matching subscription — skipping', [
                'stripe_subscription' => $event->stripeSubscriptionId,
            ]);
            return;
        }

        $this->eventDispatcher->dispatch(
            new InvoiceUpcoming(
                subscription: $subscription,
                amountDue: $event->amountPaid,
                currency: $event->currency,
            )
        );

        $this->logger->info('invoice.upcoming processed', [
            'subscription_id' => $subscription->id,
            'stripe_subscription' => $event->stripeSubscriptionId,
            'amount_due' => $event->amountPaid,
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
                hostedInvoiceUrl: $event->hostedInvoiceUrl,
                rawPayload: $event->rawPayload,
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

        $this->logger->info('invoice.payment_failed processed', [
            'subscription_id' => $subscription->id,
            'stripe_subscription' => $event->stripeSubscriptionId,
            'stripe_invoice' => $event->invoiceId,
            'failure_code' => $event->failureCode,
        ]);
    }

    private function extendFulfilmentsForCycleRenewal(
        Subscription $subscription,
        StripeInvoiceEvent $event,
    ): void {
        if (!$event->isSubscriptionCycle()) {
            return;
        }

        if (!$subscription->isPrint()) {
            return;
        }

        $periodStart = $event->currentPeriodStart();
        if (!$periodStart) {
            $this->logger->warning('subscription_cycle renewal missing period start — skipping fulfilment extension', [
                'subscription_id' => $subscription->id,
                'stripe_invoice' => $event->invoiceId,
            ]);
            return;
        }

        $issueCount = $this->resolveIssueCount($subscription);
        if ($issueCount === null) {
            $this->logger->warning('subscription_cycle renewal missing issue_count — skipping fulfilment extension', [
                'subscription_id' => $subscription->id,
                'subscription_plan_pricing_id' => $subscription->subscription_plan_pricing_id ?? null,
                'stripe_invoice' => $event->invoiceId,
            ]);
            return;
        }

        $summary = $this->renewalIssueSchedulingService->extendForInPlaceRenewal(
            $subscription,
            $periodStart,
            $issueCount,
        );

        $this->logger->info('subscription_cycle fulfilment extension applied', [
            'subscription_id' => $subscription->id,
            'stripe_invoice' => $event->invoiceId,
            'issue_count' => $issueCount,
            'created' => $summary['created'],
            'existing' => $summary['existing'],
            'skipped' => $summary['skipped'],
        ]);
    }

    private function resolveIssueCount(Subscription $subscription): ?int
    {
        $pricingId = $subscription->subscription_plan_pricing_id ?? null;
        if (!$pricingId) {
            return null;
        }

        $pricing = $this->subscriptionPlanPricingRepository->find((int) $pricingId);
        if (!$pricing || !isset($pricing->issue_count) || !is_numeric($pricing->issue_count)) {
            return null;
        }

        $issueCount = (int) $pricing->issue_count;

        return $issueCount > 0 ? $issueCount : null;
    }
}
