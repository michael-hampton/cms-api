<?php

declare(strict_types=1);

namespace App\Events\Products;

use App\Models\ProductFulfilmentRun;

/**
 * Fired by ProductFulfilmentMonitorJob when a ProductFulfilmentRun has been
 * stuck in Fulfilling status beyond the safety window.
 *
 * Listeners must send an ops alert. No automated recovery is attempted.
 */
final class ProductFulfilmentStalled
{
    public function __construct(
        public readonly ProductFulfilmentRun $fulfilmentRun,
        public readonly int                  $completedChunks,
        public readonly int                  $totalChunks,
        public readonly int                  $monitorDelayMinutes,
    )
    {
    }

    public function missingChunks(): int
    {
        return $this->totalChunks - $this->completedChunks;
    }
}