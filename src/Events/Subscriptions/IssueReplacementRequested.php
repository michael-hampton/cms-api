<?php

declare(strict_types=1);

namespace App\Events\Subscriptions;

/**
 * Fired when a CRM agent requests a print issue replacement.
 *
 * Listeners are responsible for dispatching the physical replacement
 * to the fulfilment/dispatch system (e.g. DispatchReplacementJob).
 * The service layer never dispatches jobs directly.
 */
class IssueReplacementRequested
{
    public function __construct(
        public readonly int    $subscriptionId,
        public readonly int    $issueId,
        public readonly string $reason,
        public readonly int    $agentId,
        public readonly string $timestamp,
    )
    {
    }
}