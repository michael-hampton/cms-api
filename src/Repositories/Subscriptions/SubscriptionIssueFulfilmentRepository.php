<?php

namespace App\Repositories\Subscriptions;

use App\Enums\Subscriptions\SubscriptionIssueFulfilmentStatus;
use App\Framework\Support\Collection;
use App\Models\IssueDelivery;
use App\Models\SubscriptionIssueFulfilment;
use App\Repositories\Repository;

class SubscriptionIssueFulfilmentRepository extends Repository
{
    public function findBySubscriptionAndSchedule(int $subscriptionId, int $issueDeliveryId): ?SubscriptionIssueFulfilment
    {
        return SubscriptionIssueFulfilment::where('subscription_id', $subscriptionId)
            ->where('issue_delivery_id', $issueDeliveryId)
            ->first();
    }

    public function existsForSubscriptionAndSchedule(int $subscriptionId, int $issueDeliveryId): bool
    {
        return SubscriptionIssueFulfilment::where('subscription_id', $subscriptionId)
            ->where('issue_delivery_id', $issueDeliveryId)
            ->exists();
    }

    public function wasDispatchedForSubscription(int $subscriptionId, int $issueDeliveryId): bool
    {
        return SubscriptionIssueFulfilment::where('subscription_id', $subscriptionId)
            ->where('issue_delivery_id', $issueDeliveryId)
            ->whereNotNull('dispatched_at')
            ->exists();
    }

    public function getForSubscription(int $subscriptionId): Collection
    {
        return SubscriptionIssueFulfilment::where('subscription_id', $subscriptionId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getForSubscriptionAndIssues(int $subscriptionId, array $issueDeliveryIds): array
    {
        if (empty($issueDeliveryIds)) {
            return [];
        }

        $rows = SubscriptionIssueFulfilment::where('subscription_id', $subscriptionId)
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
        return SubscriptionIssueFulfilment::where('subscription_id', $subscriptionId)
            ->where('status', SubscriptionIssueFulfilmentStatus::SCHEDULED->value)
            ->whereNull('dispatched_at')
            ->orderBy('scheduled_for', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    public function countFutureForSubscription(int $subscriptionId): int
    {
        return SubscriptionIssueFulfilment::where('subscription_id', $subscriptionId)
            ->where('status', SubscriptionIssueFulfilmentStatus::SCHEDULED->value)
            ->whereNull('dispatched_at')
            ->count();
    }

    public function resolveFirstFutureIssueId(int $subscriptionId): ?int
    {
        $fulfilment = SubscriptionIssueFulfilment::where('subscription_id', $subscriptionId)
            ->where('status', SubscriptionIssueFulfilmentStatus::SCHEDULED->value)
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
                'status' => SubscriptionIssueFulfilmentStatus::SUPERSEDED->value,
                'deferred_until' => null,
            ]);
        }

        return $fulfilments->count();
    }

    public function getScheduled(): Collection
    {
        return SubscriptionIssueFulfilment::scheduled()->get();
    }

    public function getFailedRetriable(int $maxAttempts = 3): Collection
    {
        return SubscriptionIssueFulfilment::retriable($maxAttempts)->get();
    }

    public function createForSubscription(
        int $subscriptionId,
        int $issueDeliveryId,
        ?\DateTimeInterface $scheduledFor = null,
        ?\DateTimeInterface $deferredUntil = null
    ): SubscriptionIssueFulfilment {
        $existing = $this->findBySubscriptionAndSchedule($subscriptionId, $issueDeliveryId);

        if ($existing) {
            return $this->refreshExistingFulfilment($existing, $scheduledFor, $deferredUntil);
        }

        try {
            return $this->create([
                'subscription_id' => $subscriptionId,
                'issue_delivery_id' => $issueDeliveryId,
                'status' => SubscriptionIssueFulfilmentStatus::SCHEDULED->value,
                'attempts' => 0,
                'scheduled_for' => $scheduledFor?->format('Y-m-d H:i:s'),
                'deferred_until' => $deferredUntil?->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $exception) {
            $existing = $this->findBySubscriptionAndSchedule($subscriptionId, $issueDeliveryId);

            if ($existing) {
                return $this->refreshExistingFulfilment($existing, $scheduledFor, $deferredUntil);
            }

            throw $exception;
        }
    }

    public function createFromSchedule(int $subscriptionId, IssueDelivery $issue): SubscriptionIssueFulfilment
    {
        $scheduledFor = $issue->estimated_delivery_date ?? $issue->on_sale_date;

        return $this->createForSubscription(
            $subscriptionId,
            (int) $issue->id,
            $scheduledFor
        );
    }

    public function claimForDispatch(array $fulfilmentIds, \DateTimeInterface $date): array
    {
        $claimedIds = [];
        $dispatchDate = $date->format('Y-m-d H:i:s');

        foreach (array_unique(array_map('intval', $fulfilmentIds)) as $fulfilmentId) {
            $updated = SubscriptionIssueFulfilment::where('id', $fulfilmentId)
                ->where('status', SubscriptionIssueFulfilmentStatus::SCHEDULED->value)
                ->whereNull('dispatched_at')
                ->where(function ($query) use ($dispatchDate) {
                    $query->whereNull('scheduled_for')
                        ->orWhere('scheduled_for', '<=', $dispatchDate);
                })
                ->where(function ($query) use ($dispatchDate) {
                    $query->whereNull('deferred_until')
                        ->orWhere('deferred_until', '<=', $dispatchDate);
                })
                ->update(['dispatched_at' => $dispatchDate]);

            if ((int) $updated === 1) {
                $claimedIds[] = $fulfilmentId;
            }
        }

        return $claimedIds;
    }

    public function releaseDispatchClaims(array $fulfilmentIds): int
    {
        if (empty($fulfilmentIds)) {
            return 0;
        }

        return SubscriptionIssueFulfilment::whereIn('id', array_unique(array_map('intval', $fulfilmentIds)))
            ->where('status', SubscriptionIssueFulfilmentStatus::SCHEDULED->value)
            ->whereNotNull('dispatched_at')
            ->update(['dispatched_at' => null]);
    }

    public function deferForSubscriptionAndIssues(
        int $subscriptionId,
        array $issueDeliveryIds,
        \DateTimeInterface $deferredUntil
    ): int {
        if (empty($issueDeliveryIds)) {
            return 0;
        }

        $fulfilments = SubscriptionIssueFulfilment::where('subscription_id', $subscriptionId)
            ->whereIn('issue_delivery_id', $issueDeliveryIds)
            ->where('status', SubscriptionIssueFulfilmentStatus::SCHEDULED->value)
            ->whereNull('dispatched_at')
            ->get();

        foreach ($fulfilments as $fulfilment) {
            $fulfilment->deferUntil($deferredUntil);
        }

        return $fulfilments->count();
    }

    public function releaseDeferredForSubscription(int $subscriptionId): int
    {
        $fulfilments = SubscriptionIssueFulfilment::where('subscription_id', $subscriptionId)
            ->where('status', SubscriptionIssueFulfilmentStatus::SCHEDULED->value)
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
        return SubscriptionIssueFulfilment::where('issue_delivery_id', $issueDeliveryId)
            ->where('status', SubscriptionIssueFulfilmentStatus::SCHEDULED->value)
            ->get()
            ->filter(function (SubscriptionIssueFulfilment $fulfilment) use ($date) {
                return $fulfilment->canDispatchAt($date);
            })
            ->values();
    }

    public function getDispatchedSubscriptionIdsForIssue(int $issueDeliveryId): array
    {
        return SubscriptionIssueFulfilment::where('issue_delivery_id', $issueDeliveryId)
            ->where('status', SubscriptionIssueFulfilmentStatus::SCHEDULED->value)
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
        $this->claimForDispatch($fulfilmentIds, $date);
    }

    public function hasUndispatchedForIssue(int $issueDeliveryId): bool
    {
        return SubscriptionIssueFulfilment::where('issue_delivery_id', $issueDeliveryId)
            ->where('status', SubscriptionIssueFulfilmentStatus::SCHEDULED->value)
            ->whereNull('dispatched_at')
            ->exists();
    }

    public function findBySubscriptionAndDelivery(int $subscriptionId, int $issueDeliveryId): ?SubscriptionIssueFulfilment
    {
        return $this->findBySubscriptionAndSchedule($subscriptionId, $issueDeliveryId);
    }

    protected function getModelClass(): string
    {
        return SubscriptionIssueFulfilment::class;
    }

    private function refreshExistingFulfilment(
        SubscriptionIssueFulfilment $existing,
        ?\DateTimeInterface $scheduledFor,
        ?\DateTimeInterface $deferredUntil
    ): SubscriptionIssueFulfilment {
        if (
            $existing->status === SubscriptionIssueFulfilmentStatus::SUPERSEDED->value
            && !$existing->dispatched_at
        ) {
            $existing->update([
                'status' => SubscriptionIssueFulfilmentStatus::SCHEDULED->value,
                'attempts' => 0,
                'scheduled_for' => $scheduledFor?->format('Y-m-d H:i:s'),
                'deferred_until' => $deferredUntil?->format('Y-m-d H:i:s'),
                'failed_at' => null,
                'failure_reason' => null,
                'skip_reason' => null,
            ]);

            return $existing;
        }

        $updates = [];

        if (!$existing->scheduled_for && $scheduledFor) {
            $updates['scheduled_for'] = $scheduledFor->format('Y-m-d H:i:s');
        }

        if (!$existing->deferred_until && $deferredUntil && !$existing->dispatched_at) {
            $updates['deferred_until'] = $deferredUntil->format('Y-m-d H:i:s');
        }

        if (!empty($updates)) {
            $existing->update($updates);
        }

        return $existing;
    }
}
