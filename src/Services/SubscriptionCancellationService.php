<?php

namespace App\Services;

use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Repositories\PaymentRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\Payment\StripePaymentProcessor;
use Exception;

class SubscriptionCancellationService
{
    private Database $database;

    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly PaymentRepository      $paymentRepository,
        private readonly StripePaymentProcessor $stripeProcessor,
        ?Database                               $database = null
    )
    {
        $this->database = $database ?? Database::getInstance();
    }

    /**
     * Cancel a subscription and handle Stripe integration
     */
    public function cancelSubscription(int $subscriptionId, array $options = []): array
    {
        return $this->database->transaction(function () use ($subscriptionId, $options) {
            $subscription = $this->subscriptionRepository->find($subscriptionId);

            if (!$subscription) {
                throw new Exception('Subscription not found');
            }

            if ($subscription->status === 'cancelled') {
                throw new Exception('Subscription is already cancelled');
            }

            // Cancel in Stripe if subscription has Stripe ID
            $stripeResult = null;
            if ($subscription->hasStripeSubscription()) {
                $cancelAtPeriodEnd = $options['cancel_at_period_end'] ?? false;

                $stripeResult = $this->stripeProcessor->cancelSubscription(
                    $subscription->getStripeSubscriptionId(),
                    $cancelAtPeriodEnd
                );

                if (!$stripeResult['success']) {
                    throw new Exception('Failed to cancel Stripe subscription: ' . $stripeResult['message']);
                }
            }

            if ($subscription->type === 'paid') {
                $subscription->closeWindow();
            }

            // Update local subscription
            $updateData = [
                'status' => 'cancelled',
                'auto_renew' => false
            ];

            // If cancelling at period end, keep the end_date
            // Otherwise, set it to now
            if (!($options['cancel_at_period_end'] ?? true)) {
                $updateData['end_date'] = date('Y-m-d H:i:s');
            }

            $updated = $this->subscriptionRepository->update($subscriptionId, $updateData);

            if (!$updated) {
                throw new Exception('Failed to update subscription status');
            }

            // Create a refund record if immediate cancellation and payment exists
            if (!($options['cancel_at_period_end'] ?? true) && ($options['create_refund'] ?? false)) {
                $this->createProRatedRefund($subscription);
            }

            Logger::info("Subscription cancelled", [
                'subscription_id' => $subscriptionId,
                'stripe_subscription_id' => $subscription->getStripeSubscriptionId(),
                'cancel_at_period_end' => $options['cancel_at_period_end'] ?? false
            ]);

            return [
                'success' => true,
                'subscription' => $this->subscriptionRepository->find($subscriptionId),
                'stripe_result' => $stripeResult
            ];
        });
    }

    /**
     * Reactivate a cancelled subscription
     */

    /**
     * Create a pro-rated refund for immediate cancellation
     */
    private function createProRatedRefund(Subscription $subscription): void
    {
        if (!$subscription->end_date || !$subscription->last_payment_date) {
            return;
        }

        $now = new \DateTime();
        $endDate = $subscription->end_date;
        $lastPayment = $subscription->last_payment_date;

        // Calculate unused days
        $totalDays = $lastPayment->diff($endDate)->days;
        $usedDays = $lastPayment->diff($now)->days;
        $unusedDays = max(0, $totalDays - $usedDays);

        if ($unusedDays <= 0) {
            return;
        }

        // Calculate refund amount
        $refundAmount = ($subscription->price / $totalDays) * $unusedDays;

        // Create payment record for refund
        $lastCompletedPayment = $this->paymentRepository->getLastSubscriptionPayment($subscription->id);

        if ($lastCompletedPayment) {
            $this->paymentRepository->create([
                'subscription_id' => $subscription->id,
                'site_id' => $subscription->site_id,
                'payment_method' => 'stripe',
                'payment_provider' => 'stripe',
                'amount' => -$refundAmount, // Negative for refund
                'currency' => $subscription->currency,
                'status' => 'completed',
                'paid_at' => date('Y-m-d H:i:s'),
                'metadata' => [
                    'refund_type' => 'pro_rated_cancellation',
                    'original_payment_id' => $lastCompletedPayment->id,
                    'unused_days' => $unusedDays,
                    'total_days' => $totalDays
                ]
            ]);

            Logger::info("Pro-rated refund created", [
                'subscription_id' => $subscription->id,
                'refund_amount' => $refundAmount,
                'unused_days' => $unusedDays
            ]);
        }
    }

    /**
     * Reactivate a cancelled subscription (only if cancel_at_period_end is set)
     */
    public function reactivateSubscription(int $subscriptionId): array
    {
        return $this->database->transaction(function () use ($subscriptionId) {
            $subscription = $this->subscriptionRepository->find($subscriptionId);

            if (!$subscription) {
                throw new Exception('Subscription not found');
            }

            if ($subscription->status !== 'cancelled') {
                throw new Exception('Can only reactivate cancelled subscriptions');
            }

            // Check if subscription has already ended
            if ($subscription->end_date && $subscription->end_date < new \DateTime()) {
                throw new Exception('Subscription has already ended and cannot be reactivated. Please create a new subscription.');
            }

            // Reactivate in Stripe if subscription has Stripe ID
            if ($subscription->hasStripeSubscription() && $_ENV['APP_ENV'] !== 'testing') {
                $stripeResult = $this->stripeProcessor->reactivateSubscription(
                    $subscription->getStripeSubscriptionId()
                );

                if (!$stripeResult['success']) {
                    // Check if it's because the subscription is already fully canceled
                    if (isset($stripeResult['error_code']) && $stripeResult['error_code'] === 'subscription_already_canceled') {
                        throw new Exception('This subscription cannot be reactivated. Please subscribe to a new plan.');
                    }

                    throw new Exception('Failed to reactivate Stripe subscription: ' . $stripeResult['message']);
                }
            }

            // Calculate new end date based on billing period
            $newEndDate = null;
            if ($subscription->plan && $subscription->plan->billing_period !== 'lifetime') {
                $newEndDate = new \DateTime();
                match ($subscription->plan->billing_period) {
                    'monthly' => $newEndDate->modify('+1 month'),
                    'quarterly' => $newEndDate->modify('+3 months'),
                    'yearly' => $newEndDate->modify('+1 year'),
                };
            }

            // Update subscription
            $updated = $this->subscriptionRepository->update($subscriptionId, [
                'status' => 'active',
                'auto_renew' => true,
                'end_date' => $newEndDate?->format('Y-m-d H:i:s'),
                'next_billing_date' => $newEndDate?->format('Y-m-d H:i:s')
            ]);

            if (!$updated) {
                throw new Exception('Failed to update subscription status');
            }

            Logger::info("Subscription reactivated", [
                'subscription_id' => $subscriptionId,
                'stripe_subscription_id' => $subscription->getStripeSubscriptionId()
            ]);

            return [
                'success' => true,
                'subscription' => $this->subscriptionRepository->find($subscriptionId)
            ];
        });
    }
}