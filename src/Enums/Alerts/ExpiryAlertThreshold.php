<?php

declare(strict_types=1);

namespace App\Enums\Alerts;

enum ExpiryAlertThreshold: int
{
    case FortyEightHours = 48;
    case TwentyFourHours = 24;

    /**
     * Returns all thresholds ordered from furthest to nearest,
     * which is the natural processing order for a scheduled command.
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [
            self::FortyEightHours,
            self::TwentyFourHours,
        ];
    }
}