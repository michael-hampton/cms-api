<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Enums\Subscriptions\IssueDeliveredStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Models\IssueDelivery;
use App\Models\Subscription;
use App\Repositories\Subscriptions\IssuesDeliveredRepository;

class IssueFulfilmentPlanner
{
    public function __construct(
        private readonly IssuesDeliveredRepository $issuesDeliveredRepository
    )
    {
    }

    public function plan(IssueDelivery $issueDelivery, iterable $subscriptions): array
    {
        $scheduledFor = $issueDelivery->estimated_delivery_date ?? $issueDelivery->on_sale_date;
        $now = new \DateTime();
        $digitalCandidates = [];
        $printCandidates = [];
        $created = 0;
        $deferred = 0;
        $notDue = 0;
        $alreadyDispatched = 0;
        $nonDispatchableStatus = 0;

        foreach ($subscriptions as $subscription) {
            $existing = $this->issuesDeliveredRepository
                ->findBySubscriptionAndSchedule($subscription->id, $issueDelivery->id);

            $fulfilment = $this->issuesDeliveredRepository->createForSubscription(
                $subscription->id,
                $issueDelivery->id,
                $scheduledFor,
                $this->resolveDeferredUntil($subscription, $scheduledFor)
            );

            if (!$existing) {
                $created++;
            }

            if ($fulfilment->dispatched_at) {
                $alreadyDispatched++;
                continue;
            }

            if ($fulfilment->status !== IssueDeliveredStatus::SCHEDULED->value) {
                $nonDispatchableStatus++;
                continue;
            }

            if ($fulfilment->deferred_until instanceof \DateTimeInterface && $fulfilment->deferred_until > $now) {
                $deferred++;
                continue;
            }

            if ($fulfilment->scheduled_for instanceof \DateTimeInterface && $fulfilment->scheduled_for > $now) {
                $notDue++;
                continue;
            }

            if ($subscription->delivery_type === SubscriptionType::PRINTED->value) {
                $printCandidates[] = (int) $fulfilment->id;
            } else {
                $digitalCandidates[] = (int) $fulfilment->id;
            }
        }

        $digitalIds = $this->issuesDeliveredRepository->claimForDispatch($digitalCandidates, $now);
        $printIds = $this->issuesDeliveredRepository->claimForDispatch($printCandidates, $now);
        $claimConflicts = count($digitalCandidates) + count($printCandidates)
            - count($digitalIds) - count($printIds);

        return [
            'digital_ids' => $digitalIds,
            'print_ids' => $printIds,
            'created' => $created,
            'deferred' => $deferred,
            'not_due' => $notDue,
            'already_dispatched' => $alreadyDispatched,
            'non_dispatchable_status' => $nonDispatchableStatus,
            'claim_conflicts' => $claimConflicts,
        ];
    }

    private function resolveDeferredUntil(
        Subscription $subscription,
        ?\DateTimeInterface $scheduledFor
    ): ?\DateTimeInterface {
        if (!$subscription->delivery_paused || !$scheduledFor) {
            return null;
        }

        $pauseStart = $subscription->delivery_pause_start;
        $pauseEnd = $subscription->delivery_pause_end;

        if (!$pauseStart instanceof \DateTimeInterface || !$pauseEnd instanceof \DateTimeInterface) {
            return null;
        }

        if ($scheduledFor < $pauseStart || $scheduledFor > $pauseEnd) {
            return null;
        }

        $deferredUntil = new \DateTime($pauseEnd->format('Y-m-d H:i:s'));
        $deferredUntil->modify('+1 day');

        return $deferredUntil;
    }
}
