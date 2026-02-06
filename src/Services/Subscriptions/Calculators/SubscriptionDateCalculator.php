<?php

namespace App\Services\Subscriptions\Calculators;

use App\Enums\Subscriptions\BillingPeriod;

class SubscriptionDateCalculator
{
    public function calculateEndDate(\DateTimeImmutable $startDate, BillingPeriod $period): \DateTimeImmutable
    {
        return $startDate->add($period->toDateInterval());
    }

    public function normalizeStartDate(?string $selectedStartDate): \DateTimeImmutable
    {
        if ($selectedStartDate) {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $selectedStartDate)
                ?: \DateTimeImmutable::createFromFormat('Y-m-d', $selectedStartDate);

            if (!$date) {
                throw new \InvalidArgumentException("Invalid date format: {$selectedStartDate}");
            }

            return $date->setTime(0, 0, 0);
        }

        return (new \DateTimeImmutable())->setTime(0, 0, 0);
    }
}