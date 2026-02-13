<?php

namespace App\Services\Shipping;

use DateInterval;
use DateTimeImmutable;

class BusinessDayCalculator
{
    public function __construct(
        private readonly HolidayProviderInterface $holidayProvider
    )
    {
    }

    public function addBusinessDays(
        DateTimeImmutable $date,
        int               $days
    ): DateTimeImmutable
    {
        if ($days === 0) {
            return $date;
        }

        $current = $date;
        $remainingDays = $days;

        while ($remainingDays > 0) {
            $current = $current->add(new DateInterval('P1D'));

            if (!$this->isWeekend($current) && !$this->holidayProvider->isHoliday($current)) {
                $remainingDays--;
            }
        }

        return $current;
    }

    private function isWeekend(DateTimeImmutable $date): bool
    {
        $dayOfWeek = (int)$date->format('N'); // 1 (Monday) through 7 (Sunday)
        return in_array($dayOfWeek, [6, 7]); // Saturday = 6, Sunday = 7
    }
}