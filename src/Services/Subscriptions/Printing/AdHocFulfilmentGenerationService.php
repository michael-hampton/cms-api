<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Printing;

use App\Events\Subscriptions\AdHocFulfilmentFileRequested;
use App\Framework\Database\Database;
use App\Models\AdHocFulfilmentRequest;
use App\Repositories\Subscriptions\AdHocFulfilmentRequestRepository;
use App\Repositories\Subscriptions\PrintBatchRepository;
use InvalidArgumentException;
use RuntimeException;

/**
 * Orchestrates a manually (ad-hoc) requested fulfilment file generation.
 *
 * Phase 1 wraps the existing PrintBatch export pipeline: state validation
 * stays on PrintBatch::canTriggerExport() (same rule the scheduled path
 * uses), the actual dispatch is delegated to PrintBatchExportTriggerService
 * unchanged, and this service's own job is limited to what's new here —
 * recording who asked for it and emitting the audit event. Nothing about
 * PrintBatch generation itself is duplicated or reimplemented.
 */
class AdHocFulfilmentGenerationService
{
    public function __construct(
        private readonly AdHocFulfilmentRequestRepository $requestRepository,
        private readonly PrintBatchRepository $printBatchRepository,
        private readonly PrintBatchExportTriggerService $exportTriggerService,
        private readonly Database $database,
    ) {
    }

    /**
     * @throws InvalidArgumentException When the batch does not exist.
     * @throws RuntimeException When the batch is not currently eligible for (re-)export.
     */
    public function generateForPrintBatch(int $printBatchId, int $requestedByUserId): AdHocFulfilmentRequest
    {
        $batch = $this->printBatchRepository->find($printBatchId);

        if (!$batch) {
            throw new InvalidArgumentException("Print batch #{$printBatchId} not found");
        }

        if (!$batch->canTriggerExport()) {
            throw new RuntimeException(
                "Print batch #{$printBatchId} cannot be exported in its current status: {$batch->status}"
            );
        }

        /** @var AdHocFulfilmentRequest $request */
        $request = $this->database->transaction(function () use ($batch, $requestedByUserId) {
            $request = $this->requestRepository->createForPrintBatch($batch->id, $requestedByUserId);

            $this->exportTriggerService->trigger($batch);

            return $request;
        });

        event(new AdHocFulfilmentFileRequested($request));

        return $request;
    }
}