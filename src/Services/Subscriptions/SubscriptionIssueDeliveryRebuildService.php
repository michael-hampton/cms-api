<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\DTO\Subscriptions\PublicationChangeRebuildResult;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\IssuesDeliveredRepository;

class SubscriptionIssueDeliveryRebuildService
{
    public function __construct(
        private readonly IssueDeliveryRepository $issueDeliveryRepository,
        private readonly IssuesDeliveredRepository $issuesDeliveredRepository,
    ) {}

    public function rebuildForEditionChange(
        int $subscriptionId,
        int $subscriptionPlanId,
        int $startingEditionId,
        int $remainingIssueCount,
    ): void {
        if ($remainingIssueCount <= 0) {
            return;
        }

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

        $this->issuesDeliveredRepository->supersedeFutureForSubscription($subscriptionId);

        foreach ($scheduleIssues as $issue) {
            $this->issuesDeliveredRepository->createFromSchedule($subscriptionId, $issue);
        }
    }

    public function rebuildForPublicationChange(
        int $subscriptionId,
        int $newPublicationId,
        int $remainingIssueCount,
    ): PublicationChangeRebuildResult {
        $oldEditionId = $this->issuesDeliveredRepository
            ->resolveFirstFutureIssueId($subscriptionId);

        if ($remainingIssueCount <= 0) {
            $this->issuesDeliveredRepository->supersedeFutureForSubscription($subscriptionId);

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

        $this->issuesDeliveredRepository->supersedeFutureForSubscription($subscriptionId);

        $newEditionId = null;
        $transferred = 0;

        foreach ($scheduleIssues as $issue) {
            if ($newEditionId === null) {
                $newEditionId = (int) $issue->id;
            }

            $this->issuesDeliveredRepository->createFromSchedule($subscriptionId, $issue);
            $transferred++;
        }

        return new PublicationChangeRebuildResult(
            oldEditionId: $oldEditionId,
            newEditionId: $newEditionId,
            remainingIssuesTransferred: $transferred,
        );
    }

    public function countRemainingIssues(int $subscriptionId): int
    {
        return $this->issuesDeliveredRepository
            ->countFutureForSubscription($subscriptionId);
    }

    public function resolveCurrentFutureEditionId(int $subscriptionId): ?int
    {
        return $this->issuesDeliveredRepository
            ->resolveFirstFutureIssueId($subscriptionId);
    }
}
