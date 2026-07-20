<?php

declare(strict_types=1);

namespace App\Repositories\Subscriptions\BusinessDecisions;

use App\Models\CancellationReasonPolicy;
use App\Repositories\Repository;

class CancellationReasonPolicyRepository extends Repository
{
    public function findAllForDecision(int $businessDecisionId)
    {
        return CancellationReasonPolicy::where('business_decision_id', $businessDecisionId)
            ->get()
            ->keyBy('cancellation_reason_id')
            ->all();
    }

    protected function getModelClass(): string
    {
        return CancellationReasonPolicy::class;
    }

    /**
     * The (possibly partially-null) options row for one decision + reason
     * pair, or null if that decision has no row at all for this reason
     * (distinct from a row existing with all-null fields — both cases
     * mean "fall back further" to CancellationOptionsResolver, but a
     * missing row can never contribute an override).
     */
    public function findForDecisionAndReason(int $businessDecisionId, int $cancellationReasonId): ?CancellationReasonPolicy
    {
        return CancellationReasonPolicy::where('business_decision_id', $businessDecisionId)
            ->where('cancellation_reason_id', $cancellationReasonId)
            ->first();
    }

    /**
     * Creates or updates the single row for a decision + reason pair
     * (unique index enforces at most one). Only the keys present in
     * $fields are written — omitted keys are left as-is on an existing
     * row (or null, i.e. "inherit", on a newly created one).
     */
    public function upsert(int $businessDecisionId, int $cancellationReasonId, array $fields): CancellationReasonPolicy
    {
        $policy = $this->findForDecisionAndReason($businessDecisionId, $cancellationReasonId)
            ?? new CancellationReasonPolicy();

        $policy->fill(array_merge(
            [
                'business_decision_id' => $businessDecisionId,
                'cancellation_reason_id' => $cancellationReasonId,
            ],
            $fields,
        ));
        $policy->save();

        return $policy;
    }
}
