<?php

namespace App\Services\Subscriptions;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionRepository;

class PremiumAccessGrantService
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository
    )
    {
    }

    public function grantPremiumAccess(
        Subscription     $subscription,
        SubscriptionPlan $upgradePlan,
        int              $subscriptionId
    ): array
    {
        $premiumGrants = $upgradePlan->getPremiumAccessGrants();
        $grantedAccess = [];

        foreach ($premiumGrants as $grant) {
            $access = $subscription->grantPremiumAccess(
                $grant['type'],
                $grant['identifier'],
                $grant['expires_at'] ?? null
            );
            $grantedAccess[] = $access;

            // Backward compatibility: Set digital access flag for insider
            if ($grant['type'] === 'newsletter' && $grant['identifier'] === 'insider') {
                $this->subscriptionRepository->update($subscriptionId, [
                    'includes_digital_access' => true
                ]);
            }
        }

        return $grantedAccess;
    }
}