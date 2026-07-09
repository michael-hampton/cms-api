<?php

declare(strict_types=1);

namespace App\Enums\Subscriptions;

/**
 * Where a subscription issue resolution's decision actually came from.
 *
 * Replaces the old `business_decision` boolean on
 * subscription_issue_resolutions so reporting can distinguish
 * policy-driven entitlement from manual intervention.
 */
enum DecisionSource: string
{
    case POLICY = 'policy';
    case BUSINESS_OVERRIDE = 'business_override';
    case MANAGER_OVERRIDE = 'manager_override';
    case SYSTEM = 'system';
}