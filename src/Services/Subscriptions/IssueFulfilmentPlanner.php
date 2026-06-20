<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

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
        $digitalIds = [];
        $printIds = [];
        $created = 0;
        $deferred = 0;

        foreach ($subscriptions as $subscription) {
            $fulfilment = $this->issuesDeliveredRepository
                ->findBySubscriptionAndSchedule($subscription->id, $issueDelivery->id);

            if (!$fulfilment) {
                $fulfilment = $this->issuesDeliveredRepository->createForSubscription(
                    $subscription->id,
                    $issueDelivery->id,
                    $scheduledFor,
                    $this->resolveDeferredUntil($subscription, $scheduledFor)
                );
                $created++;
            }

            if (!$fulfilment->canDispatchAt($now)) {
                $deferred++;
                continue;
            }

            if ($subscription->delivery_type === SubscriptionType::PRINTED->value) {
                $printIds[] = (int) $fulfilment->id;
            } else {
                $digitalIds[] = (int) $fulfilment->id;
            }
        }

        return [
            'digital_ids' => $digitalIds,
            'print_ids' => $printIds,
            'created' => $created,
            'deferred' => $deferred,
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
