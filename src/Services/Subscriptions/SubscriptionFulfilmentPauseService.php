<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;

/**
 * Keeps a subscriber's pending fulfilments in step with a subscription-level
 * pause (SubscriptionPauseService), which is a separate concern from the
 * dated print-delivery pause handled by SubscriptionDeliveryService.
 *
 * Pause:  every scheduled, undispatched fulfilment moves to PAUSED.
 * Resume: the PAUSED rows are superseded (never reused — same convention as
 *         SubscriptionIssueDeliveryRebuildService) and replaced with that
 *         same number of fresh rows taken from the next available plan
 *         issues, starting from the resume date. A subscription paused with
 *         5 of 12 fulfilments already delivered and 7 pending therefore
 *         resumes with 7 fresh fulfilments from the next available issue —
 *         the subscriber's total entitlement is preserved, not shifted by
 *         the pause window.
 */
class SubscriptionFulfilmentPauseService
{
    public function __construct(
        private readonly SubscriptionIssueFulfilmentRepository $fulfilmentRepository,
        private readonly IssueDeliveryRepository $issueDeliveryRepository,
        private readonly Logger $logger,
    ) {
    }

    public function pause(Subscription $subscription): int
    {
        $count = $this->fulfilmentRepository->pausePendingForSubscription((int) $subscription->id);

        $this->logger->info('SubscriptionFulfilmentPauseService: fulfilments paused', [
            'subscription_id' => $subscription->id,
            'count' => $count,
        ]);

        return $count;
    }

    /**
     * @return int The number of replacement fulfilments created.
     */
    public function resume(Subscription $subscription, ?\DateTimeInterface $fromDate = null): int
    {
        $pausedCount = $this->fulfilmentRepository->countPausedForSubscription((int) $subscription->id);

        if ($pausedCount === 0) {
            return 0;
        }

        $scheduleIssues = $this->issueDeliveryRepository->findFutureIssuesForPlan(
            subscriptionPlanId: (int) $subscription->plan_id,
            fromDate: $fromDate instanceof \DateTimeImmutable
                ? $fromDate
                : \DateTimeImmutable::createFromInterface($fromDate ?? new \DateTimeImmutable()),
            limit: $pausedCount,
        );

        $this->fulfilmentRepository->supersedePausedForSubscription((int) $subscription->id);

        $created = 0;

        foreach ($scheduleIssues as $issue) {
            $this->fulfilmentRepository->createFromSchedule((int) $subscription->id, $issue);
            $created++;
        }

        if ($created < $pausedCount) {
            $this->logger->warning('SubscriptionFulfilmentPauseService: fewer future issues available than paused fulfilments', [
                'subscription_id' => $subscription->id,
                'paused' => $pausedCount,
                'replaced' => $created,
            ]);
        }

        $this->logger->info('SubscriptionFulfilmentPauseService: fulfilments resumed', [
            'subscription_id' => $subscription->id,
            'paused' => $pausedCount,
            'created' => $created,
        ]);

        return $created;
    }
}
