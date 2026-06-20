<?php

namespace App\Repositories\Subscriptions;

use App\Enums\Subscriptions\IssueDeliveredStatus;
use App\Framework\Support\Collection;
use App\Models\IssueDelivery;
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

    public function wasDispatchedForSubscription(int $subscriptionId, int $issueDeliveryId): bool
    {
        return IssuesDelivered::where('subscription_id', $subscriptionId)
            ->where('issue_delivery_id', $issueDeliveryId)
            ->whereNotNull('dispatched_at')
            ->exists();
    }

    public function getForSubscription(int $subscriptionId): Collection
    {
        return IssuesDelivered::where('subscription_id', $subscriptionId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getForSubscriptionAndIssues(int $subscriptionId, array $issueDeliveryIds): array
    {
        if (empty($issueDeliveryIds)) {
            return [];
        }

        $rows = IssuesDelivered::where('subscription_id', $subscriptionId)
            ->whereIn('issue_delivery_id', $issueDeliveryIds)
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $result[(int) $row->issue_delivery_id] = $row;
        }

        return $result;
    }

    public function getFutureForSubscription(int $subscriptionId): Collection
    {
        return IssuesDelivered::where('subscription_id', $subscriptionId)
            ->where('status', IssueDeliveredStatus::SCHEDULED->value)
            ->whereNull('dispatched_at')
            ->orderBy('scheduled_for', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    public function countFutureForSubscription(int $subscriptionId): int
    {
        return IssuesDelivered::where('subscription_id', $subscriptionId)
            ->where('status', IssueDeliveredStatus::SCHEDULED->value)
            ->whereNull('dispatched_at')
            ->count();
    }

    public function resolveFirstFutureIssueId(int $subscriptionId): ?int
    {
        $fulfilment = IssuesDelivered::where('subscription_id', $subscriptionId)
            ->where('status', IssueDeliveredStatus::SCHEDULED->value)
            ->whereNull('dispatched_at')
            ->orderBy('scheduled_for', 'asc')
            ->orderBy('id', 'asc')
            ->first();

        return $fulfilment ? (int) $fulfilment->issue_delivery_id : null;
    }

    public function supersedeFutureForSubscription(int $subscriptionId): int
    {
        $fulfilments = $this->getFutureForSubscription($subscriptionId);

        foreach ($fulfilments as $fulfilment) {
            $fulfilment->update([
                'status' => IssueDeliveredStatus::SUPERSEDED->value,
                'deferred_until' => null,
            ]);
        }

        return $fulfilments->count();
    }

    public function getScheduled(): Collection
    {
        return IssuesDelivered::scheduled()->get();
    }

    public function getFailedRetriable(int $maxAttempts = 3): Collection
    {
        return IssuesDelivered::canRetry($maxAttempts)->get();
    }

    public function createForSubscription(
        int $subscriptionId,
        int $issueDeliveryId,
        ?\DateTimeInterface $scheduledFor = null,
        ?\DateTimeInterface $deferredUntil = null
    ): IssuesDelivered {
        $existing = $this->findBySubscriptionAndSchedule($subscriptionId, $issueDeliveryId);

        if ($existing) {
            return $existing;
        }

        return $this->create([
            'subscription_id' => $subscriptionId,
            'issue_delivery_id' => $issueDeliveryId,
            'status' => IssueDeliveredStatus::SCHEDULED->value,
            'attempts' => 0,
            'scheduled_for' => $scheduledFor?->format('Y-m-d H:i:s'),
            'deferred_until' => $deferredUntil?->format('Y-m-d H:i:s'),
        ]);
    }

    public function createFromSchedule(int $subscriptionId, IssueDelivery $issue): IssuesDelivered
    {
        $scheduledFor = $issue->estimated_delivery_date ?? $issue->on_sale_date;

        return $this->createForSubscription(
            $subscriptionId,
            (int) $issue->id,
            $scheduledFor
        );
    }

    public function deferForSubscriptionAndIssues(
        int $subscriptionId,
        array $issueDeliveryIds,
        \DateTimeInterface $deferredUntil
    ): int {
        if (empty($issueDeliveryIds)) {
            return 0;
        }

        $fulfilments = IssuesDelivered::where('subscription_id', $subscriptionId)
            ->whereIn('issue_delivery_id', $issueDeliveryIds)
            ->where('status', IssueDeliveredStatus::SCHEDULED->value)
            ->whereNull('dispatched_at')
            ->get();

        foreach ($fulfilments as $fulfilment) {
            $fulfilment->deferUntil($deferredUntil);
        }

        return $fulfilments->count();
    }

    public function releaseDeferredForSubscription(int $subscriptionId): int
    {
        $fulfilments = IssuesDelivered::where('subscription_id', $subscriptionId)
            ->where('status', IssueDeliveredStatus::SCHEDULED->value)
            ->whereNull('dispatched_at')
            ->whereNotNull('deferred_until')
            ->get();

        foreach ($fulfilments as $fulfilment) {
            $fulfilment->releaseDeferral();
        }

        return $fulfilments->count();
    }

    public function getDispatchableForIssue(
        int $issueDeliveryId,
        \DateTimeInterface $date
    ): Collection {
        return IssuesDelivered::where('issue_delivery_id', $issueDeliveryId)
            ->where('status', IssueDeliveredStatus::SCHEDULED->value)
            ->get()
            ->filter(function (IssuesDelivered $fulfilment) use ($date) {
                return $fulfilment->canDispatchAt($date);
            })
            ->values();
    }

    public function getDispatchedSubscriptionIdsForIssue(int $issueDeliveryId): array
    {
        return IssuesDelivered::where('issue_delivery_id', $issueDeliveryId)
            ->where('status', IssueDeliveredStatus::SCHEDULED->value)
            ->whereNotNull('dispatched_at')
            ->get()
            ->pluck('subscription_id')
            ->map(function ($subscriptionId) {
                return (int) $subscriptionId;
            })
            ->toArray();
    }

    public function markDispatched(array $fulfilmentIds, \DateTimeInterface $date): void
    {
        if (empty($fulfilmentIds)) {
            return;
        }

        $fulfilments = IssuesDelivered::whereIn('id', $fulfilmentIds)->get();

        foreach ($fulfilments as $fulfilment) {
            $fulfilment->markAsDispatched($date);
        }
    }

    public function hasUndispatchedForIssue(int $issueDeliveryId): bool
    {
        return IssuesDelivered::where('issue_delivery_id', $issueDeliveryId)
            ->where('status', IssueDeliveredStatus::SCHEDULED->value)
            ->whereNull('dispatched_at')
            ->exists();
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
