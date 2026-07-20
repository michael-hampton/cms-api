<?php

declare(strict_types=1);

namespace App\Repositories\Subscriptions\BusinessDecisions;

use App\Models\SuspensionPolicy;
use App\Repositories\Repository;

class SuspensionPolicyRepository extends Repository
{
    protected function getModelClass(): string
    {
        return SuspensionPolicy::class;
    }

    public function findForDecision(int $businessDecisionId): ?SuspensionPolicy
    {
        return SuspensionPolicy::where('business_decision_id', $businessDecisionId)->first();
    }

    /** Admin upsert — same one-row-per-decision shape as CancellationReasonPolicy. */
    public function upsert(int $businessDecisionId, array $fields): SuspensionPolicy
    {
        $policy = $this->findForDecision($businessDecisionId) ?? new SuspensionPolicy();

        $policy->fill(array_merge(['business_decision_id' => $businessDecisionId], $fields));
        $policy->save();

        return $policy;
    }
}
