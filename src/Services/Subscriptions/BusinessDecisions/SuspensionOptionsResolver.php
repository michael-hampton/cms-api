<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\BusinessDecisions;

use App\DTO\Subscriptions\BusinessDecisions\ResolvedSuspensionOptions;
use App\Enums\Subscriptions\BusinessDecisions\BusinessDecisionCategoryEnum;
use App\Repositories\Subscriptions\BusinessDecisions\SuspensionPolicyRepository;

/**
 * Resolves whether suspension is allowed for a plan/site, and whether a
 * note is required — the suspension-governance counterpart to
 * CancellationOptionsResolver, sharing the same product -> brand ->
 * default chain via BusinessDecisionChainResolver but with its own,
 * much smaller field set (see suspension_policies migration for why
 * there's no per-reason catalogue here).
 *
 * Left entirely unconfigured, both fields fall back to their current
 * SuspendSubscriptionAction behaviour (always allowed, note mandatory)
 * so introducing this governance layer doesn't change anything for
 * sites that haven't opted in.
 */
class SuspensionOptionsResolver
{
    private const CATEGORY = BusinessDecisionCategoryEnum::SUSPENSIONS;

    private const FIELD_DEFAULTS = [
        'allow_suspend' => true,
        'requires_note' => true,
    ];

    public function __construct(
        private readonly BusinessDecisionChainResolver $chainResolver,
        private readonly SuspensionPolicyRepository $suspensionPolicyRepository,
    ) {
    }

    /**
     * Unlike cancellations, suspension governance is optional — if
     * nothing has been configured for this category at all (no
     * assignments and no global default), that simply means "use the
     * existing unconfigured behaviour" rather than a configuration
     * error, since suspension is an internal enforcement action, not a
     * customer-facing flow that requires an admin to have set anything
     * up first.
     */
    public function resolveForPlan(int $planId, int $siteId): ResolvedSuspensionOptions
    {
        $chain = $this->chainResolver->resolveChain(self::CATEGORY, $planId, $siteId);

        $policyRowsInPriorityOrder = array_filter([
            $this->findPolicyRow($chain['product']?->id),
            $this->findPolicyRow($chain['brand']?->id),
            $this->findPolicyRow($chain['default']?->id),
        ]);

        $resolved = [];
        foreach (array_keys(self::FIELD_DEFAULTS) as $field) {
            $resolved[$field] = $this->firstNonNullField($policyRowsInPriorityOrder, $field)
                ?? self::FIELD_DEFAULTS[$field];
        }

        return new ResolvedSuspensionOptions(
            allowSuspend: (bool) $resolved['allow_suspend'],
            requiresNote: (bool) $resolved['requires_note'],
        );
    }

    private function findPolicyRow(?int $decisionId)
    {
        if ($decisionId === null) {
            return null;
        }

        return $this->suspensionPolicyRepository->findForDecision($decisionId);
    }

    private function firstNonNullField(array $policyRowsInPriorityOrder, string $field): mixed
    {
        foreach ($policyRowsInPriorityOrder as $row) {
            if ($row->{$field} !== null) {
                return $row->{$field};
            }
        }

        return null;
    }
}
