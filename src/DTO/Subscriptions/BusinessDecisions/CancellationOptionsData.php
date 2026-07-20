<?php

declare(strict_types=1);

namespace App\DTO\Subscriptions\BusinessDecisions;

/**
 * Top-level shape for GET .../cancellation-options, matching the
 * ticket's example response. `businessDecisionSource` uses the ticket's
 * own vocabulary (default|brand|product) even though internally "brand"
 * is this codebase's Site model and "product" is SubscriptionPlan — see
 * CancellationOptionsResolver for that mapping.
 *
 * @param CancellationReasonOptionData[] $reasons
 */
final class CancellationOptionsData
{
    public function __construct(
        public readonly int $subscriptionId,
        public readonly int $planId,
        public readonly string $planCode,
        public readonly string $planName,
        public readonly int $businessDecisionId,
        public readonly string $businessDecisionName,
        public readonly string $businessDecisionCategory,
        public readonly string $businessDecisionSource,
        public readonly array $reasons,
    ) {
    }

    public function toArray(): array
    {
        return [
            'subscription_id' => $this->subscriptionId,
            'product' => [
                'id' => $this->planId,
                'code' => $this->planCode,
                'name' => $this->planName,
            ],
            'business_decision' => [
                'id' => $this->businessDecisionId,
                'name' => $this->businessDecisionName,
                'category' => $this->businessDecisionCategory,
                'source' => $this->businessDecisionSource,
            ],
            'reasons' => array_map(
                static fn (CancellationReasonOptionData $reason) => $reason->toArray(),
                $this->reasons,
            ),
        ];
    }
}
