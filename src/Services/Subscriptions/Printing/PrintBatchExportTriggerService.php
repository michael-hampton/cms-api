<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Printing;

use App\Framework\Queue\Dispatcher;
use App\Jobs\Subscriptions\ExportPrintBatchJob;
use App\Models\PrintBatch;

/**
 * Manually (re-)triggers export for a single PrintBatch, via the API.
 *
 * State validation (PrintBatch::canTriggerExport()) is the controller's
 * responsibility — this service assumes it has already been checked and
 * focuses solely on the dispatch workflow, so it stays independently
 * testable and reusable outside the HTTP layer.
 *
 * Mirrors the async dispatch already used by PrintDeliveryChannel — export
 * itself (and its idempotency guard) lives in PrintBatchExportService,
 * reached via the queued ExportPrintBatchJob.
 */
class PrintBatchExportTriggerService
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
    )
    {
    }

    public function trigger(PrintBatch $batch): void
    {
        $this->dispatcher
            ->dispatch(ExportPrintBatchJob::for($batch->id, $batch->issue_delivery_id))
            ->onQueue('print');
    }
}
