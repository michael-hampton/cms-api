<?php

namespace App\Services\Subscriptions;

use App\Repositories\Subscriptions\SubscriptionPlanPricingRepository;

/**
 * Enforces domain invariants on SubscriptionPlanPricing collections.
 *
 * Rules:
 *   1. A plan may have at most one active default price (is_default = true).
 *   2. No two active prices on the same plan may share the same sort_order.
 *
 * This is a pure domain collaborator. It has one infrastructure dependency
 * (the repository) solely to query existing siblings — it performs no writes.
 *
 * Both AddPlanPriceAction and ReplacePlanPriceAction must run these guards
 * before persisting a new pricing row.
 */
class PlanPricingDomainGuard
{
    public function __construct(
        private readonly SubscriptionPlanPricingRepository $pricingRepository,
    )
    {
    }

    /**
     * Assert that marking the new row as default would not create a second
     * default price on the plan.
     *
     * @param int $planId The plan the new price belongs to.
     * @param bool $isDefault Whether the new price claims default status.
     * @param int|null $excludePricingId When replacing, pass the ID of the row
     *                                   being deactivated so it is not counted.
     *
     * @throws \DomainException
     */
    public function assertNoDefaultConflict(
        int  $planId,
        bool $isDefault,
        ?int $excludePricingId = null
    ): void
    {
        if (!$isDefault) {
            return;
        }

        $existingDefault = $this->pricingRepository->findActiveDefaultForPlan($planId, $excludePricingId);

        if ($existingDefault !== null) {
            throw new \DomainException(
                "Plan {$planId} already has an active default price (ID {$existingDefault->id}). "
                . 'Deactivate or replace it before setting a new default.'
            );
        }
    }

    /**
     * Assert that the new sort_order is unique among active prices for the plan.
     *
     * @param int $planId
     * @param int $sortOrder
     * @param int|null $excludePricingId ID of the row being replaced (excluded from check).
     *
     * @throws \DomainException
     */
    public function assertUniqueSortOrder(
        int  $planId,
        int  $sortOrder,
        ?int $excludePricingId = null
    ): void
    {
        $conflict = $this->pricingRepository->findActiveBySortOrder($planId, $sortOrder, $excludePricingId);

        if ($conflict !== null) {
            throw new \DomainException(
                "Plan {$planId} already has an active price at sort_order {$sortOrder} (ID {$conflict->id}). "
                . 'Use a unique sort_order for each price tier.'
            );
        }
    }
}