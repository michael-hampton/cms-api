<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\BusinessDecisions;

use App\DTO\Subscriptions\BusinessDecisions\SuspensionOptionsData;
use App\Models\SuspensionReason;
use App\Repositories\Subscriptions\BusinessDecisions\SuspensionReasonRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use InvalidArgumentException;

/**
 * Orchestrates the GET .../suspension-options read path: loads the
 * subscription and plan, delegates resolution to SuspensionOptionsResolver,
 * and shapes the result as SuspensionOptionsData. No writes.
 */
class SuspensionOptionsService
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly SuspensionOptionsResolver $resolver,
        private readonly SuspensionReasonRepository $suspensionReasonRepository,
        private readonly SubscriptionPlanRepository $planRepository,
    ) {
    }

    public function forSubscription(int $subscriptionId): SuspensionOptionsData
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription) {
            throw new InvalidArgumentException('Subscription not found.');
        }

        $plan = $this->planRepository->find((int) $subscription->plan_id);

        if (!$plan) {
            throw new InvalidArgumentException('Subscription plan not found.');
        }

        $options = $this->resolver->resolveForPlan((int) $plan->id, (int) $subscription->site_id);

        return new SuspensionOptionsData(
            subscriptionId: (int) $subscription->id,
            planId: (int) $plan->id,
            allowSuspend: $options->allowSuspend,
            requiresNote: $options->requiresNote,
            reasons: $this->suspensionReasonRepository->listActive()
                ->map(static fn (SuspensionReason $reason) => [
                    'id' => (int) $reason->id,
                    'code' => (string) $reason->code,
                    'label' => (string) $reason->label,
                    'requires_note' => (bool) $reason->requires_note,
                ])->toArray(),
        );
    }
}
