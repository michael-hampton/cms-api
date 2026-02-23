<?php

namespace App\Events\Subscriptions;

use App\Models\IssueDelivery;

/**
 * Fired when an IssueDelivery send attempt fails.
 * Listeners may: alert ops, schedule retry, log to monitoring.
 */
class IssueDeliveryDispatchFailed
{
    public function __construct(
        public readonly IssueDelivery $issueDelivery,
        public readonly string        $reason,
    )
    {
    }
}