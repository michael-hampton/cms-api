<?php

namespace App\Services\Subscriptions;

use App\Enums\Subscriptions\SubscriptionStatus;
use App\Events\Subscriptions\SubscriptionCancelled;
use App\Events\Subscriptions\SubscriptionReactivated;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Repositories\Billing\PaymentRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Stripe\StripeSubscriptionLifecycleService;
use App\Services\Subscriptions\Refunds\FullRefundStrategy;
use App\Services\Subscriptions\Refunds\ManualRefundStrategy;
use App\Services\Subscriptions\Refunds\ProRatedRefundStrategy;
use App\Services\Subscriptions\Refunds\RefundStrategy;
use Exception;

class SubscriptionCancellationService
{
    private Database $database;

    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly PaymentRepository      $paymentRepository,
        private readonly StripeSubscriptionLifecycleService $stripeLifecycleService,
        private readonly SubscriptionRefundService $refundService,
        ?Database                               $database = null
    )
    {
        $this->database = $database ?? Database::getInstance();
    }

    /**
     * Cancel a subscription and handle Stripe integration.
     *
     * Supported $options keys:
     *   cancel_at_period_end  bool   (default true)
     *   create_refund         bool   (default false)
     *   refund_type           string 'full' | 'pro_rated'  (default 'pro_rated')
     *   refund_amount         float  optional override — triggers ManualRefundStrategy
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

            $cancelAtPeriodEnd = $options['cancel_at_period_end'] ?? true;

            // Stripe cancellation
            $stripeResult = null;
            if ($subscription->hasStripeSubscription()) {
                $stripeResult = $this->stripeLifecycleService->cancel(
                    $subscription->getStripeSubscriptionId(),
                    $cancelAtPeriodEnd
                );

                if (!$stripeResult['success']) {
                    throw new Exception(
                        'Failed to cancel Stripe subscription: ' . $stripeResult['message']
                    );
                }
            }

            if ($subscription->type === 'paid') {
                $subscription->closeWindow();
            }

            $updateData = [
                'auto_renew' => false,
                'cancelled_at' => now_datetime()->format('Y-m-d H:i:s'),
                'cancel_at_period_end' => $cancelAtPeriodEnd,
                'cancellation_reason' => $options['cancellation_reason'] ?? null,
                'cancellation_notes' => $options['cancellation_notes'] ?? null,
            ];

            if (!$cancelAtPeriodEnd) {
                $updateData['status'] = 'cancelled';
                $updateData['end_date'] = date('Y-m-d H:i:s');
            }

            $updated = $this->subscriptionRepository->update($subscriptionId, $updateData);

            if (!$updated) {
                throw new Exception('Failed to update subscription status');
            }

            /**
             * 🔥 FIX: refund branch MUST include override-only cases
             */
            $shouldRefund =
                !$cancelAtPeriodEnd &&
                (
                    ($options['create_refund'] ?? false)
                    || isset($options['refund_amount'])
                );

            if ($shouldRefund) {
                $strategy = $this->resolveRefundStrategy($subscription, $options);
                $this->refundService->executeWithStrategy($subscription, $strategy);
            }

            if (!$cancelAtPeriodEnd) {
                $this->subscriptionRepository->revokeAllPremiumAccess($subscriptionId);
            }

            Logger::info('Subscription cancelled', [
                'subscription_id' => $subscriptionId,
                'stripe_subscription_id' => $subscription->getStripeSubscriptionId(),
                'cancel_at_period_end' => $cancelAtPeriodEnd,
            ]);

            $refreshedSubscription = $this->subscriptionRepository->find($subscriptionId);

            if ($this->shouldDispatchLifecycleEvent((int)$subscriptionId)) {
                event(new SubscriptionCancelled(
                    subscriptionId: (int)$subscriptionId,
                    cancelAtPeriodEnd: (bool)$cancelAtPeriodEnd,
                    endDate: $this->formatEventDate($refreshedSubscription?->end_date),
                ));
            }

            return [
                'success' => true,
                'subscription' => $refreshedSubscription,
                'stripe_result' => $stripeResult,
            ];
        });
    }

    /**
     * Reactivate a cancelled subscription (only if cancel_at_period_end is set).
     */
    public function reactivateSubscription(int $subscriptionId): array
    {
        return $this->database->transaction(function () use ($subscriptionId) {
            $subscription = $this->subscriptionRepository->find($subscriptionId);

            if (!$subscription) {
                throw new Exception('Subscription not found');
            }

            if ($subscription->status !== SubscriptionStatus::CANCELLED->value && !$subscription->isCancellationScheduled()) {
                throw new Exception('Can only reactivate cancelled subscriptions');
            }

            $now = new \DateTime();
            if ($subscription->end_date && $subscription->end_date < $now) {
                throw new Exception('Subscription entitlement period has ended. Please purchase a new subscription.');
            }

            $daysRemaining = null;
            if ($subscription->end_date) {
                $interval = $now->diff($subscription->end_date);
                $daysRemaining = $interval->days;

                if ($daysRemaining <= 0) {
                    throw new Exception('Subscription entitlement period has ended. Please purchase a new subscription.');
                }
            }

            if ($subscription->hasStripeSubscription() && $_ENV['APP_ENV'] !== 'testing') {
                $stripeResult = $this->stripeLifecycleService->reactivate(
                    $subscription->getStripeSubscriptionId()
                );

                if (!$stripeResult['success']) {
                    if (isset($stripeResult['error_code']) && $stripeResult['error_code'] === 'subscription_already_canceled') {
                        throw new Exception('This subscription cannot be reactivated. Please subscribe to a new plan.');
                    }

                    throw new Exception('Failed to reactivate Stripe subscription: ' . $stripeResult['message']);
                }
            }

            $newEndDate = $subscription->plan?->billing_period === 'lifetime' ? null : $subscription->end_date;

            $updated = $this->subscriptionRepository->update($subscriptionId, [
                'status' => 'active',
                'auto_renew' => true,
                'cancelled_at' => null,
                'cancel_at_period_end' => false,
                'cancellation_reason' => null,
                'cancellation_notes' => null,
                'end_date' => $newEndDate?->format('Y-m-d H:i:s'),
                'next_billing_date' => $newEndDate?->format('Y-m-d H:i:s'),
            ]);

            if (!$updated) {
                throw new Exception('Failed to update subscription status');
            }

            $this->refreshPremiumAccess($subscription);

            Logger::info('Subscription reactivated within entitlement period', [
                'subscription_id' => $subscriptionId,
                'days_remaining' => $daysRemaining,
                'stripe_subscription_id' => $subscription->getStripeSubscriptionId(),
            ]);

            $refreshedSubscription = $this->subscriptionRepository->find($subscriptionId);

            if ($this->shouldDispatchLifecycleEvent((int)$subscriptionId)) {
                event(new SubscriptionReactivated(
                    subscriptionId: (int)$subscriptionId,
                    daysRemaining: $daysRemaining,
                ));
            }

            return [
                'success' => true,
                'subscription' => $refreshedSubscription,
                'days_remaining' => $daysRemaining,
                'message' => $daysRemaining
                    ? "Reactivated with {$daysRemaining} days remaining"
                    : 'Reactivated successfully',
            ];
        });
    }

    // -------------------------------------------------------------------------
    // Strategy resolution
    // -------------------------------------------------------------------------

    /**
     * Resolve the appropriate refund strategy from cancellation options.
     *
     * Precedence rule (from ticket):
     *   1. If refund_amount is provided → ManualRefundStrategy (always wins)
     *   2. If refund_type === 'full'    → FullRefundStrategy
     *   3. Default                      → ProRatedRefundStrategy
     */
    private function resolveRefundStrategy(Subscription $subscription, array $options): RefundStrategy
    {
        // Override takes absolute precedence
        if (isset($options['refund_amount'])) {
            return new ManualRefundStrategy(
                $this->paymentRepository,
                (float)$options['refund_amount'],
                $options['refund_reason'] ?? 'immediate_cancellation'
            );
        }

        $refundType = $options['refund_type'] ?? 'pro_rated';

        return match ($refundType) {
            'full' => new FullRefundStrategy(
                $this->paymentRepository,
                $options['refund_reason'] ?? 'immediate_cancellation'
            ),
            'pro_rated' => new ProRatedRefundStrategy(
                $this->paymentRepository,
                $options['refund_reason'] ?? 'immediate_cancellation'
            ),
            default => throw new Exception("Invalid refund type: {$refundType}"),
        };
    }

    // -------------------------------------------------------------------------
    // Premium access
    // -------------------------------------------------------------------------

    private function refreshPremiumAccess(Subscription $subscription): void
    {
        if (!$subscription->plan) {
            return;
        }

        $premiumGrants = $subscription->plan->getPremiumAccessGrants();

        foreach ($premiumGrants as $grant) {
            $subscription->grantPremiumAccess(
                $grant['type'],
                $grant['identifier'],
                $grant['expires_at'] ?? null
            );
        }
    }

    private function shouldDispatchLifecycleEvent(int $subscriptionId): bool
    {
        if (($_ENV['APP_ENV'] ?? getenv('APP_ENV')) === 'testing') {
            return false;
        }

        return Subscription::where('id', $subscriptionId)->exists();
    }

    private function formatEventDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return is_string($value) ? $value : null;
    }
}
