<?php

namespace App\Services\Subscriptions;

use App\Enums\Subscriptions\BillingPeriod;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Billing\PaymentRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Order\OrderStateManager;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use App\Services\Billing\Payments\PaymentRecorder;
use App\Services\Billing\PaymentService;
use DateTime;
use Exception;

class SubscriptionPaymentService
{
    private Database $database;

    public function __construct(
        private readonly PaymentRepository      $paymentRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly PaymentService         $paymentService,
        private readonly StripePaymentProcessor   $stripePaymentProcessor,
        private readonly PaymentRecorder          $paymentRecorder,
        private readonly SubscriptionStateManager $subscriptionStateManager,
        private readonly OrderStateManager        $orderStateManager,
        ?Database                               $database = null
    )
    {
        $this->database = $database ?? Database::getInstance();
    }

    public function processStripeSubscriptionPayment(
        Subscription     $subscription,
        SubscriptionPlan $plan,
        array            $data
    ): array
    {
        $stripeResult = $this->stripePaymentProcessor->processSubscriptionPayment(
            $subscription,
            $plan,
            $data
        );

        if (!($stripeResult['success'] ?? false)) {
            return $stripeResult;
        }

        /**
         * todo need to get the total from the order as it includes the tax and shipping
         * <pre>Array
         * (
         * [payment_method_id] => pm_1TUSLvGvaZO1S9EXRv73vub6
         * [order_id] => 124
         * )
         */

        $payment = $this->paymentRecorder->recordSubscriptionStripePayment(
            $subscription,
            $plan,
            [
                'transaction_id' => $stripeResult['payment_intent_id'] ?? $stripeResult['subscription_id'],
                'payment_intent_id' => $stripeResult['payment_intent_id'] ?? null,
                'status' => $this->mapStripeStatusToPaymentStatus($stripeResult['status'] ?? 'pending'),
                'stripe_subscription_id' => $stripeResult['subscription_id'] ?? null,
                'stripe_customer_id' => $stripeResult['customer_id'] ?? null,
                'order_id' => $data['order_id'] ?? null,
                'amount_cents' => isset($data['amount_cents'])
                    ? (int)$data['amount_cents']
                    : (isset($data['amount']) ? (int)round(((float)$data['amount']) * 100) : null),
            ]
        );

        if (($stripeResult['status'] ?? null) === 'active' && !($stripeResult['requires_action'] ?? false)) {
            $this->paymentRecorder->markCompleted($payment);
            $this->subscriptionStateManager->markActiveFromStripe(
                $subscription,
                $stripeResult['current_period_start'] ?? null,
                $stripeResult['current_period_end'] ?? null,
            );

            if (!empty($data['order_id'])) {
                $this->orderStateManager->markPaid((int)$data['order_id']);
            }
        }

        return array_merge($stripeResult, [
            'payment_id' => $payment->id,
        ]);
    }

    /**
     * Create initial payment for a new subscription
     */
    public function createInitialSubscriptionPayment(
        int   $subscriptionId,
        int   $memberId,
        array $paymentData = []
    ): Payment
    {
        return $this->database->transaction(function () use ($subscriptionId, $memberId, $paymentData) {
            $subscription = $this->subscriptionRepository->find($subscriptionId);

            if (!$subscription) {
                throw new Exception('Subscription not found');
            }

            if ($subscription->member_id !== $memberId) {
                throw new Exception('Subscription does not belong to member');
            }

            // Create payment record
            $payment = $this->paymentRepository->create([
                'subscription_id' => $subscriptionId,
                'order_id' => null,
                'site_id' => $subscription->site_id,
                'payment_method' => $paymentData['payment_method'] ?? 'stripe',
                'payment_provider' => $paymentData['payment_provider'] ?? 'stripe',
                'amount' => $subscription->price,
                'currency' => $subscription->currency,
                'status' => 'pending',
                'transaction_id' => $paymentData['transaction_id'] ?? null,
                'payment_intent_id' => $paymentData['payment_intent_id'] ?? null,
                'metadata' => array_merge(
                    ['subscription_initial_payment' => true],
                    $paymentData['metadata'] ?? []
                )
            ]);

            Logger::info("Initial subscription payment created", [
                'payment_id' => $payment->id,
                'subscription_id' => $subscriptionId,
                'amount' => $payment->amount
            ]);

            return $payment;
        });
    }

    /**
     * Complete subscription payment and update subscription
     */
    public function completeSubscriptionPayment(int $paymentId): Payment
    {
        return $this->database->transaction(function () use ($paymentId) {
            $payment = $this->paymentRepository->find($paymentId);

            if (!$payment) {
                throw new Exception('Payment not found');
            }

            if (!$payment->isSubscriptionPayment()) {
                throw new Exception('Payment is not for a subscription');
            }

            // Complete the payment
            $payment = $this->paymentService->completePayment($paymentId);

            // Update subscription
            $subscription = $this->subscriptionRepository->find($payment->subscription_id);

            if ($subscription) {
                // Update last payment date
                $this->subscriptionRepository->updateLastPaymentDate(
                    $subscription->id,
                    new DateTime()
                );

                // Calculate and update next billing date
                if ($subscription->auto_renew && $subscription->plan) {
                    $baseDate = $subscription->end_date ?? new DateTime();
                    $nextBillingDate = $this->calculateNextBillingDate(
                        $subscription->plan->billing_period,
                        $baseDate
                    );

                    $this->subscriptionRepository->updateNextBillingDate(
                        $subscription->id,
                        $nextBillingDate
                    );

                    // Update end date as well
                    $this->subscriptionRepository->update($subscription->id, [
                        'end_date' => $nextBillingDate->format('Y-m-d H:i:s')
                    ]);
                }

                // If subscription was past_due, reactivate it
                if ($subscription->status === 'past_due') {
                    $this->subscriptionRepository->update($subscription->id, [
                        'status' => 'active'
                    ]);
                }
            }

            Logger::info("Subscription payment completed", [
                'payment_id' => $paymentId,
                'subscription_id' => $payment->subscription_id
            ]);

            return $payment;
        });
    }

    /**
     * Calculate next billing date based on billing period
     */
    private function calculateNextBillingDate(string|BillingPeriod $billingPeriod, ?DateTime $baseDate = null): DateTime
    {
        $nextDate = clone($baseDate ?? new DateTime());

        $period = is_string($billingPeriod) ? BillingPeriod::from($billingPeriod) : $billingPeriod;

        $modifier = $period->toDateModifier();
        if ($modifier === null) {
            throw new Exception("Cannot calculate next billing date for billing period: {$period->value}");
        }

        $nextDate->modify($modifier);
        return $nextDate;
    }

    private function mapStripeStatusToPaymentStatus(string $status): string
    {
        return match ($status) {
            'active' => 'completed',
            'trialing' => 'completed',
            'incomplete' => 'processing',
            'incomplete_expired' => 'failed',
            'past_due' => 'failed',
            'canceled' => 'cancelled',
            'unpaid' => 'pending',
            default => 'pending'
        };
    }

    /**
     * Handle failed subscription payment
     */
    public function handleFailedSubscriptionPayment(int $paymentId, string $errorMessage): Payment
    {
        return $this->database->transaction(function () use ($paymentId, $errorMessage) {
            $payment = $this->paymentRepository->find($paymentId);

            if (!$payment) {
                throw new Exception('Payment not found');
            }

            if (!$payment->isSubscriptionPayment()) {
                throw new Exception('Payment is not for a subscription');
            }

            // Mark payment as failed
            $payment = $this->paymentService->failPayment($paymentId, $errorMessage);

            // Update subscription status
            $subscription = $this->subscriptionRepository->find($payment->subscription_id);

            if ($subscription) {
                $failedPaymentCount = $this->paymentRepository->countSubscriptionPayments(
                    $subscription->id,
                    'failed'
                );

                // Mark subscription as past_due after first failed payment
                if ($failedPaymentCount >= 1 && $subscription->status === 'active') {
                    $this->subscriptionRepository->markAsPastDue($subscription->id);
                }

                // Cancel subscription after 3 failed payments
                if ($failedPaymentCount >= 3) {
                    $this->subscriptionRepository->cancelSubscription($subscription->id);

                    Logger::warning("Subscription cancelled due to multiple failed payments", [
                        'subscription_id' => $subscription->id,
                        'failed_payment_count' => $failedPaymentCount
                    ]);
                }
            }

            Logger::error("Subscription payment failed", [
                'payment_id' => $paymentId,
                'subscription_id' => $payment->subscription_id,
                'error' => $errorMessage
            ]);

            return $payment;
        });
    }

    /**
     * Process all subscriptions due for renewal
     */
    public function processRenewals(?int $siteId = null): array
    {
        $subscriptions = $this->subscriptionRepository->getSubscriptionsDueForRenewal($siteId);
        $results = [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($subscriptions as $subscription) {
            try {
                $payment = $this->createRecurringPayment($subscription->id);

                // Here you would integrate with payment provider to charge the card
                // For now, we just create the payment record

                $results['processed']++;

                Logger::info("Subscription renewal processed", [
                    'subscription_id' => $subscription->id,
                    'payment_id' => $payment->id
                ]);

            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage()
                ];

                Logger::error("Failed to process subscription renewal", [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Create recurring payment for subscription renewal
     */
    public function createRecurringPayment(int $subscriptionId): Payment
    {
        return $this->database->transaction(function () use ($subscriptionId) {
            $subscription = $this->subscriptionRepository->find($subscriptionId);

            if (!$subscription) {
                throw new Exception('Subscription not found');
            }

            if (!$subscription->isDueForRenewal()) {
                throw new Exception('Subscription is not due for renewal');
            }

            // NEW: Check for existing pending payment for this billing cycle
            $billingDate = $subscription->next_billing_date ?? new DateTime();
            if ($this->subscriptionRepository->hasPendingPaymentForCycle($subscriptionId, $billingDate)) {
                throw new Exception('Pending payment already exists for this billing cycle');
            }

            // Create payment record for renewal
            $payment = $this->paymentRepository->create([
                'subscription_id' => $subscriptionId,
                'order_id' => null,
                'site_id' => $subscription->site_id,
                'payment_method' => $subscription->payment_method ?? 'stripe',
                'payment_provider' => $subscription->payment_method ?? 'stripe',
                'amount' => $subscription->price,
                'currency' => $subscription->currency,
                'status' => 'pending',
                'metadata' => [
                    'subscription_renewal' => true,
                    'billing_period' => $subscription->plan->billing_period ?? 'monthly'
                ]
            ]);

            Logger::info("Recurring subscription payment created", [
                'payment_id' => $payment->id,
                'subscription_id' => $subscriptionId,
                'amount' => $payment->amount
            ]);

            return $payment;
        });
    }

    /**
     * Get payment history for a subscription
     */
    public function getSubscriptionPaymentHistory(int $subscriptionId): array
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription) {
            throw new Exception('Subscription not found');
        }

        $payments = $this->paymentRepository->findBySubscriptionId($subscriptionId);

        return [
            'subscription' => $subscription,
            'payments' => $payments,
            'total_paid' => $payments->where('status', 'completed')->sum('amount'),
            'failed_count' => $payments->where('status', 'failed')->count()
        ];
    }

    /**
     * Retry failed subscription payment
     */
    public function retrySubscriptionPayment(int $paymentId): Payment
    {
        $payment = $this->paymentRepository->find($paymentId);

        if (!$payment) {
            throw new Exception('Payment not found');
        }

        if (!$payment->isSubscriptionPayment()) {
            throw new Exception('Payment is not for a subscription');
        }

        return $this->paymentService->retryPayment($paymentId);
    }
}
