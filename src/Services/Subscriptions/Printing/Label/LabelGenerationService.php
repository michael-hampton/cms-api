<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Printing\Label;

use App\Enums\Subscriptions\LabelExportFormat;
use App\Events\Subscriptions\LabelRunFailed;
use App\Events\Subscriptions\LabelRunGenerated;
use App\Framework\Support\Logger;
use App\Models\LabelRun;
use App\Repositories\Subscriptions\LabelRunRepository;
use App\Repositories\Subscriptions\PrintFulfillmentRepository;
use App\Services\Subscriptions\Printing\Transport\LocalLabelExportTransport;

/**
 * Orchestrates the generation of a single label file for one LabelRun.
 *
 * Responsibilities:
 *   1. Load the PrintFulfillment and IssueDelivery data.
 *   2. Resolve format strategy via LabelFormatStrategyRegistry.
 *   3. Transition LabelRun: pending → generating.
 *   4. Generate file contents via the format strategy.
 *   5. Upload via LabelExportTransport.
 *   6. Transition LabelRun: generating → complete | failed.
 *   7. Emit LabelRunGenerated or LabelRunFailed event.
 *
 * Single reason to change: the label generation orchestration steps.
 * Format logic lives in strategies. Transport logic lives in transport.
 *
 * This service does NOT:
 *   - Create LabelRun records (that is GenerateLabelRunsJob's job)
 *   - Know about batches or print runs
 *   - Access sessions or request globals
 */
class LabelGenerationService
{
    public function __construct(
        private readonly PrintFulfillmentRepository  $fulfillmentRepository,
        private readonly LabelRunRepository          $labelRunRepository,
        private readonly LabelFormatStrategyRegistry $formatRegistry,
        private readonly LocalLabelExportTransport   $transport,
        private readonly Logger                      $logger,
    )
    {
    }

    /**
     * Generate the label for a single LabelRun.
     *
     * Idempotent: already-complete runs are skipped silently.
     * Already-generating runs (from a crashed previous attempt) are
     * retried — the status is reset to generating and a new file written.
     *
     * @throws \Throwable Re-throws after marking the LabelRun failed,
     *                    so the queue worker can retry the job.
     */
    public function generate(LabelRun $labelRun): void
    {
        if ($labelRun->isComplete()) {
            $this->logger->info('LabelGenerationService: already complete, skipping', [
                'label_run_id' => $labelRun->id,
            ]);
            return;
        }

        $fulfillment = $this->fulfillmentRepository->find(
            $this->resolveFulfillmentId($labelRun)
        );

        if (!$fulfillment) {
            $this->failAndThrow(
                $labelRun,
                "PrintFulfillment not found for LabelRun #{$labelRun->id}"
            );
        }

        $issueDelivery = $labelRun->issuesDelivered(true)->first()?->issueDelivery(true)->first();

        $context = LabelContext::fromConfig(
            issueDeliveryId: $labelRun->issues_delivered_id,
            issueNumber: $issueDelivery?->issue_number,
            issueTitle: $issueDelivery?->issue_title,
        );


        $format = LabelExportFormat::from($labelRun->format);

        $strategy = $this->formatRegistry->get($format);

        //$labelRun->markGenerating();

        $this->logger->info('LabelGenerationService: generating', [
            'label_run_id' => $labelRun->id,
            'format' => $format->value,
            'fulfillment_id' => $fulfillment->id,
        ]);

        try {
            $contents = $strategy->generate($fulfillment, $context);

            $filename = $this->buildFilename($labelRun, $format);

            $this->transport->upload($filename, $contents);

            $labelRun->markComplete($filename, $this->transport->identifier());

            $this->logger->info('LabelGenerationService: complete', [
                'label_run_id' => $labelRun->id,
                'filename' => $filename,
                'transport' => $this->transport->identifier(),
            ]);

            event(new LabelRunGenerated($labelRun));

        } catch (\Throwable $e) {
            $this->failAndThrow($labelRun, $e->getMessage(), $e);
        }
    }

    // =========================================================================
    // Private
    // =========================================================================

    /**
     * Resolve the PrintFulfillment ID from the LabelRun.
     *
     * LabelRun links to IssuesDelivered, not directly to PrintFulfillment.
     * We find the fulfillment via issues_delivered_id + print_batch_id.
     */
    private function resolveFulfillmentId(LabelRun $labelRun): int
    {
        $fulfillment = $this->fulfillmentRepository->findByIssuesDeliveredAndBatch(
            $labelRun->issues_delivered_id,
            $labelRun->print_batch_id,
        );

        if (!$fulfillment) {
            throw new \RuntimeException(
                "No PrintFulfillment found for IssuesDelivered #{$labelRun->issues_delivered_id} "
                . "in PrintBatch #{$labelRun->print_batch_id}"
            );
        }

        return $fulfillment->id;
    }

    /**
     * Mark the LabelRun as failed, emit the event, then throw so the
     * queue worker retries the job at the correct backoff interval.
     *
     * @throws \Throwable Always.
     */
    private function failAndThrow(LabelRun $labelRun, string $reason, ?\Throwable $previous = null): never
    {
        $labelRun->markFailed($reason);

        $this->logger->error('LabelGenerationService: failed', [
            'label_run_id' => $labelRun->id,
            'reason' => $reason,
        ]);

        event(new LabelRunFailed($labelRun, $reason));

        throw $previous ?? new \RuntimeException($reason);
    }

    /**
     * Deterministic versioned filename.
     * Format: label_{labelRunId}_v{attempt}_{YYYYMMDD_HHmmss}.{ext}
     *
     * Examples:
     *   label_1234_v1_20260401_120000.pdf
     *   label_1234_v2_20260401_120030.pdf  ← retry
     */
    private function buildFilename(LabelRun $labelRun, LabelExportFormat $format): string
    {
        $timestamp = (new \DateTimeImmutable())->format('Ymd_His');

        return "label_{$labelRun->id}_v{$labelRun->attempt_count}_{$timestamp}.{$format->extension()}";
    }
}