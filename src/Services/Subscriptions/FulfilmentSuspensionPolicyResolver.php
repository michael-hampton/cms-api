<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\DTO\Subscriptions\FulfilmentSuspensionRule;
use App\Enums\Subscriptions\FulfilmentSuspensionDelayType;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;

/**
 * Resolves and evaluates the "how long before we suspend pending
 * fulfilments" business rule described in the fulfilment-suspension ticket:
 *
 *   - defaults to immediate;
 *   - overridable per plan (product) as either a fixed number of days
 *     after the subscriber's first issue, or a number of further issues
 *     delivered after the first one.
 *
 * This resolver only decides *when* suspension is due. Applying the
 * suspension is FulfilmentSuspensionService's job.
 */
class FulfilmentSuspensionPolicyResolver
{
    public function __construct(
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly SubscriptionIssueFulfilmentRepository $fulfilmentRepository,
    ) {
    }

    public function resolveForPlan(int $planId): FulfilmentSuspensionRule
    {
        $plan = $this->planRepository->find($planId);

        if (!$plan) {
            return FulfilmentSuspensionRule::immediate();
        }

        $type = FulfilmentSuspensionDelayType::tryFrom((string) ($plan->fulfilment_suspension_delay_type ?? ''));

        if ($type === null || $type === FulfilmentSuspensionDelayType::IMMEDIATE) {
            return FulfilmentSuspensionRule::immediate();
        }

        $value = $plan->fulfilment_suspension_delay_value;

        if (!is_numeric($value) || (int) $value <= 0) {
            return FulfilmentSuspensionRule::immediate();
        }

        return new FulfilmentSuspensionRule($type, (int) $value);
    }

    /**
     * True when $rule is currently satisfied for $subscriptionId and
     * suspension should happen now.
     *
     * A subscriber who has not yet received a first issue has no "first
     * issue" anchor to measure a days/issues delay from, so the grace
     * period cannot apply — suspension is treated as immediately due.
     */
    public function isSuspensionDue(
        int $subscriptionId,
        FulfilmentSuspensionRule $rule,
        \DateTimeImmutable $now,
    ): bool {
        if ($rule->isImmediate()) {
            return true;
        }

        $firstDeliveredAt = $this->fulfilmentRepository->firstDeliveredAt($subscriptionId);

        if ($firstDeliveredAt === null) {
            return true;
        }

        if ($rule->type === FulfilmentSuspensionDelayType::DAYS) {
            $dueAt = $firstDeliveredAt->modify(sprintf('+%d days', $rule->value));

            return $dueAt <= $now;
        }

        // ISSUES: due once $value further issues have been delivered
        // on top of the first one.
        $delivered = $this->fulfilmentRepository->countDeliveredForSubscription($subscriptionId);

        return $delivered >= (1 + $rule->value);
    }
}
