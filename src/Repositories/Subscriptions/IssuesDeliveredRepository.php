<?php

namespace App\Repositories\Subscriptions;

use App\Enums\Subscriptions\IssueDeliveryStatus;
use App\Framework\Support\Collection;
use App\Models\IssuesDelivered;
use App\Repositories\Repository;

class IssuesDeliveredRepository extends Repository
{
    public function findBySubscriptionAndSchedule(int $subscriptionId, int $issueDeliveryId): ?IssuesDelivered
    {
        return IssuesDelivered::where('subscription_id', $subscriptionId)
            ->where('issue_delivery_id', $issueDeliveryId)
            ->first();
    }

    public function existsForSubscriptionAndSchedule(int $subscriptionId, int $issueDeliveryId): bool
    {
        return IssuesDelivered::where('subscription_id', $subscriptionId)
            ->where('issue_delivery_id', $issueDeliveryId)
            ->exists();
    }

    public function getForSubscription(int $subscriptionId): Collection
    {
        return IssuesDelivered::where('subscription_id', $subscriptionId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getScheduled(): Collection
    {
        return IssuesDelivered::scheduled()->get();
    }

    public function getFailedRetriable(int $maxAttempts = 3): Collection
    {
        return IssuesDelivered::canRetry($maxAttempts)->get();
    }

    public function createForSubscription(int $subscriptionId, int $issueDeliveryId): IssuesDelivered
    {
        return $this->create([
            'subscription_id' => $subscriptionId,
            'issue_delivery_id' => $issueDeliveryId,
            'status' => IssueDeliveryStatus::SCHEDULED->value,
            'attempts' => 0,
        ]);
    }

    public function findBySubscriptionAndDelivery(int $subscriptionId, int $issueDeliveryId): ?IssuesDelivered
    {
        return IssuesDelivered::where('subscription_id', $subscriptionId)
            ->where('issue_delivery_id', $issueDeliveryId)
            ->first();
    }

    protected function getModelClass(): string
    {
        return IssuesDelivered::class;
    }
}