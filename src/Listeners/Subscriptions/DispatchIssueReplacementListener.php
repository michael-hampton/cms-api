<?php

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\IssueReplacementRequested;
use App\Jobs\Subscriptions\DispatchReplacementJob;

/**
 * Dispatches a DispatchReplacementJob when a CRM agent raises a print issue
 * replacement request.
 *
 * Responsibility: event → job bridge only.
 * No business logic lives here — all decisions happen in DispatchReplacementJob.
 *
 * Failure behaviour: if the job dispatch fails (queue infrastructure issue),
 * the fulfilment_replacements record still exists in 'pending' status.
 * A monitor or retry mechanism can pick these up.
 */
final class DispatchIssueReplacementListener
{
    public function handle(IssueReplacementRequested $event): void
    {
        dispatch(
            DispatchReplacementJob::for($event->fulfilmentReplacementId)
        )->onQueue('print');
    }
}