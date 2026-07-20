<?php

declare(strict_types=1);

namespace App\Repositories\Subscriptions\BusinessDecisions;

use App\Models\RefundReasonPolicy;
use App\Repositories\Repository;

class RefundReasonPolicyRepository extends Repository
{
    protected function getModelClass(): string
    {
        return RefundReasonPolicy::class;
    }

    public function findAllForDecision(int $businessDecisionId): array
    {
        return RefundReasonPolicy::where('business_decision_id', $businessDecisionId)
            ->get()->keyBy('refund_reason_id')->all();
    }

    public function findForDecisionAndReason(int $businessDecisionId, int $refundReasonId): ?RefundReasonPolicy
    {
        return RefundReasonPolicy::where('business_decision_id', $businessDecisionId)
            ->where('refund_reason_id', $refundReasonId)
            ->first();
    }

    public function upsert(int $businessDecisionId, int $refundReasonId, array $fields): RefundReasonPolicy
    {
        $policy = $this->findForDecisionAndReason($businessDecisionId, $refundReasonId) ?? new RefundReasonPolicy();
        $policy->fill(array_merge([
            'business_decision_id' => $businessDecisionId,
            'refund_reason_id' => $refundReasonId,
        ], $fields));
        $policy->save();

        return $policy;
    }
}
