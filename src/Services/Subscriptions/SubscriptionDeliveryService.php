<?php

namespace App\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Repositories\IssueDeliveryRepository;
use App\Repositories\SubscriptionRepository;

class SubscriptionDeliveryService
{
    public function __construct(
        private readonly SubscriptionRepository  $subscriptionRepository,
        private readonly IssueDeliveryRepository $issueDeliveryRepository,
        private readonly Database                $database
    )
    {
    }

    /**
     * Pause delivery for a subscription
     */
    public function pauseDelivery(
        int       $subscriptionId,
        \DateTime $pauseStart,
        \DateTime $pauseEnd,
        ?string   $reason = null
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

            // Validate dates
            $now = new \DateTime();
            if ($pauseEnd <= $pauseStart) {
                throw new \Exception('End date must be after start date');
            }

            if ($pauseStart < $now->modify('-1 day')) {
                throw new \Exception('Start date cannot be in the past');
            }

            $maxPauseDays = 90; // Maximum 90 days pause
            $pauseDuration = $pauseStart->diff($pauseEnd)->days;
            if ($pauseDuration > $maxPauseDays) {
                throw new \Exception("Pause period cannot exceed {$maxPauseDays} days");
            }

            // Update subscription
            $updated = $this->subscriptionRepository->update($subscriptionId, [
                'delivery_paused' => true,
                'delivery_pause_start' => $pauseStart->format('Y-m-d'),
                'delivery_pause_end' => $pauseEnd->format('Y-m-d'),
                'delivery_pause_reason' => $reason
            ]);

            if (!$updated) {
                throw new \Exception('Failed to pause delivery');
            }

            // Update issue delivery schedule ONLY for this subscription
            $this->updateIssueDeliverySchedule($subscriptionId, $pauseStart, $pauseEnd);

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

    /**
     * Update issue delivery schedule to skip paused period
     */
    private function updateIssueDeliverySchedule(
        int       $subscriptionId,
        \DateTime $pauseStart,
        \DateTime $pauseEnd
    ): void
    {
        // Get all upcoming deliveries that fall within the pause period
        $upcomingDeliveries = $this->issueDeliveryRepository
            ->getUpcomingDeliveries($subscriptionId);

        $pauseDays = $pauseStart->diff($pauseEnd)->days;

        foreach ($upcomingDeliveries as $delivery) {
            $estimatedDelivery = $delivery->estimated_delivery_date;

            // If delivery falls within pause period, push it back
            if ($estimatedDelivery >= $pauseStart && $estimatedDelivery <= $pauseEnd) {
                $newDeliveryDate = clone $pauseEnd;
                $newDeliveryDate->modify('+1 day');

                $this->issueDeliveryRepository->update($delivery->id, [
                    'estimated_delivery_date' => $newDeliveryDate->format('Y-m-d H:i:s'),
                    'metadata' => array_merge($delivery->metadata ?? [], [
                        'paused' => true,
                        'original_date' => $estimatedDelivery->format('Y-m-d'),
                        'pause_days' => $pauseDays
                    ])
                ]);
            } elseif ($estimatedDelivery > $pauseEnd) {
                // Push all future deliveries back by the pause duration
                $newDeliveryDate = clone $estimatedDelivery;
                $newDeliveryDate->modify("+{$pauseDays} days");

                $this->issueDeliveryRepository->update($delivery->id, [
                    'estimated_delivery_date' => $newDeliveryDate->format('Y-m-d H:i:s'),
                    'metadata' => array_merge($delivery->metadata ?? [], [
                        'adjusted_for_pause' => true,
                        'pause_days' => $pauseDays
                    ])
                ]);
            }
        }
    }

    /**
     * Resume delivery for a subscription
     */
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

            // Clear pause data
            $updated = $this->subscriptionRepository->update($subscriptionId, [
                'delivery_paused' => false,
                'delivery_pause_start' => null,
                'delivery_pause_end' => null,
                'delivery_pause_reason' => null
            ]);

            if (!$updated) {
                throw new \Exception('Failed to resume delivery');
            }

            // Recalculate issue delivery schedule
            $this->recalculateIssueDeliverySchedule($subscriptionId);

            return [
                'success' => true,
                'message' => 'Delivery resumed successfully',
                'subscription' => $updated
            ];
        });
    }

    /**
     * Recalculate issue delivery schedule after resuming
     */
    private function recalculateIssueDeliverySchedule(int $subscriptionId): void
    {
        // Get all deliveries that were adjusted for pause
        $deliveries = $this->issueDeliveryRepository
            ->getUpcomingDeliveries($subscriptionId);

        foreach ($deliveries as $delivery) {

            $metadata = $delivery->metadata;

            // If delivery was paused, restore original date or recalculate
            if (isset($metadata['paused']) && $metadata['paused']) {
                $originalDate = new \DateTime($metadata['original_date']);
                $now = new \DateTime();

                // If original date is in the past, schedule for immediate delivery
                if ($originalDate < $now) {
                    $newDate = $now->modify('+1 day');
                } else {
                    $newDate = $originalDate;
                }

                // Remove pause metadata
                unset($metadata['paused'], $metadata['original_date'], $metadata['pause_days']);

                $this->issueDeliveryRepository->update($delivery->id, [
                    'estimated_delivery_date' => $newDate->format('Y-m-d H:i:s'),
                    'metadata' => $metadata
                ]);
            } elseif (isset($metadata['adjusted_for_pause'])) {
                // Revert the pause adjustment
                $pauseDays = $metadata['pause_days'] ?? 0;
                $currentDate = $delivery->estimated_delivery_date;
                $restoredDate = clone $currentDate;
                $restoredDate->modify("-{$pauseDays} days");

                // Remove adjustment metadata
                unset($metadata['adjusted_for_pause'], $metadata['pause_days']);

                $this->issueDeliveryRepository->update($delivery->id, [
                    'estimated_delivery_date' => $restoredDate->format('Y-m-d H:i:s'),
                    'metadata' => $metadata
                ]);
            }
        }
    }

    /**
     * Get pause status for a subscription
     */
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
}