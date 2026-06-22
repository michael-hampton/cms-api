<?php

declare(strict_types=1);

namespace App\Repositories\Subscriptions;

use App\Framework\Support\Collection;
use App\Models\FulfilmentReplacement;
use App\Models\IssueDelivery;
use App\Models\SubscriptionIssueFulfilment;
use App\Models\Model;
use App\Repositories\Repository;

class FulfilmentReplacementRepository extends Repository
{
    private const OPEN_STATUSES = ['pending', 'queued', 'dispatched'];

    public function createReplacement(
        int $subscriptionId,
        int $issueId,
        string $reason,
        int $createdBy,
        string $status = 'pending',
    ): Model {
        return $this->create([
            'subscription_id' => $subscriptionId,
            'issue_delivery_id' => $issueId,
            'reason' => $reason,
            'created_by' => $createdBy,
            'status' => $status,
        ]);
    }

    public function updateStatus(int $replacementId, string $status): ?Model
    {
        return $this->update($replacementId, ['status' => $status]);
    }

    public function findBySubscription(int $subscriptionId): Collection
    {
        return FulfilmentReplacement::where('subscription_id', $subscriptionId)
            ->orderBy('id', 'desc')
            ->get();
    }

    public function issueExistsForSubscription(int $issueId, int $subscriptionId): bool
    {
        if (SubscriptionIssueFulfilment::where('issue_delivery_id', $issueId)
            ->where('subscription_id', $subscriptionId)
            ->exists()) {
            return true;
        }

        return IssueDelivery::where('id', $issueId)
            ->where('subscription_id', $subscriptionId)
            ->exists();
    }

    public function issueExistsForSubscriptionPlan(int $issueId, int $subscriptionPlanId): bool
    {
        return IssueDelivery::where('id', $issueId)
            ->where('subscription_plan_id', $subscriptionPlanId)
            ->exists();
    }

    public function issueDeliveryWasDispatched(
        int $issueId,
        ?int $subscriptionId = null
    ): bool {
        if ($subscriptionId !== null) {
            if (SubscriptionIssueFulfilment::where('issue_delivery_id', $issueId)
                ->where('subscription_id', $subscriptionId)
                ->whereNotNull('dispatched_at')
                ->exists()) {
                return true;
            }

            return IssueDelivery::where('id', $issueId)
                ->where('subscription_id', $subscriptionId)
                ->where('status', 'dispatched')
                ->exists();
        }

        return IssueDelivery::where('id', $issueId)
            ->where('status', 'dispatched')
            ->exists();
    }

    public function issueDeliveryWasDispatchedForSubscriptionPlan(
        int $issueId,
        int $subscriptionPlanId,
    ): bool {
        return IssueDelivery::where('id', $issueId)
            ->where('subscription_plan_id', $subscriptionPlanId)
            ->where('status', 'dispatched')
            ->exists();
    }

    public function issueDeliveryIsReplaceableForSubscriptionPlan(
        int $issueId,
        int $subscriptionPlanId,
    ): bool {
        return IssueDelivery::where('id', $issueId)
            ->where('subscription_plan_id', $subscriptionPlanId)
            ->where(function ($query): void {
                $query->where('status', 'dispatched')
                    ->orWhere('estimated_delivery_date', '<', date('Y-m-d H:i:s'));
            })
            ->exists();
    }

    public function hasOpenReplacement(int $subscriptionId, int $issueId): bool
    {
        return FulfilmentReplacement::where('subscription_id', $subscriptionId)
            ->where('issue_delivery_id', $issueId)
            ->whereIn('status', self::OPEN_STATUSES)
            ->exists();
    }

    public function findOpenReplacementsForIssues(
        int $subscriptionId,
        array $issueIds,
    ): Collection {
        if (empty($issueIds)) {
            return new Collection([]);
        }

        return FulfilmentReplacement::where('subscription_id', $subscriptionId)
            ->whereIn('issue_delivery_id', $issueIds)
            ->whereIn('status', self::OPEN_STATUSES)
            ->get();
    }

    protected function getModelClass(): string
    {
        return FulfilmentReplacement::class;
    }
}
