<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\BusinessDecisions;

use App\Enums\Subscriptions\BusinessDecisions\BusinessDecisionCategoryEnum;
use App\Models\BusinessDecision;
use App\Models\Site;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\BusinessDecisions\BusinessDecisionAssignmentRepository;
use App\Repositories\Subscriptions\BusinessDecisions\BusinessDecisionRepository;
use RuntimeException;

/**
 * Resolves which BusinessDecision governs a given category for a
 * plan/site — the product ("product") -> site ("brand") -> global
 * default chain shared by every category-specific resolver
 * (CancellationOptionsResolver, SuspensionOptionsResolver, and any
 * future FULFILMENT/RENEWALS resolver). Extracted because this lookup
 * has an independent reason to change (how decisions are assigned)
 * from how any one category interprets its resolved fields.
 */
class BusinessDecisionChainResolver
{
    public function __construct(
        private readonly BusinessDecisionRepository $decisionRepository,
        private readonly BusinessDecisionAssignmentRepository $assignmentRepository,
    ) {
    }

    /**
     * @return array{product: ?BusinessDecision, brand: ?BusinessDecision, default: ?BusinessDecision}
     */
    public function resolveChain(BusinessDecisionCategoryEnum $category, int $planId, int $siteId): array
    {
        $productAssignment = $this->assignmentRepository->findForAssignable(SubscriptionPlan::class, $planId, $category);
        $brandAssignment = $this->assignmentRepository->findForAssignable(Site::class, $siteId, $category);

        return [
            'product' => $this->activeDecision($productAssignment?->business_decision_id),
            'brand' => $this->activeDecision($brandAssignment?->business_decision_id),
            'default' => $this->decisionRepository->findDefault($category),
        ];
    }

    /**
     * @param array{product: ?BusinessDecision, brand: ?BusinessDecision, default: ?BusinessDecision} $chain
     * @return array{decision: BusinessDecision, source: string}
     */
    public function resolveDecision(array $chain, BusinessDecisionCategoryEnum $category, int $planId, int $siteId): array
    {
        $decision = $chain['product'] ?? $chain['brand'] ?? $chain['default'];

        if ($decision === null) {
            throw new RuntimeException(
                "No {$category->value} Business Decision is configured for plan #{$planId} (site #{$siteId}) and no global default exists."
            );
        }

        $source = match (true) {
            $chain['product'] !== null => 'product',
            $chain['brand'] !== null => 'brand',
            default => 'default',
        };

        return ['decision' => $decision, 'source' => $source];
    }

    private function activeDecision(?int $decisionId): ?BusinessDecision
    {
        if ($decisionId === null) {
            return null;
        }

        return $this->decisionRepository->findActive($decisionId);
    }
}
