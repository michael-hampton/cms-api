<?php

namespace App\Services\Shipping;

use DateTimeImmutable;

class CutOffTimeResolver
{
    public function __construct(
        private readonly BusinessDayCalculator $calculator
    )
    {
    }

    public function resolveStartDate(
        DateTimeImmutable $orderDate,
        string            $cutOffTime
    ): DateTimeImmutable
    {
        $cutOff = new DateTimeImmutable(
            $orderDate->format('Y-m-d') . ' ' . $cutOffTime
        );

        if ($orderDate > $cutOff) {
            return $this->calculator->addBusinessDays($orderDate, 1);
        }

        return $orderDate;
    }
}