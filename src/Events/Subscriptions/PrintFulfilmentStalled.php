<?php

declare(strict_types=1);

namespace App\Events\Subscriptions;

use App\Models\PrintRun;

/**
 * Fired by FulfilmentCompletionMonitorJob when a PrintRun has been
 * in Fulfilling status beyond the safety window and its chunk counter
 * has not reached total_chunks.
 *
 * This is an operator alert — no automated recovery is attempted.
 * The operator must investigate which chunks failed and re-dispatch
 * or resolve the stall manually.
 *
 * Listeners should:
 *   - Send an alert to the ops channel (Slack, PagerDuty, etc.)
 *   - Record the stall in an observability store
 *
 * Every event must have at least one listener before shipping.
 */
final class PrintFulfilmentStalled
{
    public function __construct(
        public readonly PrintRun $printRun,
        public readonly int      $completedChunks,
        public readonly int      $totalChunks,
        public readonly int      $monitorDelayMinutes,
        public array $missingChunkIndexes = []
    )
    {
    }

    public function missingChunks(): int
    {
        return $this->totalChunks - $this->completedChunks;
    }
}