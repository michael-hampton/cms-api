<?php

declare(strict_types=1);

namespace App\Jobs\Subscriptions;

use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Services\Subscriptions\BackIssue\BackIssueReplacementCopyDispatchService;

/**
 * Runs BackIssueReplacementCopyDispatchService on a schedule. Every run
 * extracts whatever BACK_ISSUE fulfilments are currently outstanding — there
 * is no batch/cursor state to track between runs, so this is safe to run as
 * often as needed (e.g. alongside GenerateLabelRunsJob) without risk of
 * double-dispatching a row already marked fulfilled.
 */
class DispatchBackIssueReplacementCopiesJob extends BaseJob
{
    public ?string $queue = 'print';
    public int $tries = 3;

    private BackIssueReplacementCopyDispatchService $dispatchService;
    private Logger $logger;

    public function handle(): void
    {
        $count = $this->dispatchService->dispatch();

        $this->logger->info('DispatchBackIssueReplacementCopiesJob: run complete', [
            'dispatched' => $count,
        ]);
    }
}
