<?php

declare(strict_types=1);

namespace App\Enums\Subscriptions;

/**
 * The individual pause/cancellation settings a concrete
 * ReplacementPolicyInterface policy can expose for per-site admin
 * override, via SubscriptionPolicySettingOverrideService.
 *
 * Deliberately scoped to pause + cancellation only (not
 * replace/extend — MAX_REPLACEMENTS/MAX_EXTENSIONS stay as
 * per-class consts, out of scope for this ticket). A policy class
 * declares which of these it supports, and their defaults, via
 * ReplacementPolicyInterface::overridableSettings(); attempting to
 * override a key a policy doesn't declare is rejected by
 * SubscriptionPolicySettingOverrideService.
 */
enum PolicySettingKey: string
{
    case PAUSE_ALLOWED = 'pause_allowed';
    case PAUSE_LIMIT_PER_TERM = 'pause_limit_per_term';
    case PAUSE_REQUIRES_MANAGER_APPROVAL = 'pause_requires_manager_approval';
    case CANCELLATION_ALLOWED = 'cancellation_allowed';
    case CANCELLATION_REQUIRES_MANAGER_APPROVAL = 'cancellation_requires_manager_approval';

    /**
     * Value type, used to validate admin input before it's persisted.
     * PAUSE_LIMIT_PER_TERM is the only nullable-int setting (null means
     * unlimited); the rest are plain booleans.
     */
    public function valueType(): string
    {
        return match ($this) {
            self::PAUSE_LIMIT_PER_TERM => 'nullable_int',
            default => 'bool',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PAUSE_ALLOWED => 'Pausing allowed',
            self::PAUSE_LIMIT_PER_TERM => 'Pauses allowed per term (blank = unlimited)',
            self::PAUSE_REQUIRES_MANAGER_APPROVAL => 'Pause requires manager approval',
            self::CANCELLATION_ALLOWED => 'Cancellation allowed',
            self::CANCELLATION_REQUIRES_MANAGER_APPROVAL => 'Cancellation requires manager approval',
        };
    }
}