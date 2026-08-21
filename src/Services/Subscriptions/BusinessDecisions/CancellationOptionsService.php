<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\BusinessDecisions;

use App\DTO\Subscriptions\BusinessDecisions\CancellationOptionsData;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use InvalidArgumentException;

/**
 * Orchestrates the GET .../cancellation-options read path: loads the
 * subscription and plan, delegates all inheritance/resolution logic to
 * CancellationOptionsResolver, and shapes the result as
 * CancellationOptionsData. No writes, so no transaction boundary is
 * needed here.
 */
class CancellationOptionsService
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly CancellationOptionsResolver $resolver,
    ) {
    }

    public function forSubscription(int $subscriptionId): CancellationOptionsData
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription) {
            throw new InvalidArgumentException('Subscription not found.');
        }

        $plan = $this->planRepository->find((int) $subscription->plan_id);

        if (!$plan) {
            throw new InvalidArgumentException('Subscription plan not found.');
        }

        $resolved = $this->resolver->resolveForPlan((int) $plan->id, (int) $subscription->site_id);

        $decision = $resolved['decision'];

        return new CancellationOptionsData(
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
