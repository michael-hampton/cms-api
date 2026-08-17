<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Framework\Support\Logger;
use App\Repositories\Subscriptions\SubscriptionPlanPricingRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Stripe\StripeSubscriptionPlanUpdater;

class SubscriptionStripePlanSyncService
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly StripeSubscriptionPlanUpdater $planUpdater,
        private readonly SubscriptionPlanPricingRepository $pricingRepository,
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly Logger $logger,
    ) {}

    public function syncPlanChange(int $subscriptionId): void
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || ($subscription->stripe_sync_status ?? null) !== 'pending') {
            return;
        }

        try {
            $stripeSubscriptionId = $subscription->stripe_subscription_id
                ?: $subscription->payment_subscription_id
                ?: null;
            $stripeItemId = $subscription->stripe_subscription_item_id ?? null;
            $stripePriceId = $this->resolveTargetStripePriceId($subscription);

            if (!$stripeSubscriptionId) {
                throw new \RuntimeException('Cannot sync Stripe plan change without a Stripe subscription id.');
            }

            if (!$stripeItemId) {
                $stripeItemId = $this->planUpdater->findFirstSubscriptionItemId($stripeSubscriptionId);

                if ($stripeItemId) {
                    $this->subscriptionRepository->update($subscriptionId, [
                        'stripe_subscription_item_id' => $stripeItemId,
                    ]);
                }
            }

            if (!$stripeItemId) {
                throw new \RuntimeException('Cannot resolve Stripe subscription item id for this subscription.');
            }

            if (!$stripePriceId) {
                throw new \RuntimeException('Cannot sync Stripe plan change without a target Stripe price id.');
            }

            if (($subscription->stripe_price_id ?? null) !== $stripePriceId) {
                $this->subscriptionRepository->update($subscriptionId, [
                    'stripe_price_id' => $stripePriceId,
                ]);
            }

            $result = $this->planUpdater->updateSubscriptionItemPrice(
                $stripeItemId,
                $stripePriceId,
                ['proration_behavior' => 'none'],
            );

            if (($result['success'] ?? false) !== true) {
                throw new \RuntimeException((string)($result['error'] ?? 'Stripe plan sync failed.'));
            }

            $this->subscriptionRepository->update($subscriptionId, [
                'stripe_sync_status' => 'synced',
                'stripe_sync_error' => null,
                'stripe_synced_at' => now_datetime()->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            $this->subscriptionRepository->update($subscriptionId, [
                'stripe_sync_status' => 'failed',
                'stripe_sync_error' => $e->getMessage(),
            ]);

            $this->logger->error('Stripe subscription plan sync failed', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveTargetStripePriceId(object $subscription): ?string
    {
        if (!empty($subscription->stripe_price_id)) {
            return (string) $subscription->stripe_price_id;
        }

        if (!empty($subscription->subscription_plan_pricing_id)) {
            $pricing = $this->pricingRepository->find((int) $subscription->subscription_plan_pricing_id);

            if (!empty($pricing?->stripe_price_id)) {
                return (string) $pricing->stripe_price_id;
            }
        }

        if (empty($subscription->plan_id)) {
            return null;
        }

        $defaultPricing = $this->pricingRepository->getDefaultForPlan((int) $subscription->plan_id);

        if (!empty($defaultPricing?->stripe_price_id)) {
            return (string) $defaultPricing->stripe_price_id;
        }

        $plan = $this->planRepository->find((int) $subscription->plan_id);

        return !empty($plan?->stripe_price_id)
            ? (string) $plan->stripe_price_id
            : null;
    }
}
