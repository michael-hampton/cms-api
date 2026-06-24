<?php

declare(strict_types=1);

namespace App\Events\Subscriptions;

use App\Models\IssueDelivery;

/**
 * Fired after subscriber fulfilments for a plan issue have been planned and
 * print fulfilment rows have been atomically claimed with `dispatched_at`.
 *
 * Listeners:
 *   - IssueDeliveryDispatchedListener  (triggers PrintRunWorkflow for print subscriptions)
 *
 * This event is the seam between the issue-delivery planner and the print
 * fulfilment pipeline. Keeping them decoupled here means the planner has no
 * knowledge of print runs, batching or label generation.
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
