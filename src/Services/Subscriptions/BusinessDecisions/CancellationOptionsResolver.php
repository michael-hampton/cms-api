<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\BusinessDecisions;

use App\DTO\Subscriptions\BusinessDecisions\CancellationReasonOptionData;
use App\DTO\Subscriptions\BusinessDecisions\ResolvedCancellationOptions;
use App\DTO\Subscriptions\SubscriptionOfferFilters;
use App\Enums\Subscriptions\BusinessDecisions\BusinessDecisionCategoryEnum;
use App\Models\BusinessDecision;
use App\Models\CancellationReasonPolicy;
use App\Repositories\Subscriptions\BusinessDecisions\CancellationReasonPolicyRepository;
use App\Repositories\Subscriptions\BusinessDecisions\CancellationReasonRepository;
use App\Services\Subscriptions\SubscriptionOfferSearchService;

/**
 * Resolves which BusinessDecision governs cancellations for a plan/site,
 * and the fully-resolved per-reason save options under it.
 *
 * Decision precedence (per ticket, "brand" = this codebase's Site — see
 * business_decision_assignments migration note) is delegated to
 * BusinessDecisionChainResolver:
 *   1. SubscriptionPlan's assignment ("product")
 *   2. Site's assignment ("brand")
 *   3. The global is_default decision for the category
 *   4. Configuration exception if none exist
 *
 * Field precedence within cancellation_reason_policies is resolved
 * independently per option field across the same chain — see the
 * "nullable inheritance" note on the cancellation_reason_policies
 * migration. Only decision levels that actually exist are consulted; a
 * missing assignment is simply skipped rather than contributing nulls.
 *
 * This class only resolves and calculates — it performs no writes.
 */
class CancellationOptionsResolver
{
    private const CATEGORY = BusinessDecisionCategoryEnum::CANCELLATIONS;

    /** Applied only if every level in the chain left a field null. */
    private const FIELD_DEFAULTS = [
        'show_save_actions' => false,
        'allow_discount' => false,
        'allow_offer_switch' => false,
        'allow_cancel' => true,
        'refund_max_percent' => 0,
        'marketing_consent' => false,
    ];

    public function __construct(
        private readonly BusinessDecisionChainResolver $chainResolver,
        private readonly CancellationReasonRepository $reasonRepository,
        private readonly CancellationReasonPolicyRepository $reasonPolicyRepository,
        private readonly SubscriptionOfferSearchService $offerSearchService,
    ) {
    }

    /**
     * @return array{decision: BusinessDecision, source: string, reasons: CancellationReasonOptionData[]}
     */
    public function resolveForPlan(int $planId, int $siteId): array
    {
        $chain = $this->chainResolver->resolveChain(self::CATEGORY, $planId, $siteId);
        $resolved = $this->chainResolver->resolveDecision($chain, self::CATEGORY, $planId, $siteId);

        $reasons = $this->reasonRepository->listActive();

        $reasonOptions = [];
        foreach ($reasons as $reason) {
            $options = $this->resolveOptionsForReason((int) $reason->id, $chain);

            $reasonOptions[] = new CancellationReasonOptionData(
                code: (string) $reason->code,
                label: (string) $reason->label,
                options: $options,
                availableOffers: $this->resolveAvailableOffers($planId, $options),
            );
        }

        return [
            'decision' => $resolved['decision'],
            'source' => $resolved['source'],
            'reasons' => $reasonOptions,
        ];
    }

    /**
     * Resolves options for one specific reason (by id) on a plan/site,
     * without computing offers or iterating every active reason — used
     * by SubscriptionCancellationService to validate/apply a single
     * chosen reason at cancel time.
     */
    public function resolveOptionsForReasonId(int $planId, int $siteId, int $cancellationReasonId): ResolvedCancellationOptions
    {
        $chain = $this->chainResolver->resolveChain(self::CATEGORY, $planId, $siteId);
        // Resolving the decision here (and discarding it) is deliberate:
        // it's what raises the "nothing configured" RuntimeException
        // when the chain is entirely empty, same as resolveForPlan().
        $this->chainResolver->resolveDecision($chain, self::CATEGORY, $planId, $siteId);

        return $this->resolveOptionsForReason($cancellationReasonId, $chain);
    }

    /**
     * @param array{product: ?BusinessDecision, brand: ?BusinessDecision, default: ?BusinessDecision} $chain
     */
    private function resolveOptionsForReason(int $cancellationReasonId, array $chain): ResolvedCancellationOptions
    {
        $policyRowsInPriorityOrder = array_filter([
            $this->findPolicyRow($chain['product'], $cancellationReasonId),
            $this->findPolicyRow($chain['brand'], $cancellationReasonId),
            $this->findPolicyRow($chain['default'], $cancellationReasonId),
        ]);

        $resolved = [];
        foreach (array_keys(self::FIELD_DEFAULTS) as $field) {
            $resolved[$field] = $this->firstNonNullField($policyRowsInPriorityOrder, $field)
                ?? self::FIELD_DEFAULTS[$field];
        }

        return new ResolvedCancellationOptions(
            showSaveActions: (bool) $resolved['show_save_actions'],
            allowDiscount: (bool) $resolved['allow_discount'],
            allowOfferSwitch: (bool) $resolved['allow_offer_switch'],
            allowCancel: (bool) $resolved['allow_cancel'],
            refundMaxPercent: (int) $resolved['refund_max_percent'],
            marketingConsent: (bool) $resolved['marketing_consent'],
        );
    }

    private function findPolicyRow(?BusinessDecision $decision, int $cancellationReasonId): ?CancellationReasonPolicy
    {
        if ($decision === null) {
            return null;
        }

        return $this->reasonPolicyRepository->findForDecisionAndReason((int) $decision->id, $cancellationReasonId);
    }

    /**
     * @param CancellationReasonPolicy[] $policyRowsInPriorityOrder
     */
    private function firstNonNullField(array $policyRowsInPriorityOrder, string $field): mixed
    {
        foreach ($policyRowsInPriorityOrder as $row) {
            if ($row->{$field} !== null) {
                return $row->{$field};
            }
        }

        return null;
    }

    /**
     * Only surfaces offers when the resolved options actually allow a
     * save via offer switch — an agent should never see offers for a
     * reason (e.g. bereavement) where show_save_actions is false.
     *
     * @return \App\DTO\Subscriptions\SubscriptionOfferData[]
     */
    private function resolveAvailableOffers(int $planId, ResolvedCancellationOptions $options): array
    {
        if (!$options->showSaveActions || !$options->allowOfferSwitch) {
            return [];
        }

        $result = $this->offerSearchService->search(new SubscriptionOfferFilters(
            planId: $planId,
            isActive: true,
        ));

        return $result['items'];
    }
}
