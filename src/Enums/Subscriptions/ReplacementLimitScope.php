<?php

declare(strict_types=1);

namespace App\Enums\Subscriptions;

/**
 * The window a policy's max_replacements / max_extensions limit is
 * counted over. Kept as a first-class enum (rather than baked into the
 * evaluator) so the scope is configuration, not code — matches the
 * ticket's original goal of moving entitlement rules out of services.
 */
enum ReplacementLimitScope: string
{
    case PER_ISSUE = 'per_issue';
    case PER_SUBSCRIPTION = 'per_subscription';
    case PER_YEAR = 'per_year';
    case LIFETIME = 'lifetime';
}