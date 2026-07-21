<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\BusinessDecisions;

use App\DTO\Subscriptions\BusinessDecisions\RefundReasonOptionData;
use App\DTO\Subscriptions\BusinessDecisions\ResolvedRefundOptions;
use App\Enums\Subscriptions\BusinessDecisions\BusinessDecisionCategoryEnum;
use App\Models\BusinessDecision;
use App\Models\RefundReasonPolicy;
use App\Repositories\Subscriptions\BusinessDecisions\RefundReasonPolicyRepository;
use App\Repositories\Subscriptions\BusinessDecisions\RefundReasonRepository;

class RefundOptionsResolver
{
    private const CATEGORY = BusinessDecisionCategoryEnum::REFUNDS;

    private const FIELD_DEFAULTS = [
        'allow_full' => true,
        'allow_pro_rated' => true,
        'allow_manual' => true,
        'allow_cancel_at_period_end' => true,
        'allow_cancel_immediately_no_refund' => true,
        'refund_max_percent' => 100,
        'manager_approval_threshold_percent' => null,
        'default_notify_customer' => true,
        'requires_internal_notes' => false,
    ];

    public function __construct(
        private readonly BusinessDecisionChainResolver $chainResolver,
        private readonly RefundReasonRepository $reasonRepository,
        private readonly RefundReasonPolicyRepository $policyRepository,
    ) {
    }

    /** @return array{decision: BusinessDecision, source: string, reasons: RefundReasonOptionData[]} */
    public function resolveForPlan(int $planId, int $siteId): array
    {
        $chain = $this->chainResolver->resolveChain(self::CATEGORY, $planId, $siteId);
        $resolved = $this->chainResolver->resolveDecision($chain, self::CATEGORY, $planId, $siteId);
        $reasons = [];

        foreach ($this->reasonRepository->listActive() as $reason) {
            $reasons[] = new RefundReasonOptionData(
                id: (int) $reason->id,
                code: (string) $reason->code,
                label: (string) $reason->label,
                requiresNote: (bool) $reason->requires_note,
                options: $this->resolveOptionsForReason((int) $reason->id, $chain),
            );
        }

        return [
            'decision' => $resolved['decision'],
            'source' => $resolved['source'],
            'reasons' => $reasons
        ];
    }

    public function resolveOptionsForReasonId(int $planId, int $siteId, int $refundReasonId): ResolvedRefundOptions
    {
        $chain = $this->chainResolver->resolveChain(self::CATEGORY, $planId, $siteId);
        $this->chainResolver->resolveDecision($chain, self::CATEGORY, $planId, $siteId);

        return $this->resolveOptionsForReason($refundReasonId, $chain);
    }

    /** @param array{product: ?BusinessDecision, brand: ?BusinessDecision, default: ?BusinessDecision} $chain */
    private function resolveOptionsForReason(int $refundReasonId, array $chain): ResolvedRefundOptions
    {
        $rows = array_filter([
            $this->findPolicy($chain['product'], $refundReasonId),
            $this->findPolicy($chain['brand'], $refundReasonId),
            $this->findPolicy($chain['default'], $refundReasonId),
        ]);
        $resolved = [];

        foreach (self::FIELD_DEFAULTS as $field => $default) {
            $resolved[$field] = $this->firstNonNullField($rows, $field) ?? $default;
        }

        return new ResolvedRefundOptions(
            (bool) $resolved['allow_full'],
            (bool) $resolved['allow_pro_rated'],
            (bool) $resolved['allow_manual'],
            (bool) $resolved['allow_cancel_at_period_end'],
            (bool) $resolved['allow_cancel_immediately_no_refund'],
            (int) $resolved['refund_max_percent'],
            $resolved['manager_approval_threshold_percent'] === null ? null : (int) $resolved['manager_approval_threshold_percent'],
            (bool) $resolved['default_notify_customer'],
            (bool) $resolved['requires_internal_notes'],
        );
    }

    private function findPolicy(?BusinessDecision $decision, int $reasonId): ?RefundReasonPolicy
    {
        return $decision === null ? null : $this->policyRepository->findForDecisionAndReason((int) $decision->id, $reasonId);
    }

    /** @param RefundReasonPolicy[] $rows */
    private function firstNonNullField(array $rows, string $field): mixed
    {
        foreach ($rows as $row) {
            if ($row->{$field} !== null) {
                return $row->{$field};
            }
        }

        return null;
    }
}
