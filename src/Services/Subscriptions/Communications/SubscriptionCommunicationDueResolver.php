<?php

namespace App\Services\Subscriptions\Communications;

use App\Enums\Subscriptions\CommunicationRelativeTo;
use App\Models\Subscription;
use App\Models\SubscriptionCommunicationSchedule;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;

class SubscriptionCommunicationDueResolver
{
    public function isDue(
        Subscription $subscription,
        SubscriptionCommunicationSchedule $schedule,
        DateTimeInterface $date
    ): bool {
        if (!$schedule->is_active) {
            return false;
        }

        if ($schedule->trigger_type === 'fixed') {
            return $this->isFixedDue($schedule, $date);
        }

        return $this->isRelativeDue($subscription, $schedule, $date);
    }

    private function isFixedDue(
        SubscriptionCommunicationSchedule $schedule,
        DateTimeInterface $date
    ): bool {
        if ($schedule->fixed_date === null) {
            return false;
        }

        $fixedDate = $this->toDate($schedule->fixed_date);

        if ($fixedDate === null) {
            return false;
        }

        return $this->sameDay($fixedDate, $date);
    }

    private function isRelativeDue(
        Subscription $subscription,
        SubscriptionCommunicationSchedule $schedule,
        DateTimeInterface $date
    ): bool {
        $relativeTo = $schedule->relative_to instanceof CommunicationRelativeTo
            ? $schedule->relative_to
            : CommunicationRelativeTo::tryFrom((string) $schedule->relative_to);

        if ($relativeTo === null) {
            return false;
        }

        $anchorDate = $this->resolveAnchorDate($subscription, $relativeTo);

        if ($anchorDate === null) {
            return false;
        }

        $offsetDays = (int) ($schedule->offset_days ?? 0);

        $targetDate = $anchorDate->modify(
            sprintf('%+d days', $offsetDays)
        );

        return $this->sameDay($targetDate, $date);
    }

    private function resolveAnchorDate(
        Subscription $subscription,
        CommunicationRelativeTo $relativeTo
    ): ?DateTimeImmutable {
        return match ($relativeTo) {
            CommunicationRelativeTo::RENEWAL_DATE =>
            $this->toDate($subscription->renewal_date ?? null),

            CommunicationRelativeTo::SUBSCRIPTION_START_DATE =>
            $this->toDate($subscription->start_date ?? null),

            CommunicationRelativeTo::SUBSCRIPTION_END_DATE =>
            $this->toDate($subscription->end_date ?? null),

            CommunicationRelativeTo::CCC_EXPIRY_DATE =>
            $this->toDate($subscription->ccc_expiry_date ?? null),

            CommunicationRelativeTo::SEGMENT_ASSIGNED_AT =>
            null,
        };
    }

    private function toDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        try {
            return new DateTimeImmutable((string) $value);
        } catch (Exception) {
            return null;
        }
    }

    private function sameDay(DateTimeInterface $left, DateTimeInterface $right): bool
    {
        return $left->format('Y-m-d') === $right->format('Y-m-d');
    }
}