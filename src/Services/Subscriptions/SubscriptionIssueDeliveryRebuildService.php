<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\DTO\Subscriptions\PublicationChangeRebuildResult;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;

class SubscriptionIssueDeliveryRebuildService
{
    public function __construct(
        private readonly IssueDeliveryRepository $issueDeliveryRepository,
        private readonly SubscriptionIssueFulfilmentRepository $subscriptionIssueFulfilmentRepository,
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

        $this->subscriptionIssueFulfilmentRepository->supersedeFutureForSubscription($subscriptionId);

        foreach ($scheduleIssues as $issue) {
            $this->subscriptionIssueFulfilmentRepository->createFromSchedule($subscriptionId, $issue);
        }
    }

    public function rebuildForPublicationChange(
        int $subscriptionId,
        int $newPublicationId,
        int $remainingIssueCount,
    ): PublicationChangeRebuildResult {
        $oldEditionId = $this->subscriptionIssueFulfilmentRepository
            ->resolveFirstFutureIssueId($subscriptionId);

        if ($remainingIssueCount <= 0) {
            $this->subscriptionIssueFulfilmentRepository->supersedeFutureForSubscription($subscriptionId);

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

        $this->subscriptionIssueFulfilmentRepository->supersedeFutureForSubscription($subscriptionId);

        $newEditionId = null;
        $transferred = 0;

        foreach ($scheduleIssues as $issue) {
            if ($newEditionId === null) {
                $newEditionId = (int) $issue->id;
            }

            $this->subscriptionIssueFulfilmentRepository->createFromSchedule($subscriptionId, $issue);
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
        return $this->subscriptionIssueFulfilmentRepository
            ->countFutureForSubscription($subscriptionId);
    }

    public function resolveCurrentFutureEditionId(int $subscriptionId): ?int
    {
        return $this->subscriptionIssueFulfilmentRepository
            ->resolveFirstFutureIssueId($subscriptionId);
    }
}
