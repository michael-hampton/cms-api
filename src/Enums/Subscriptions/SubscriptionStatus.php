<?php

namespace App\Enums\Subscriptions;

enum SubscriptionStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case RENEWING_SOON = 'renewing_soon';
    case EXPIRING_SOON = 'expiring_soon';
    case PAUSED = 'paused';
    case UNPAID       = 'unpaid';
    case INCOMPLETE   = 'incomplete';
    case PAST_DUE = 'past_due';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case FAILED = 'failed';
    case TRIALING = 'trialing';
    case SCHEDULED = 'scheduled';
    case GRACE_PERIOD = 'grace_period';
    case SUSPENDED = 'suspended';
    case REPLACED = 'replaced';

    case RETRYING = 'retrying';

    /**
     * Statuses that grant access to subscription content/features.
     * Used by entitlement checks — single source of truth.
     */
    public static function entitledStatuses(): array
    {
        return [
            self::ACTIVE->value,
            self::RENEWING_SOON->value,
            self::EXPIRING_SOON->value,
            self::TRIALING->value,
            self::GRACE_PERIOD->value,
            self::RETRYING->value,
        ];
    }

    public static function isEntitled(string $status): bool
    {
        return in_array($status, self::entitledStatuses(), true);
    }
}
