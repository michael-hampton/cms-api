<?php

declare(strict_types=1);

namespace App\Repositories\Subscriptions\BusinessDecisions;

use App\Enums\Subscriptions\BusinessDecisions\BusinessDecisionCategoryEnum;
use App\Models\BusinessDecisionAssignment;
use App\Repositories\Repository;

class BusinessDecisionAssignmentRepository extends Repository
{
    protected function getModelClass(): string
    {
        return BusinessDecisionAssignment::class;
    }

    /**
     * The decision assigned to a given assignable entity (a Site or a
     * SubscriptionPlan) for a category, or null if none is assigned —
     * the caller (CancellationOptionsResolver) falls back up the chain
     * on null.
     */
    public function findForAssignable(
        string $assignableType,
        int $assignableId,
        BusinessDecisionCategoryEnum $category,
    ): ?BusinessDecisionAssignment {
        return BusinessDecisionAssignment::where('assignable_type', $assignableType)
            ->where('assignable_id', $assignableId)
            ->where('category', $category->value)
            ->first();
    }

    /**
     * Creates or repoints the single assignment row for an assignable
     * entity + category (the unique index on
     * [assignable_type, assignable_id, category] means there is ever at
     * most one). `category` is denormalised from the decision here so it
     * stays in sync with whatever decision is assigned.
     */
    public function upsert(
        string $assignableType,
        int $assignableId,
        BusinessDecisionCategoryEnum $category,
        int $businessDecisionId,
    ): BusinessDecisionAssignment {
        $assignment = $this->findForAssignable($assignableType, $assignableId, $category)
            ?? new BusinessDecisionAssignment();

        $assignment->fill([
            'assignable_type' => $assignableType,
            'assignable_id' => $assignableId,
            'category' => $category->value,
            'business_decision_id' => $businessDecisionId,
        ]);
        $assignment->save();

        return $assignment;
    }
}
