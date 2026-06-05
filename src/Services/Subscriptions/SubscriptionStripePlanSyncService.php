<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Framework\Support\Logger;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Stripe\StripeSubscriptionPlanUpdater;

class SubscriptionStripePlanSyncService
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly StripeSubscriptionPlanUpdater $planUpdater,
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
            $stripePriceId = $subscription->stripe_price_id ?? null;

            if (!$stripeSubscriptionId) {
                throw new \RuntimeException('Cannot sync Stripe plan change without a Stripe subscription id.');
            }

            if (!$stripeItemId) {
                throw new \RuntimeException('Cannot sync Stripe plan change without a Stripe subscription item id.');
            }

            if (!$stripePriceId) {
                throw new \RuntimeException('Cannot sync Stripe plan change without a target Stripe price id.');
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

            Logger::error('Stripe subscription plan sync failed', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
