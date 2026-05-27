<?php

namespace App\Services\Subscriptions;

use App\Exceptions\Subscriptions\MissingStripePriceException;
use App\Exceptions\Subscriptions\StripeUpdateFailedException;
use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Billing\Stripe\StripeSubscriptionPlanUpdater;

class StripeSubscriptionUpgradeService
{
    public function __construct(
        private readonly StripeSubscriptionPlanUpdater $planUpdater
    )
    {
    }

    public function updateSubscriptionPlan(
        Subscription     $subscription,
        SubscriptionPlan $upgradePlan
    ): void
    {
        $stripeSubscriptionId = $subscription->getStripeSubscriptionId();

        if (!$stripeSubscriptionId || ($_ENV['APP_ENV'] ?? 'production') === 'testing') {
            return;
        }

        $priceId = $upgradePlan->stripe_price_id;

        if (!$priceId) {
            throw new MissingStripePriceException(
                "Cannot upgrade: Plan '{$upgradePlan->name}' is missing Stripe price ID. Please contact support."
            );
        }

        $result = $this->planUpdater->update(
            $stripeSubscriptionId,
            $priceId,
            [
                'upgraded_at' => now_datetime()->format('Y-m-d H:i:s'),
                'original_plan_id' => $subscription->plan_id,
            ]
        );

        if (!$result['success']) {
            throw new StripeUpdateFailedException(
                "Failed to update Stripe subscription: " . ($result['error'] ?? 'Unknown error')
            );
        }

        Logger::info("Stripe subscription updated for upgrade", [
            'subscription_id' => $subscription->id,
            'stripe_subscription_id' => $stripeSubscriptionId
        ]);
    }
}
