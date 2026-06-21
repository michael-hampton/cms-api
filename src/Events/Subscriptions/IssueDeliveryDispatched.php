<?php

declare(strict_types=1);

namespace App\Events\Subscriptions;

use App\Models\IssueDelivery;

/**
 * Fired by GenerateIssueDeliveriesJob after it has:
 *   - Created all SubscriptionIssueFulfilment records for eligible subscriptions
 *   - Marked the IssueDelivery status as DISPATCHED
 *
 * Listeners:
 *   - IssueDeliveryDispatchedListener  (triggers PrintRunWorkflow for print subscriptions)
 *
 * This event is the seam between the delivery pipeline and the print
 * fulfilment pipeline. Keeping them decoupled here means the delivery
 * job has no knowledge of printing.
 */
final class IssueDeliveryDispatched
{
    public function __construct(
        public readonly IssueDelivery $issueDelivery,
        public readonly int           $eligibleCount,
        public readonly int           $createdCount,
        public readonly int           $skippedCount,
    )
    {
    }
}