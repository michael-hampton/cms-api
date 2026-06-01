<?php

namespace App\Services\Subscriptions\Communications;

use App\Models\Subscription;
use App\Repositories\MemberInsights\SubscriptionSegmentRepository;
use App\Repositories\Subscriptions\SubscriptionCommunicationDeliveryRepository;
use App\Repositories\Subscriptions\SubscriptionCommunicationRepository;
use DateTimeInterface;

class SubscriptionCommunicationCandidateResolver
{
    public function __construct(
        private readonly SubscriptionCommunicationRepository $communicationRepository,
        private readonly SubscriptionCommunicationDeliveryRepository $deliveryRepository,
        private readonly SubscriptionSegmentRepository $subscriptionSegmentRepository,
        private readonly SubscriptionCommunicationDueResolver $dueResolver,
    ) {
    }

    /**
     * @return array<int, array{
     *   communication: \App\Models\SubscriptionCommunication,
     *   schedule: \App\Models\SubscriptionCommunicationSchedule,
     *   segment: \App\Models\Segment|null,
     * }>
     */
    public function dueForSubscription(Subscription $subscription, DateTimeInterface $date): array
    {
        $activeAssignment = $this->subscriptionSegmentRepository->findActive($subscription->id);
        $segment = $activeAssignment?->segment;
        $segmentId = $segment?->id;

        $communications = $this->communicationRepository->findActiveForSegment($segmentId);

        $candidates = [];

        foreach ($communications as $communication) {
            $schedules = $communication->schedules
                ->filter(fn ($schedule) => $schedule->is_active)
                ->sortBy('sort_order');

            foreach ($schedules as $schedule) {
                if (!$this->dueResolver->isDue($subscription, $schedule, $date)) {
                    continue;
                }

                if ($this->deliveryRepository->hasAlreadySent(
                    $subscription->id,
                    $communication->id,
                    $schedule->id,
                )) {
                    continue;
                }

                $candidates[] = [
                    'communication' => $communication,
                    'schedule' => $schedule,
                    'segment' => $segment,
                ];
            }
        }

        return $candidates;
    }
}