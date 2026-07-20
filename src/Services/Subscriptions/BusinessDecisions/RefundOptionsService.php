<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\BusinessDecisions;

use App\DTO\Subscriptions\BusinessDecisions\RefundOptionsData;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use InvalidArgumentException;

class RefundOptionsService
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly RefundOptionsResolver $resolver,
    ) {
    }

    public function forSubscription(int $subscriptionId): RefundOptionsData
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);
        if ($subscription === null) {
            throw new InvalidArgumentException('Subscription not found.');
        }

        $plan = $this->planRepository->find((int) $subscription->plan_id);
        if ($plan === null) {
            throw new InvalidArgumentException('Subscription plan not found.');
        }

        $resolved = $this->resolver->resolveForPlan((int) $plan->id, (int) $subscription->site_id);
        $decision = $resolved['decision'];

        return new RefundOptionsData(
            subscriptionId: (int) $subscription->id,
            planId: (int) $plan->id,
            planCode: (string) ($plan->slug ?? $plan->id),
            planName: (string) $plan->name,
            businessDecisionId: (int) $decision->id,
            businessDecisionName: (string) $decision->name,
            businessDecisionCategory: $decision->categoryValue(),
            businessDecisionSource: $resolved['source'],
            reasons: $resolved['reasons'],
        );
    }
}
