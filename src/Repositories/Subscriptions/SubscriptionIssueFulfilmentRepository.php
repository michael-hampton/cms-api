<?php

namespace App\Repositories\Subscriptions;

use App\Enums\Subscriptions\SubscriptionIssueFulfilmentStatus;
use App\Framework\Support\Collection;
use App\Models\IssueDelivery;
use App\Models\Subscription;
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
        $subscription = Subscription::find($subscriptionId);

        if ($subscription && $subscription->scheduled_fulfilments_count !== null) {
            return (int) $subscription->scheduled_fulfilments_count;
        }

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

        $this->syncCountsForSubscription($subscriptionId);

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
            $fulfilment = $this->refreshExistingFulfilment($existing, $scheduledFor, $deferredUntil);
            $this->syncCountsForSubscription($subscriptionId);

            return $fulfilment;
        }

        try {
            $fulfilment = $this->create([
                'subscription_id' => $subscriptionId,
                'issue_delivery_id' => $issueDeliveryId,
                'status' => SubscriptionIssueFulfilmentStatus::SCHEDULED->value,
                'attempts' => 0,
                'scheduled_for' => $scheduledFor?->format('Y-m-d H:i:s'),
                'deferred_until' => $deferredUntil?->format('Y-m-d H:i:s'),
            ]);

            $this->syncCountsForSubscription($subscriptionId);

            return $fulfilment;
        } catch (\Throwable $exception) {
            if (!$this->isDuplicateKeyException($exception)) {
                throw $exception;
            }

            $existing = $this->findBySubscriptionAndSchedule($subscriptionId, $issueDeliveryId);

            if ($existing) {
                $fulfilment = $this->refreshExistingFulfilment($existing, $scheduledFor, $deferredUntil);
                $this->syncCountsForSubscription($subscriptionId);

                return $fulfilment;
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
        $changedSubscriptionIds = [];
        $dispatchDate = $date->format('Y-m-d H:i:s');

        foreach (array_unique(array_map('intval', $fulfilmentIds)) as $fulfilmentId) {
            $fulfilment = SubscriptionIssueFulfilment::find($fulfilmentId);

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

                if ($fulfilment?->subscription_id) {
                    $changedSubscriptionIds[] = (int) $fulfilment->subscription_id;
                }
            }
        }

        $this->syncCountsForSubscriptions($changedSubscriptionIds);

        return $claimedIds;
    }

    public function releaseDispatchClaims(array $fulfilmentIds): int
    {
        if (empty($fulfilmentIds)) {
            return 0;
        }

        $ids = array_unique(array_map('intval', $fulfilmentIds));
        $changedSubscriptionIds = $this->getSubscriptionIdsForFulfilments($ids);

        $updated = SubscriptionIssueFulfilment::whereIn('id', $ids)
            ->where('status', SubscriptionIssueFulfilmentStatus::SCHEDULED->value)
            ->whereNotNull('dispatched_at')
            ->update(['dispatched_at' => null]);

        if ((int) $updated > 0) {
            $this->syncCountsForSubscriptions($changedSubscriptionIds);
        }

        return (int) $updated;
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

    public function syncCountsForSubscription(int $subscriptionId): void
    {
        if ($subscriptionId <= 0) {
            return;
        }

        Subscription::where('id', $subscriptionId)->update([
            'fulfilments_count' => SubscriptionIssueFulfilment::where('subscription_id', $subscriptionId)->count(),
            'scheduled_fulfilments_count' => SubscriptionIssueFulfilment::where('subscription_id', $subscriptionId)
                ->where('status', SubscriptionIssueFulfilmentStatus::SCHEDULED->value)
                ->whereNull('dispatched_at')
                ->count(),
            'dispatched_fulfilments_count' => SubscriptionIssueFulfilment::where('subscription_id', $subscriptionId)
                ->whereNotNull('dispatched_at')
                ->count(),
            'delivered_fulfilments_count' => SubscriptionIssueFulfilment::where('subscription_id', $subscriptionId)
                ->where('status', SubscriptionIssueFulfilmentStatus::DELIVERED->value)
                ->count(),
            'failed_fulfilments_count' => SubscriptionIssueFulfilment::where('subscription_id', $subscriptionId)
                ->where('status', SubscriptionIssueFulfilmentStatus::FAILED->value)
                ->count(),
            'superseded_fulfilments_count' => SubscriptionIssueFulfilment::where('subscription_id', $subscriptionId)
                ->where('status', SubscriptionIssueFulfilmentStatus::SUPERSEDED->value)
                ->count(),
        ]);
    }

    protected function getModelClass(): string
    {
        return SubscriptionIssueFulfilment::class;
    }

    private function syncCountsForSubscriptions(array $subscriptionIds): void
    {
        foreach (array_unique(array_filter(array_map('intval', $subscriptionIds))) as $subscriptionId) {
            $this->syncCountsForSubscription($subscriptionId);
        }
    }

    private function getSubscriptionIdsForFulfilments(array $fulfilmentIds): array
    {
        if (empty($fulfilmentIds)) {
            return [];
        }

        return SubscriptionIssueFulfilment::whereIn('id', $fulfilmentIds)
            ->get()
            ->pluck('subscription_id')
            ->map(function ($subscriptionId) {
                return (int) $subscriptionId;
            })
            ->toArray();
    }

    private function isDuplicateKeyException(\Throwable $exception): bool
    {
        $code = (string) $exception->getCode();

        if (in_array($code, ['23000', '23505'], true)) {
            return true;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'duplicate')
            || str_contains($message, 'unique constraint')
            || str_contains($message, 'unique violation')
            || str_contains($message, 'integrity constraint violation');
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
