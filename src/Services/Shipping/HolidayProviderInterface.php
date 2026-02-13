<?php

namespace App\Services\Shipping;

use DateTimeImmutable;

interface HolidayProviderInterface
{
    public function isHoliday(DateTimeImmutable $date): bool;
}