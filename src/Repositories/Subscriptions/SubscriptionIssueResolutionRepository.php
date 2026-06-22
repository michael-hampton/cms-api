<?php

declare(strict_types=1);

namespace App\Repositories\Subscriptions;

use App\Enums\Subscriptions\BusinessDecisionCategory;
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

    public function createReplacementResolution(
        int $siteId,
        int $subscriptionId,
        int $issueDeliveryId,
        ReplacementResolution $decision,
        string $reason,
        bool $businessDecision,
        int $createdBy,
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
            'business_decision' => $businessDecision ? 1 : 0,
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
