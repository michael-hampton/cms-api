<?php

namespace App\Services\Shipping;


use DateTimeImmutable;

class UkHolidayProvider implements HolidayProviderInterface
{
    public function isHoliday(DateTimeImmutable $date): bool
    {
        return in_array(
            $date->format('Y-m-d'),
            $this->holidays((int)$date->format('Y'))
        );
    }


    private function holidays(int $year): array
    {
        // Load from config for maintainability
        $configHolidays = config('shipping.uk_bank_holidays', []);

        // Filter to only current year
        return array_filter($configHolidays, function ($date) use ($year) {
            return str_starts_with($date, (string)$year);
        });
    }
}