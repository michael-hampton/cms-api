<?php

namespace App\Services\Subscriptions;

use App\Enums\Orders\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Events\Subscriptions\TrialConversionFailedEvent;
use App\Events\Subscriptions\TrialConvertedEvent;
use App\Framework\Database\Database;
use App\Models\Subscription;
use App\Repositories\Billing\OrderRepository;
use App\Services\Billing\Order\OrderManager;
use App\Services\Billing\Stripe\StripeOffSessionCharger;
use App\Services\Subscriptions\Calculators\SubscriptionDateCalculator;
use App\Services\Subscriptions\Validators\OneTimePlanValidator;
use Psr\Log\LoggerInterface;

/**
 * Converts TRIALING subscriptions to ACTIVE by charging the customer's saved
 * payment method once the trial period ends.
 *
 * Designed to be called by a scheduled job (e.g. every hour).  Each
 * subscription is processed inside its own transaction so a single failure
 * does not block the rest of the batch.
 *
 * Payment method resolution:
 *   During initial checkout, PaymentIntentService calls
 *   createPaymentIntentWithCustomer() with setup_future_usage = 'off_session',
 *   which saves the card to the Stripe customer.  The Stripe customer ID is
 *   stored on the Order as stripe_customer_id.  The conversion job reads that
 *   field and charges the customer's default payment method via
 *   StripePaymentProcessor::chargeOffSession().
 *
 * Failure path:
 *   Payment declined → status set to EXPIRED, TrialConversionFailedEvent fired.
 *   Infrastructure exception → logged, subscription skipped, job continues.
 *   Payment succeeded but DB activation failed → logged at CRITICAL level,
 *   exception rethrown (money was taken; requires manual intervention).
 */
class TrialConversionService
{
    public function __construct(
        private readonly StripeOffSessionCharger    $offSessionCharger,
        private readonly Database                   $database,
        private readonly OrderManager               $orderManager,
        private readonly SubscriptionDateCalculator $dateCalculator,
        private readonly OneTimePlanValidator       $planValidator,
        private readonly LoggerInterface            $logger,
        private readonly OrderRepository            $orderRepository
    )
    {
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Process all subscriptions whose trial has expired and are still TRIALING.
     * Returns a summary array for logging / monitoring.
     */
    public function convertExpiredTrials(): array
    {
        $candidates = Subscription::scopeReadyForTrialConversion(
            Subscription::query()
        )->get();

        $results = [
            'processed' => 0,
            'converted' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        foreach ($candidates as $subscription) {
            $results['processed']++;

            try {
                $converted = $this->convertSingle($subscription);
                $converted ? $results['converted']++ : $results['failed']++;
            } catch (\Throwable $e) {
                $results['skipped']++;
                $this->logger->error('TrialConversionService: unhandled exception', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Convert a single subscription.
     *
     * Returns true on success, false on soft failure (declined payment — the
     * subscription is expired).  Throws only for infrastructure failures so
     * the caller can decide whether to retry.
     */
    public function convertSingle(Subscription $subscription): bool
    {
        // ── Guards ────────────────────────────────────────────────────────────

        if ($subscription->status !== SubscriptionStatus::TRIALING->value) {
            $this->logger->warning('TrialConversionService: subscription is not TRIALING', [
                'subscription_id' => $subscription->id,
                'status' => $subscription->status,
            ]);
            return false;
        }

        // isTrialing() returns true while the trial is still running
        if ($subscription->isTrialing()) {
            $this->logger->info('TrialConversionService: trial has not yet ended, skipping', [
                'subscription_id' => $subscription->id,
                'trial_ends_at' => $subscription->trial_ends_at,
            ]);
            return false;
        }

        $plan = $subscription->plan;

        if (!$plan) {
            $this->logger->error('TrialConversionService: plan not found', [
                'subscription_id' => $subscription->id,
                'plan_id' => $subscription->plan_id,
            ]);
            $this->expireSubscription($subscription);
            return false;
        }

        // ── Resolve the Stripe customer ID from the subscription's order ──────
        //
        // PaymentIntentService::createForOrder() calls
        // createPaymentIntentWithCustomer(), which stores the Stripe customer
        // on the Order as stripe_customer_id.  We find the most recent order
        // for this subscription and read that field directly.

        $latestOrder = $this->orderRepository->findLatestForSubscription($subscription);

        if (!$latestOrder) {
            $this->logger->error('TrialConversionService: no order found for subscription', [
                'subscription_id' => $subscription->id,
            ]);
            $this->expireSubscription($subscription);
            return false;
        }

        $stripeCustomerId = $latestOrder->stripe_customer_id ?? null;

        if (!$stripeCustomerId) {
            $this->logger->error('TrialConversionService: no stripe_customer_id on order', [
                'subscription_id' => $subscription->id,
                'order_id' => $latestOrder->id,
            ]);
            $this->expireSubscription($subscription);
            return false;
        }

        // ── Charge the saved card off-session ─────────────────────────────────

        $amountCents = (int)round($subscription->price * 100);
        $currency = $subscription->currency ?? 'gbp';

        $paymentResult = $this->offSessionCharger->charge(
            stripeCustomerId: $stripeCustomerId,
            amountCents: $amountCents,
            currency: $currency,
            metadata: [
                'subscription_id' => $subscription->id,
                'plan_id' => $subscription->plan_id,
                'member_id' => $subscription->member_id,
                'conversion_type' => 'trial_to_paid',
                'order_id' => $latestOrder->id,
            ]
        );

        if (!($paymentResult['success'] ?? false)) {
            $reason = $paymentResult['message'] ?? 'Payment declined';

            $this->logger->warning('TrialConversionService: payment declined', [
                'subscription_id' => $subscription->id,
                'reason' => $reason,
            ]);

            $this->expireSubscription($subscription);

            // Non-critical side effect — catch and log, do not rethrow
            try {
                event(new TrialConversionFailedEvent($subscription, $reason));
            } catch (\Throwable $e) {
                $this->logger->error('TrialConversionService: failed to fire TrialConversionFailedEvent', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return false;
        }

        // ── Activate the subscription ─────────────────────────────────────────
        //
        // Payment has succeeded at this point.  Any exception from here must be
        // logged at CRITICAL and rethrown — money was taken but the subscription
        // was not activated, which requires manual intervention.

        try {
            $billingPeriod = $this->planValidator->validateBillingPeriod($plan->billing_period);
            $newEndDate = $this->dateCalculator->calculateEndDate(
                new \DateTimeImmutable(),
                $billingPeriod
            );

            $this->database->transaction(function () use (
                $subscription,
                $newEndDate,
                $paymentResult,
                $latestOrder
            ) {
                $subscription->update([
                    'status' => SubscriptionStatus::ACTIVE->value,
                    'end_date' => $newEndDate->format('Y-m-d H:i:s'),
                    'last_payment_date' => now_datetime()->format('Y-m-d H:i:s'),
                    'next_billing_date' => $newEndDate->format('Y-m-d H:i:s'),
                    'payment_intent_id' => $paymentResult['payment_intent_id'] ?? null,
                ]);

                // Signature: updateOrderStatus(int $orderId, string $orderStatus, string $paymentStatus)
                $this->orderManager->updateOrderStatus(
                    $latestOrder->id,
                    OrderStatus::COMPLETED->value,
                    PaymentStatus::PAID->value
                );
            });

        } catch (\Throwable $e) {
            $this->logger->critical(
                'TrialConversionService: payment succeeded but activation failed — MANUAL INTERVENTION REQUIRED',
                [
                    'subscription_id' => $subscription->id,
                    'payment_intent_id' => $paymentResult['payment_intent_id'] ?? null,
                    'order_id' => $latestOrder->id,
                    'error' => $e->getMessage(),
                ]
            );
            throw $e;
        }

        // Non-critical side effect
        try {
            event(new TrialConvertedEvent($subscription));
        } catch (\Throwable $e) {
            $this->logger->error('TrialConversionService: failed to fire TrialConvertedEvent', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->logger->info('TrialConversionService: subscription converted', [
            'subscription_id' => $subscription->id,
            'new_end_date' => $newEndDate->format('Y-m-d H:i:s'),
        ]);

        return true;
    }

    private function expireSubscription(Subscription $subscription): void
    {
        try {
            $this->database->transaction(function () use ($subscription) {
                $subscription->update([
                    'status' => SubscriptionStatus::EXPIRED->value,
                    'cancelled_at' => now_datetime()->format('Y-m-d H:i:s'),
                ]);
            });
        } catch (\Throwable $e) {
            // Root cause already logged by the caller
            $this->logger->error('TrialConversionService: failed to expire subscription', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
