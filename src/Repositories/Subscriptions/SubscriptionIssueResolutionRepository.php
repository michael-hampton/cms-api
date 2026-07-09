<?php

declare(strict_types=1);

namespace App\Repositories\Subscriptions;

use App\Enums\Subscriptions\BusinessDecisionCategory;
use App\Enums\Subscriptions\DecisionSource;
use App\Enums\Subscriptions\ReplacementLimitScope;
use App\Enums\Subscriptions\ReplacementResolution;
use App\Models\Model;
use App\Models\SubscriptionIssueResolution;
use App\Repositories\Repository;

class SubscriptionIssueResolutionRepository extends Repository
{
    private const OPEN_DECISIONS = ['replace', 'extend'];

    public function hasOpenResolution(int $subscriptionId, int $issueDeliveryId): bool
    {
        return SubscriptionIssueResolution::where('subscription_id', $subscriptionId)
            ->where('issue_delivery_id', $issueDeliveryId)
            ->where('category', BusinessDecisionCategory::REPLACEMENTS->value)
            ->whereIn('decision', self::OPEN_DECISIONS)
            ->exists();
    }

    /**
     * Count of prior resolutions of a given decision type (replace/extend),
     * counted over the window a policy's limit_scope specifies. Used by
     * ReplacementPolicyEvaluator (via IssueResolutionService) to enforce
     * max_replacements / max_extensions.
     *
     * Counts across all decision sources (policy, override, etc.) since
     * any recorded resolution consumed the entitlement regardless of how
     * it was authorised.
     */
    public function countForScope(
        ReplacementLimitScope $scope,
        int $subscriptionId,
        int $issueDeliveryId,
        int $memberId,
        ReplacementResolution $decision
    ): int {
        return match ($scope) {
            ReplacementLimitScope::PER_ISSUE => $this->countForIssue($subscriptionId, $issueDeliveryId, $decision),
            ReplacementLimitScope::PER_SUBSCRIPTION => $this->countForSubscription($subscriptionId, $decision),
            ReplacementLimitScope::PER_YEAR => $this->countForSubscriptionInYear($subscriptionId, $decision, (int) date('Y')),
            ReplacementLimitScope::LIFETIME => $this->countForMember($memberId, $decision),
            default => throw new \Exception('Unexpected match value'),
        };
    }

    /**
     * @deprecated Use countForScope() — kept only because it's simpler
     *   for callers/tests that don't care about scope (defaults to the
     *   pre-scope, per-subscription behaviour).
     */
    public function countDecisionsForSubscription(int $subscriptionId, ReplacementResolution $decision): int
    {
        return $this->countForSubscription($subscriptionId, $decision);
    }

    private function countForIssue(int $subscriptionId, int $issueDeliveryId, ReplacementResolution $decision): int
    {
        return SubscriptionIssueResolution::where('subscription_id', $subscriptionId)
            ->where('issue_delivery_id', $issueDeliveryId)
            ->where('category', BusinessDecisionCategory::REPLACEMENTS->value)
            ->where('decision', $decision->value)
            ->count();
    }

    private function countForSubscription(int $subscriptionId, ReplacementResolution $decision): int
    {
        return SubscriptionIssueResolution::where('subscription_id', $subscriptionId)
            ->where('category', BusinessDecisionCategory::REPLACEMENTS->value)
            ->where('decision', $decision->value)
            ->count();
    }

    private function countForSubscriptionInYear(int $subscriptionId, ReplacementResolution $decision, int $year): int
    {
        return SubscriptionIssueResolution::where('subscription_id', $subscriptionId)
            ->where('category', BusinessDecisionCategory::REPLACEMENTS->value)
            ->where('decision', $decision->value)
            ->where('created_at', '>=', "{$year}-01-01 00:00:00")
            ->where('created_at', '<=', "{$year}-12-31 23:59:59")
            ->count();
    }

    private function countForMember(int $memberId, ReplacementResolution $decision): int
    {
        return SubscriptionIssueResolution::where('category', BusinessDecisionCategory::REPLACEMENTS->value)
            ->where('decision', $decision->value)
            ->whereHas('subscription', function ($query) use ($memberId) {
                $query->where('member_id', $memberId);
            })
            ->count();
    }

    public function createReplacementResolution(
        int $siteId,
        int $subscriptionId,
        int $issueDeliveryId,
        ReplacementResolution $decision,
        string $reason,
        DecisionSource $decisionSource,
        int $createdBy,
        ?int $replacementPolicyId = null,
        ?int $fulfilmentReplacementId = null,
        ?int $extensionFulfilmentId = null,
        array $metadata = []
    ): Model {
        return $this->create([
            'site_id' => $siteId,
            'subscription_id' => $subscriptionId,
            'issue_delivery_id' => $issueDeliveryId,
            'category' => BusinessDecisionCategory::REPLACEMENTS->value,
            'decision' => $decision->value,
            'reason' => $reason,
            'decision_source' => $decisionSource->value,
            'replacement_policy_id' => $replacementPolicyId,
            'fulfilment_replacement_id' => $fulfilmentReplacementId,
            'extension_fulfilment_id' => $extensionFulfilmentId,
            'metadata' => $metadata,
            'created_by' => $createdBy,
        ]);
    }

    protected function getModelClass(): string
    {
        return SubscriptionIssueResolution::class;
    }
}