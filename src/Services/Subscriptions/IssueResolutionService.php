<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\DTO\Subscriptions\PolicyContext;
use App\DTO\Subscriptions\ReplacementUsageStatistics;
use App\Enums\Subscriptions\DecisionSource;
use App\Enums\Subscriptions\ReplacementLimitScope;
use App\Enums\Subscriptions\ReplacementResolution;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\IssueDelivery;
use App\Models\Subscription;
use App\Models\SubscriptionIssueFulfilment;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\IssueDeliveryStockRepository;
use App\Repositories\Subscriptions\SubscriptionIssueResolutionRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\Contracts\ReplacementPolicyInterface;

/**
 * Orchestrates resolving a reported subscription issue as either a
 * replacement or a subscription extension.
 *
 * Flow (per ticket):
 *   resolve policy -> validate policy -> evaluate entitlement
 *   -> operational eligibility checks (replacement only)
 *   -> perform replacement or extension -> record resolution
 *
 * BEHAVIOUR CHANGE from the pre-strategy version: operational eligibility
 * (stock/dispatch-state/etc., via FulfilmentReplacementEligibilityService)
 * now runs *after* policy entitlement is decided, and only for REPLACE
 * requests — this is what the ticket's flow explicitly specifies. The
 * duplicate-open-resolution guard is kept as a universal check up front
 * for both decision types, since it's a correctness guard independent of
 * physical fulfilment mechanics, not an "operational eligibility" concern
 * the ticket scoped to replacements.
 *
 * This service does not decide *how* a policy is resolved (that's
 * ReplacementPolicyResolver) or *whether* a policy entitles the request
 * (that's the resolved ReplacementPolicyInterface itself). It only
 * sequences those collaborators and persists the outcome.
 */
class IssueResolutionService
{
    private SubscriptionRepository $subscriptionRepository;
    private FulfilmentReplacementEligibilityService $eligibilityService;
    private FulfilmentReplacementService $replacementService;
    private SubscriptionIssueExtensionService $extensionService;
    private IssueDeliveryStockRepository $stockRepository;
    private IssueDeliveryRepository $issueDeliveryRepository;
    private SubscriptionIssueResolutionRepository $resolutionRepository;
    private ReplacementPolicyResolver $policyResolver;

    public function __construct(
        SubscriptionRepository $subscriptionRepository,
        FulfilmentReplacementEligibilityService $eligibilityService,
        FulfilmentReplacementService $replacementService,
        SubscriptionIssueExtensionService $extensionService,
        IssueDeliveryStockRepository $stockRepository,
        IssueDeliveryRepository $issueDeliveryRepository,
        SubscriptionIssueResolutionRepository $resolutionRepository,
        ReplacementPolicyResolver $policyResolver
    ) {
        $this->subscriptionRepository = $subscriptionRepository;
        $this->eligibilityService = $eligibilityService;
        $this->replacementService = $replacementService;
        $this->extensionService = $extensionService;
        $this->stockRepository = $stockRepository;
        $this->issueDeliveryRepository = $issueDeliveryRepository;
        $this->resolutionRepository = $resolutionRepository;
        $this->policyResolver = $policyResolver;
    }

    /**
     * @param bool $businessDecision Agent is requesting to override a
     *   policy block. Only takes effect if the policy actually blocks the
     *   request — it is not a way to bypass operational checks (stock,
     *   dispatch state, duplicates), only entitlement. When it takes
     *   effect, the GoodwillPolicy is substituted for the plan's own
     *   policy and decision_source is recorded as BUSINESS_OVERRIDE.
     */
    public function resolve(
        int $subscriptionId,
        int $issueId,
        ReplacementResolution $decision,
        string $reason,
        int $agentId,
        int $siteId,
        bool $businessDecision = false
    ): object {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \InvalidArgumentException('reason is required.');
        }

        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription) {
            throw new \InvalidArgumentException('Subscription not found.');
        }

        if ($this->resolutionRepository->hasOpenResolution($subscriptionId, $issueId)) {
            throw new \InvalidArgumentException('A resolution is already recorded for this issue.');
        }

        $issueDelivery = $this->issueDeliveryRepository->find($issueId);

        if (!$issueDelivery) {
            throw new \InvalidArgumentException('Issue not found.');
        }

        // ASSUMPTION: Subscription exposes a plan() accessor returning the
        // related SubscriptionPlan, matching this codebase's relation
        // convention seen elsewhere (e.g. SubscriptionPlan::replacementPolicy()).
        // Subscription.php wasn't available to confirm directly.
        $plan = $subscription->plan();

        if (!$plan instanceof SubscriptionPlan) {
            throw new \InvalidArgumentException('Subscription plan not found.');
        }

        $policy = $this->policyResolver->resolveForSubscription($subscriptionId, $siteId);

        $decided = $this->resolvePolicyDecision(
            $subscription,
            $plan,
            $issueDelivery,
            $decision,
            $agentId,
            $siteId,
            $businessDecision,
            $policy
        );

        if ($decision === ReplacementResolution::REPLACE) {
            $eligibility = $this->eligibilityService->canRequest($subscriptionId, $issueId, $siteId);

            if (!$eligibility->canRequestReplacement) {
                throw new \InvalidArgumentException($eligibility->blockedReason);
            }

            return $this->replace(
                $subscriptionId,
                $issueId,
                $reason,
                $agentId,
                $siteId,
                $decided->decisionSource,
                $decided->policy
            );
        }

        return $this->extend(
            $subscription,
            $issueId,
            $reason,
            $agentId,
            $siteId,
            $decided->decisionSource,
            $decided->policy
        );
    }

    /**
     * Validates and evaluates the resolved policy against the request.
     * Falls back to the GoodwillPolicy (decision_source = BUSINESS_OVERRIDE)
     * when the policy denies the request and the agent supplied an
     * override. Throws otherwise.
     */
    private function resolvePolicyDecision(
        Subscription $subscription,
        SubscriptionPlan $plan,
        IssueDelivery $issueDelivery,
        ReplacementResolution $decision,
        int $agentId,
        int $siteId,
        bool $businessDecision,
        ReplacementPolicyInterface $policy
    ): object {
        $context = $this->buildContext($subscription, $plan, $issueDelivery, $decision, $agentId, $siteId, $policy);

        $validation = $policy->validate($context);

        if (!$validation->valid) {
            throw new \InvalidArgumentException(
                $validation->reason ?? 'This replacement policy is not valid for the request.'
            );
        }

        $evaluation = $policy->evaluate($context);

        if ($evaluation->isAllowed()) {
            return (object) ['policy' => $policy, 'decisionSource' => DecisionSource::POLICY];
        }

        if (!$businessDecision) {
            throw new \InvalidArgumentException(
                $evaluation->blockedReason ?? 'This request is not permitted by the assigned replacement policy.'
            );
        }

        $goodwillPolicy = $this->policyResolver->resolveGoodwill($siteId);
        $goodwillContext = $this->buildContext(
            $subscription,
            $plan,
            $issueDelivery,
            $decision,
            $agentId,
            $siteId,
            $goodwillPolicy
        );

        $goodwillValidation = $goodwillPolicy->validate($goodwillContext);

        if (!$goodwillValidation->valid) {
            throw new \InvalidArgumentException(
                $goodwillValidation->reason ?? 'The goodwill override policy is not valid for this request.'
            );
        }

        $goodwillEvaluation = $goodwillPolicy->evaluate($goodwillContext);

        if (!$goodwillEvaluation->isAllowed()) {
            throw new \InvalidArgumentException(
                $goodwillEvaluation->blockedReason ?? 'This request could not be granted, even as a business override.'
            );
        }

        return (object) ['policy' => $goodwillPolicy, 'decisionSource' => DecisionSource::BUSINESS_OVERRIDE];
    }

    private function buildContext(
        Subscription $subscription,
        SubscriptionPlan $plan,
        IssueDelivery $issueDelivery,
        ReplacementResolution $decision,
        int $agentId,
        int $siteId,
        ReplacementPolicyInterface $policy
    ): PolicyContext {
        $subscriptionId = (int) $subscription->id;
        $memberId = (int) $subscription->member_id;
        $issueId = (int) $issueDelivery->id;

        $usage = new ReplacementUsageStatistics(
            $this->resolutionRepository->countForScope(
                $policy->replacementLimitScope(),
                $subscriptionId,
                $issueId,
                $memberId,
                ReplacementResolution::REPLACE
            ),
            $this->resolutionRepository->countForScope(
                $policy->extensionLimitScope(),
                $subscriptionId,
                $issueId,
                $memberId,
                ReplacementResolution::EXTEND
            ),
        );

        $currentResolutionCount = $this->resolutionRepository->countForScope(
            ReplacementLimitScope::PER_ISSUE,
            $subscriptionId,
            $issueId,
            $memberId,
            $decision
        );

        return new PolicyContext(
            $subscription,
            $plan,
            $issueDelivery,
            $decision,
            $usage,
            $agentId,
            $siteId,
            $currentResolutionCount
        );
    }

    private function replace(
        int $subscriptionId,
        int $issueId,
        string $reason,
        int $agentId,
        int $siteId,
        DecisionSource $decisionSource,
        ReplacementPolicyInterface $policy
    ): object {
        return Database::runTransaction(function () use (
            $subscriptionId,
            $issueId,
            $reason,
            $agentId,
            $siteId,
            $decisionSource,
            $policy
        ) {
            // ASSUMPTION: stock is now checked unconditionally. The old
            // require_stock config column is dropped by this migration
            // (replacement_policies keeps only policy_class per the
            // ticket's schema), and every policy that can reach this
            // point (Standard/Premium/Corporate-post-approval/Goodwill)
            // represents a physical replacement — NoReplacementPolicy and
            // DigitalOnlyPolicy deny REPLACE outright and never get here.
            // If a future policy needs a stock-free physical replacement
            // path, this will need a dedicated interface method.
            if (!$this->stockRepository->decrementIfAvailable($issueId)) {
                throw new \InvalidArgumentException('This issue has no stock available for replacement.');
            }

            $replacement = $this->replacementService->requestReplacement(
                $subscriptionId,
                $issueId,
                $reason,
                $agentId,
                $siteId
            );

            $resolution = $this->resolutionRepository->createReplacementResolution(
                $siteId,
                $subscriptionId,
                $issueId,
                ReplacementResolution::REPLACE,
                $reason,
                $decisionSource,
                $agentId,
                $policy->id(),
                (int) $replacement->id,
                null,
                ['stock_decremented' => true]
            );

            Logger::info('Issue resolved with replacement copy', [
                'subscription_id' => $subscriptionId,
                'issue_id' => $issueId,
                'replacement_id' => $replacement->id,
                'resolution_id' => $resolution->id,
                'policy_id' => $policy->id(),
                'policy_name' => $policy->name(),
                'decision_source' => $decisionSource->value,
            ]);

            return (object) [
                'decision' => ReplacementResolution::REPLACE->value,
                'replacement' => $replacement,
                'resolution' => $resolution,
            ];
        });
    }

    private function extend(
        Subscription $subscription,
        int $issueId,
        string $reason,
        int $agentId,
        int $siteId,
        DecisionSource $decisionSource,
        ReplacementPolicyInterface $policy
    ): object {
        // NOTE: not wrapped in Database::runTransaction(), matching the
        // pre-existing behaviour of this method. It performs 2+ writes
        // (extendByOneIssue()'s internal updates, plus the resolution
        // record below) and per the team's own contract should be
        // transactional — flagging as a pre-existing gap rather than
        // fixing it here, since extendByOneIssue() itself isn't
        // transaction-aware and wrapping only the outer call would give
        // a false sense of atomicity. Worth a follow-up ticket.
        $subscriptionId = (int) $subscription->id;

        $fulfilment = $this->extensionService->extendByOneIssue($subscription);

        $resolution = $this->resolutionRepository->createReplacementResolution(
            $siteId,
            $subscriptionId,
            $issueId,
            ReplacementResolution::EXTEND,
            $reason,
            $decisionSource,
            $agentId,
            $policy->id(),
            null,
            (int) $fulfilment->id,
            $this->buildExtensionMetadata($fulfilment)
        );

        Logger::info('Issue resolved with subscription extension', [
            'subscription_id' => $subscriptionId,
            'issue_id' => $issueId,
            'extension_fulfilment_id' => $fulfilment->id,
            'resolution_id' => $resolution->id,
            'policy_id' => $policy->id(),
            'policy_name' => $policy->name(),
            'decision_source' => $decisionSource->value,
        ]);

        return (object) [
            'decision' => ReplacementResolution::EXTEND->value,
            'extension_fulfilment' => $fulfilment,
            'resolution' => $resolution,
        ];
    }

    private function buildExtensionMetadata(SubscriptionIssueFulfilment $fulfilment): array
    {
        return [
            'extra_issue_delivery_id' => (int) $fulfilment->issue_delivery_id,
            'scheduled_for' => $fulfilment->scheduled_for instanceof \DateTimeInterface
                ? $fulfilment->scheduled_for->format('Y-m-d H:i:s')
                : null,
        ];
    }
}
