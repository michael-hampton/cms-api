<?php

namespace App\Services\Subscriptions;

use App\Exceptions\Subscriptions\PlanNotFoundException;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

class SubscriptionEligibilityService
{
    public function __construct(
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly SubscriptionRepository     $subscriptionRepository
    )
    {
    }

    public function canMemberSubscribe(int $memberId, int $planId, int $siteId, bool $allowMultiplePlans = false): array
    {
        $plan = $this->planRepository->find($planId);

        if (!$plan || !$plan->is_active) {
            return [
                'can_subscribe' => false,
                'reason' => 'Plan not available'
            ];
        }

        if ($this->subscriptionRepository->hasActiveSubscriptionToPlan($memberId, $planId, $siteId)) {
            return [
                'can_subscribe' => false,
                'reason' => 'Already subscribed to this plan'
            ];
        }

        if (!$allowMultiplePlans) {
            $activeSubscription = $this->subscriptionRepository->getActiveSubscriptionForMember($memberId, $siteId);

            if ($activeSubscription) {
                return [
                    'can_subscribe' => false,
                    'reason' => 'Already has an active subscription',
                    'current_plan' => $activeSubscription->plan_name
                ];
            }
        }


        return [
            'can_subscribe' => true,
            'plan' => $plan
        ];
    }

    public function ensurePlanExists(int $planId): void
    {
        $plan = $this->planRepository->find($planId);

        if (!$plan) {
            throw new PlanNotFoundException("Plan with ID {$planId} not found");
        }
    }
}