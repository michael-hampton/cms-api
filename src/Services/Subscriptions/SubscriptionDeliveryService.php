<?php

namespace App\Services\Subscriptions;

use App\Events\Subscriptions\SubscriptionPaused;
use App\Events\Subscriptions\SubscriptionResumed;
use App\Framework\Database\Database;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\IssuesDeliveredRepository;
use App\Repositories\Subscriptions\PlanIssueScheduleRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

class SubscriptionDeliveryService
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly IssueDeliveryRepository $issueDeliveryRepository,
        private readonly IssuesDeliveredRepository $issuesDeliveredRepository,
        private readonly PlanIssueScheduleRepository $planIssueScheduleRepository,
        private readonly Database $database
    )
    {
    }

    public function pauseDelivery(
        int $subscriptionId,
        \DateTime $pauseStart,
        \DateTime $pauseEnd,
        ?string $reason = null
    ): array
    {
        return $this->database->transaction(function () use ($subscriptionId, $pauseStart, $pauseEnd, $reason) {
            $subscription = $this->subscriptionRepository->find($subscriptionId);

            if (!$subscription) {
                throw new \Exception('Subscription not found');
            }

            if (!$subscription->canPauseDelivery()) {
                throw new \Exception('This subscription cannot be paused');
            }

            $now = new \DateTime();
            if ($pauseEnd <= $pauseStart) {
                throw new \Exception('End date must be after start date');
            }

            if ($pauseStart < $now->modify('-1 day')) {
                throw new \Exception('Start date cannot be in the past');
            }

            $maxPauseDays = 90;
            $pauseDuration = $pauseStart->diff($pauseEnd)->days;
            if ($pauseDuration > $maxPauseDays) {
                throw new \Exception("Pause period cannot exceed {$maxPauseDays} days");
            }

            $updated = $this->subscriptionRepository->update($subscriptionId, [
                'delivery_paused' => true,
                'delivery_pause_start' => $pauseStart->format('Y-m-d'),
                'delivery_pause_end' => $pauseEnd->format('Y-m-d'),
                'delivery_pause_reason' => $reason
            ]);

            if (!$updated) {
                throw new \Exception('Failed to pause delivery');
            }

            $this->deferIssueFulfilments(
                $subscriptionId,
                (int) $subscription->plan_id,
                $pauseStart,
                $pauseEnd
            );

            if (($_ENV['APP_ENV'] ?? getenv('APP_ENV')) !== 'testing') {
                event(new SubscriptionPaused(
                    subscription: $this->subscriptionRepository->find($subscriptionId),
                    pausedUntil: $pauseEnd->format('Y-m-d H:i:s'),
                    pauseStart: $pauseStart->format('Y-m-d H:i:s'),
                    reason: $reason,
                ));
            }

            return [
                'success' => true,
                'message' => 'Delivery paused successfully',
                'pause_start' => $pauseStart->format('Y-m-d'),
                'pause_end' => $pauseEnd->format('Y-m-d'),
                'paused_days' => $pauseDuration,
                'subscription' => $updated
            ];
        });
    }

    private function deferIssueFulfilments(
        int $subscriptionId,
        int $subscriptionPlanId,
        \DateTime $pauseStart,
        \DateTime $pauseEnd
    ): void
    {
        $scheduleIssues = $this->planIssueScheduleRepository->findWithinDeliveryWindow(
            $subscriptionPlanId,
            $pauseStart,
            $pauseEnd
        );

        $issueDeliveryIds = [];

        foreach ($scheduleIssues as $issue) {
            $scheduledDate = $issue->estimated_delivery_date ?? $issue->on_sale_date;

            $this->issuesDeliveredRepository->createForSubscription(
                $subscriptionId,
                (int) $issue->id,
                $scheduledDate
            );

            $issueDeliveryIds[] = (int) $issue->id;
        }

        $deferredUntil = clone $pauseEnd;
        $deferredUntil->modify('+1 day');

        $this->issuesDeliveredRepository->deferForSubscriptionAndIssues(
            $subscriptionId,
            $issueDeliveryIds,
            $deferredUntil
        );
    }

    public function resumeDelivery(int $subscriptionId): array
    {
        return $this->database->transaction(function () use ($subscriptionId) {
            $subscription = $this->subscriptionRepository->find($subscriptionId);

            if (!$subscription) {
                throw new \Exception('Subscription not found');
            }

            if (!$subscription->canResumeDelivery()) {
                throw new \Exception('This subscription is not paused');
            }

            $updated = $this->subscriptionRepository->update($subscriptionId, [
                'delivery_paused' => false,
                'delivery_pause_start' => null,
                'delivery_pause_end' => null,
                'delivery_pause_reason' => null
            ]);

            if (!$updated) {
                throw new \Exception('Failed to resume delivery');
            }

            $this->issuesDeliveredRepository->releaseDeferredForSubscription($subscriptionId);

            if (($_ENV['APP_ENV'] ?? getenv('APP_ENV')) !== 'testing') {
                event(new SubscriptionResumed(
                    subscription: $this->subscriptionRepository->find($subscriptionId),
                    memberId: (int)$subscription->member_id,
                ));
            }

            return [
                'success' => true,
                'message' => 'Delivery resumed successfully',
                'subscription' => $updated
            ];
        });
    }

    public function getPauseStatus(int $subscriptionId): array
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription) {
            return [
                'success' => false,
                'message' => 'Subscription not found'
            ];
        }

        return [
            'success' => true,
            'is_paused' => $subscription->isDeliveryPaused(),
            'can_pause' => $subscription->canPauseDelivery(),
            'can_resume' => $subscription->canResumeDelivery(),
            'pause_start' => $subscription->delivery_pause_start?->format('Y-m-d'),
            'pause_end' => $subscription->delivery_pause_end?->format('Y-m-d'),
            'days_until_resume' => $subscription->getDaysUntilPauseEnds(),
            'reason' => $subscription->delivery_pause_reason
        ];
    }

    public function setStartIssue(
        int $subscriptionId,
        int $issueId
    ): void
    {
        $this->database->transaction(function () use ($subscriptionId, $issueId) {
            $issue = $this->issueDeliveryRepository->find($issueId);

            if (!$issue) {
                throw new \Exception('Issue not found');
            }

            if (strtotime($issue->publication_date) < strtotime('today')) {
                throw new \Exception('Cannot start subscription with past issue');
            }

            $this->subscriptionRepository->update($subscriptionId, [
                'start_issue_id' => $issueId,
                'start_date' => $issue->publication_date
            ]);
        });
    }
}
