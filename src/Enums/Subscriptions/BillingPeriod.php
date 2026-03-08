<?php

namespace App\Enums\Subscriptions;

enum BillingPeriod: string
{
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case WEEKLY = 'weekly';
    case YEARLY = 'yearly';
    case LIFETIME = 'lifetime';
    case ONE_TIME = '2year'; // Keep existing '2year' for backward compatibility

    public function toDateInterval(): \DateInterval
    {
        return match ($this) {
            self::MONTHLY => new \DateInterval('P1M'),
            self::YEARLY => new \DateInterval('P1Y'),
            self::ONE_TIME => new \DateInterval('P2Y'),
        };
    }
    public function toDateModifier(): ?string
    {
        return match ($this) {
            self::MONTHLY => '+1 month',
            self::QUARTERLY => '+3 months',
            self::YEARLY => '+1 year',
            self::ONE_TIME => '+2 years',
            self::LIFETIME => null,
        };
    }

    public function isRecurring(): bool
    {
        return !in_array($this, [self::LIFETIME, self::ONE_TIME]);
    }

    public function getMonths(): int
    {
        return match ($this) {
            self::MONTHLY => 1,
            self::YEARLY => 12,
            self::ONE_TIME => 24,
        };
    }
}