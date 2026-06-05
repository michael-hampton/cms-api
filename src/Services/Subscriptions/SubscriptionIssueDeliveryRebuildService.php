<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\DTO\Subscriptions\PublicationChangeRebuildResult;
use App\Repositories\Subscriptions\IssueDeliveryRepository;

/**
 * Rebuilds future issue deliveries for a subscription after an edition or
 * publication change.
 *
 * This service is the only place that touches future deliveries during a
 * subscription change. Callers (SubscriptionEditionChangeService,
 * SubscriptionPublicationChangeService) own the transaction boundary — never
 * call this service from inside another transaction.
 *
 * Design decisions:
 *   - Future deliveries are marked "superseded", not hard-deleted.
 *     This preserves an audit trail of what would have been sent.
 *   - "Future" means any delivery whose status is: pending, scheduled,
 *     not_dispatched. Dispatched, delivered, and failed rows are never touched.
 *   - New deliveries are created by pulling the next N issue schedule records
 *     for the new edition, ordered by on_sale_date ascending.
 *   - All direct model access has been moved to IssueDeliveryRepository so
 *     this service can be fully unit-tested with Mockery.
 */
class SubscriptionIssueDeliveryRebuildService
{
    /**
     * Statuses considered "future" and safe to supersede.
     */
    private const FUTURE_STATUSES = ['pending', 'scheduled', 'not_dispatched'];

    public function __construct(
        private readonly IssueDeliveryRepository $issueDeliveryRepository,
    ) {}

    /**
     * Rebuilds future issue deliveries for a subscription after a plan/publication
     * change.
     *
     * Domain language:
     *   - SubscriptionPlan = publication
     *   - IssueDelivery = edition / issue schedule row
     *
     * This service is the only place that touches future deliveries during a
     * subscription plan change.
     *
     * The caller owns the transaction boundary. This service must not start its
     * own transaction.
     */
    /**
     * Rebuild future deliveries after an issue/edition change.
     *
     * This keeps the subscription on the same plan/publication.
     *
     * @param int $subscriptionId
     * @param int $subscriptionPlanId The current subscriptions.plan_id
     * @param int $startingEditionId  The selected IssueDelivery.id
     * @param int $remainingIssueCount How many future issues the customer is owed
     */
    public function rebuildForEditionChange(
        int $subscriptionId,
        int $subscriptionPlanId,
        int $startingEditionId,
        int $remainingIssueCount,
    ): void {
        if ($remainingIssueCount <= 0) {
            return;
        }

        $futureDeliveries = $this->issueDeliveryRepository
            ->getFutureDeliveriesForSubscription($subscriptionId, self::FUTURE_STATUSES);

        $this->supersedeDeliveries($futureDeliveries);

        $scheduleIssues = $this->issueDeliveryRepository
            ->findFutureIssuesForPlanStartingFromIssue(
                subscriptionPlanId: $subscriptionPlanId,
                startingIssueDeliveryId: $startingEditionId,
                limit: $remainingIssueCount,
            );

        if ($scheduleIssues->count() < $remainingIssueCount) {
            throw new \RuntimeException(
                "Only {$scheduleIssues->count()} future issues found for plan #{$subscriptionPlanId} "
                . "starting from edition #{$startingEditionId}; {$remainingIssueCount} required."
            );
        }

        foreach ($scheduleIssues as $issue) {
            $this->issueDeliveryRepository->createFulfilmentFromSchedule(
                $subscriptionId,
                $subscriptionPlanId,
                $issue,
            );
        }
    }

    public function rebuildForPublicationChange(
        int $subscriptionId,
        int $newPublicationId,
        int $remainingIssueCount,
    ): PublicationChangeRebuildResult {
        $futureDeliveries = $this->issueDeliveryRepository
            ->getFutureDeliveriesForSubscription($subscriptionId, self::FUTURE_STATUSES);

        $oldEditionId = $this->resolveFirstEditionId($futureDeliveries);

        $this->supersedeDeliveries($futureDeliveries);

        if ($remainingIssueCount <= 0) {
            return new PublicationChangeRebuildResult(
                oldEditionId: $oldEditionId,
                newEditionId: null,
                remainingIssuesTransferred: 0,
            );
        }

        $scheduleIssues = $this->issueDeliveryRepository->findFutureIssuesForPlan(
            subscriptionPlanId: $newPublicationId,
            fromDate: new \DateTimeImmutable(),
            limit: $remainingIssueCount,
        );

        if ($scheduleIssues->count() < $remainingIssueCount) {
            throw new \RuntimeException(
                "Only {$scheduleIssues->count()} future issues found for publication #{$newPublicationId}; "
                . "{$remainingIssueCount} required."
            );
        }

        $newEditionId = null;
        $transferred = 0;

        foreach ($scheduleIssues as $issue) {
            if ($newEditionId === null) {
                $newEditionId = (int) $issue->id;
            }

            $this->issueDeliveryRepository->createFulfilmentFromSchedule(
                $subscriptionId,
                $newPublicationId,
                $issue,
            );


            $transferred++;
        }

        return new PublicationChangeRebuildResult(
            oldEditionId: $oldEditionId,
            newEditionId: $newEditionId,
            remainingIssuesTransferred: $transferred,
        );
    }


    /**
     * Count of future non-dispatched issue deliveries for a subscription.
     *
     * Used by SubscriptionPublicationChangeService to calculate remaining
     * entitlement before the transaction starts.
     */
    public function countRemainingIssues(int $subscriptionId): int
    {
        return $this->issueDeliveryRepository
            ->countFutureForSubscription($subscriptionId, self::FUTURE_STATUSES);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * @param \App\Models\IssueDelivery[] $deliveries
     */
    private function supersedeDeliveries(array $deliveries): void
    {
        if (empty($deliveries)) {
            return;
        }

        $ids = array_map(static fn($d): int => $d->id, $deliveries);

        $this->issueDeliveryRepository->supersedeManyByIds($ids);
    }

    /**
     * Create $count new fulfilment rows for the subscription from the new
     * edition's schedule. If fewer schedule records exist than requested,
     * we create what we can — the caller records what was actually transferred.
     */
    private function createDeliveries(
        int $subscriptionId,
        int $editionId,
        int $count,
    ): void {
        if ($count <= 0) {
            return;
        }

        $scheduleIssues = $this->issueDeliveryRepository
            ->getUpcomingScheduleIssues($editionId, $count);

        foreach ($scheduleIssues as $issue) {
            $this->issueDeliveryRepository->createFulfilmentFromSchedule(
                $subscriptionId,
                $editionId,
                $issue,
            );
        }
    }

    /**
     * Resolve the first old edition / issue id from the future deliveries being superseded.
     *
     * Depending on how the repository hydrates these rows, the source issue id may be:
     *   - issue_delivery_id, if this is a subscription fulfilment row
     *   - id, if this is already an IssueDelivery schedule row
     *
     * @param array<int, object> $futureDeliveries
     */
    private function resolveFirstEditionId(array $futureDeliveries): ?int
    {
        if (empty($futureDeliveries)) {
            return null;
        }

        $first = $futureDeliveries[0];

        if (isset($first->issue_delivery_id)) {
            return (int) $first->issue_delivery_id;
        }

        if (isset($first->id)) {
            return (int) $first->id;
        }

        return null;
    }

    public function resolveCurrentFutureEditionId(int $subscriptionPlanId): ?int
    {
        $futureDeliveries = $this->issueDeliveryRepository
            ->getFutureDeliveriesForPlan(
                $subscriptionPlanId,
                self::FUTURE_STATUSES,
            );

        return $this->resolveFirstEditionId($futureDeliveries);
    }
}
