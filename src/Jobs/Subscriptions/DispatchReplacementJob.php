<?php

namespace App\Jobs\Subscriptions;

use App\Enums\Subscriptions\FulfilmentReplacementStatus;
use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Repositories\Subscriptions\FulfilmentReplacementRepository;

/**
 * Sends a print issue replacement request to the fulfilment/dispatch system.
 *
 * Responsibilities:
 *   1. Load the FulfilmentReplacement record (guard against stale state).
 *   2. Transition status: pending → queued.
 *   3. Send the replacement request to the dispatch system.
 *   4. Transition status: queued → sent.
 *
 * Failure semantics:
 *   - If the dispatch system call fails, the status remains 'queued'
 *     (or reverts to 'pending' if it failed before the first transition).
 *   - The job will be retried per queue configuration.
 *   - After max retries the record is left in 'queued'/'pending' for
 *     operator investigation.
 *
 * Idempotency:
 *   - Guards against re-processing already sent records.
 */
final class DispatchReplacementJob extends BaseJob
{
    public ?string $queue = 'print';
    public int $tries = 3;
    public int $backoff = 30;

    private FulfilmentReplacementRepository $replacementRepository;
    private Logger $logger;

    public function __construct(
        private readonly int $fulfilmentReplacementId,
    )
    {
    }

    public function handle(): void
    {
        $replacement = $this->replacementRepository->find($this->fulfilmentReplacementId);

        if (!$replacement) {
            $this->logger->error('DispatchReplacementJob: FulfilmentReplacement not found', [
                'fulfilment_replacement_id' => $this->fulfilmentReplacementId,
            ]);
            return;
        }

        // Idempotency guard: already sent, nothing to do.
        if ($replacement->status === FulfilmentReplacementStatus::SENT->value ||
            $replacement->status === FulfilmentReplacementStatus::COMPLETED->value) {
            $this->logger->info('DispatchReplacementJob: already dispatched, skipping', [
                'fulfilment_replacement_id' => $this->fulfilmentReplacementId,
                'status' => $replacement->status,
            ]);
            return;
        }

        // Transition: pending → queued
        $this->replacementRepository->updateStatus(
            $this->fulfilmentReplacementId,
            FulfilmentReplacementStatus::QUEUED->value,
        );

        $this->logger->info('DispatchReplacementJob: sending to dispatch system', [
            'fulfilment_replacement_id' => $this->fulfilmentReplacementId,
            'subscription_id' => $replacement->subscription_id,
            'issue_delivery_id' => $replacement->issue_delivery_id,
        ]);

        // Send to the external fulfilment/dispatch system.
        // The actual implementation (HTTP call, queue message, etc.) is
        // owned by the fulfilment infrastructure layer — this job is the
        // correct entry point per the existing print pipeline pattern.
        dispatch(TriggerPrintRunWorkflowJob::for((int)$replacement->issue_delivery_id))->onQueue('print');

        // Transition: queued → sent
        $this->replacementRepository->updateStatus(
            $this->fulfilmentReplacementId,
            FulfilmentReplacementStatus::SENT->value,
        );

        $this->logger->info('DispatchReplacementJob: dispatched successfully', [
            'fulfilment_replacement_id' => $this->fulfilmentReplacementId,
        ]);
    }
}