<?php

declare(strict_types=1);

namespace App\Enums\Subscriptions;

/**
 * How long we wait, after a payment-failure/suspension trigger, before we
 * actually suspend a subscription's pending fulfilments.
 *
 * Configured per plan (product) via SubscriptionPlan::fulfilment_suspension_delay_type
 * / fulfilment_suspension_delay_value, resolved by FulfilmentSuspensionPolicyResolver.
 * Defaults to IMMEDIATE when a plan has no override — see
 * FulfilmentSuspensionPolicyResolver::DEFAULT_RULE.
 */
enum FulfilmentSuspensionDelayType: string
{
    /** Suspend pending fulfilments as soon as the trigger event fires. */
    case IMMEDIATE = 'immediate';

    /**
     * Suspend N days after the subscription's first issue was delivered.
     * See FulfilmentSuspensionPolicyResolver::resolveSuspendAt().
     */
    case DAYS = 'days';

    /**
     * Suspend once N further issues have been delivered after the first
     * issue. See FulfilmentSuspensionPolicyResolver::resolveSuspendAt().
     */
    case ISSUES = 'issues';
}
