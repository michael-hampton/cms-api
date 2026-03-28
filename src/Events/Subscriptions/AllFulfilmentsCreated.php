<?php

declare(strict_types=1);

namespace App\Events\Subscriptions;

use App\Models\PrintRun;

/**
 * Fired when all CreateFulfilmentsChunkJob workers have completed
 * and the PrintRun's fulfilled_chunks_count equals total_chunks.
 *
 * This is the Phase 1 → Phase 2 barrier signal.
 *
 * Listeners:
 *   - AllFulfilmentsCreatedListener → dispatches BuildPrintBatchesJob
 *
 * The last chunk job to complete fires this event atomically after
 * incrementing fulfilled_chunks_count. No polling required.
 */
final class AllFulfilmentsCreated
{
    public function __construct(
        public readonly PrintRun $printRun,
        public readonly int      $totalFulfilments,
    )
    {
    }
}