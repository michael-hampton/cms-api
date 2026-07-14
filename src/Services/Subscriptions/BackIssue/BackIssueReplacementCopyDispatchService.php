<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\BackIssue;

use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Framework\Support\Logger;
use App\Models\PrintFulfillment;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\PrintBatchRepository;
use App\Repositories\Subscriptions\PrintFulfillmentRepository;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;
use App\Services\Subscriptions\Printing\Format\PrintExportFormatStrategy;
use App\Services\Subscriptions\Printing\Transport\PrintExportTransport;

/**
 * The "replacement copy process": on every run, extracts whatever BACK_ISSUE
 * fulfilments are currently outstanding and sends them to the vendor as
 * replacement copies of an already-printed issue. This is what makes
 * back-issue orders dispatchable at all — they belong to issues whose Label
 * Run has already completed, so GenerateLabelRunsJob will never process
 * them, and IssueFulfilmentPlanner refuses to claim them for the normal
 * print run (see its own back-issue guard).
 *
 * Reuses the same PrintFulfillment records (name, resolved delivery address,
 * territory) and the same PrintExportFormatStrategy/PrintExportTransport the
 * standard print pipeline uses — a vendor gets an identical file shape
 * either way, just via a different trigger. BackIssueOrderService is
 * responsible for creating those PrintFulfillment rows up front.
 *
 * Extracted rows are grouped by their existing batch (one PrintExportFormatStrategy
 * call, and one uploaded file, per batch — mirroring PrintBatchExportService)
 * because the format strategy expects a single batch id and a single issue
 * per call. Only the specific rows extracted this run are marked exported —
 * NOT the whole batch — because that batch may also contain unrelated
 * standard rows still awaiting the normal Label Run export.
 *
 * Money/order-critical flow: a failed vendor upload for a batch group throws
 * and nothing in that group is written back. Other groups already uploaded
 * in this run remain committed — there is no cross-group rollback, since
 * each group is an independent file sent to the vendor.
 */
class BackIssueReplacementCopyDispatchService
{
    public function __construct(
        private readonly SubscriptionIssueFulfilmentRepository $fulfilmentRepository,
        private readonly PrintFulfillmentRepository $printFulfillmentRepository,
        private readonly PrintBatchRepository $printBatchRepository,
        private readonly IssueDeliveryRepository $issueDeliveryRepository,
        private readonly PrintExportFormatStrategy $formatStrategy,
        private readonly PrintExportTransport $transport,
        private readonly Database $database,
        private readonly Logger $logger,
        private readonly int $maxBatchSize = 5000,
    ) {
    }

    /**
     * @return int Number of fulfilments dispatched and marked fulfilled this run.
     */
    public function dispatch(): int
    {
        $outstanding = $this->fulfilmentRepository->findUnfulfilledBackIssues($this->maxBatchSize);

        if ($outstanding->isEmpty()) {
            $this->logger->info('BackIssueReplacementCopyDispatchService: nothing to dispatch');

            return 0;
        }

        $printFulfilments = $this->printFulfillmentRepository->findBySubscriptionIssueFulfilmentIds(
            $outstanding->map(fn ($fulfilment) => (int) $fulfilment->id)->all()
        );

        $outstandingBySubscriptionIssueFulfilmentId = [];
        foreach ($outstanding as $fulfilment) {
            $outstandingBySubscriptionIssueFulfilmentId[(int) $fulfilment->id] = $fulfilment;
        }

        $missingPrintFulfilment = array_diff(
            array_keys($outstandingBySubscriptionIssueFulfilmentId),
            $printFulfilments->map(fn (PrintFulfillment $pf) => (int) $pf->subscription_issue_fulfilment_id)->all(),
        );

        foreach ($missingPrintFulfilment as $id) {
            // Digital-delivery back-issue orders never get a PrintFulfillment
            // (there is nothing to physically dispatch) — that is expected
            // and not logged as an error. Anything else missing a
            // PrintFulfillment here indicates BackIssueOrderService failed
            // to create one; it is skipped this run rather than blocking
            // the rest of the batch, and will be retried on the next run.
            $this->logger->info('BackIssueReplacementCopyDispatchService: no PrintFulfillment for row, skipping this run', [
                'subscription_issue_fulfilment_id' => $id,
            ]);
        }

        $dispatched = 0;

        foreach ($printFulfilments->groupBy('batch_id') as $batchId => $rows) {
            $dispatched += $this->dispatchBatchGroup((int) $batchId, $rows, $outstandingBySubscriptionIssueFulfilmentId);
        }

        return $dispatched;
    }

    /**
     * @param Collection<PrintFulfillment> $rows
     * @param array<int, \App\Models\SubscriptionIssueFulfilment> $outstandingBySubscriptionIssueFulfilmentId
     */
    private function dispatchBatchGroup(int $batchId, Collection $rows, array $outstandingBySubscriptionIssueFulfilmentId): int
    {
        $batch = $this->printBatchRepository->find($batchId);

        if (!$batch) {
            $this->logger->error('BackIssueReplacementCopyDispatchService: batch not found, skipping group', [
                'batch_id' => $batchId,
            ]);

            return 0;
        }

        $issue = $this->issueDeliveryRepository->find((int) $batch->issue_delivery_id);

        if (!$issue) {
            $this->logger->error('BackIssueReplacementCopyDispatchService: issue delivery not found, skipping group', [
                'batch_id' => $batchId,
                'issue_delivery_id' => $batch->issue_delivery_id,
            ]);

            return 0;
        }

        $fulfilmentsArray = $rows->all();

        $issueSnapshot = [
            'id' => $issue->id,
            'title' => $issue->issue_title ?? null,
        ];

        $contents = $this->formatStrategy->generate($batchId, $fulfilmentsArray, $issueSnapshot);
        $filename = $this->buildFilename($batchId);

        $this->logger->info('BackIssueReplacementCopyDispatchService: dispatch started', [
            'batch_id' => $batchId,
            'filename' => $filename,
            'count' => count($fulfilmentsArray),
        ]);

        try {
            $this->transport->upload($filename, $contents);
        } catch (\Throwable $e) {
            $this->logger->error('BackIssueReplacementCopyDispatchService: vendor upload failed', [
                'batch_id' => $batchId,
                'filename' => $filename,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $fulfilledAt = new \DateTime();
        $printFulfilmentIds = array_map(fn (PrintFulfillment $pf) => (int) $pf->id, $fulfilmentsArray);

        return $this->database->transaction(function () use (
            $fulfilmentsArray,
            $printFulfilmentIds,
            $fulfilledAt,
            $batchId,
            $filename,
            $outstandingBySubscriptionIssueFulfilmentId,
        ) {
            $this->printFulfillmentRepository->markExported($printFulfilmentIds);

            foreach ($fulfilmentsArray as $printFulfilment) {
                $subscriptionIssueFulfilmentId = (int) $printFulfilment->subscription_issue_fulfilment_id;

                if (!isset($outstandingBySubscriptionIssueFulfilmentId[$subscriptionIssueFulfilmentId])) {
                    continue;
                }

                $this->fulfilmentRepository->markFulfilled($subscriptionIssueFulfilmentId, $fulfilledAt);
            }

            $this->logger->info('BackIssueReplacementCopyDispatchService: dispatch completed', [
                'batch_id' => $batchId,
                'filename' => $filename,
                'count' => count($fulfilmentsArray),
            ]);

            return count($fulfilmentsArray);
        });
    }

    private function buildFilename(int $batchId): string
    {
        $timestamp = (new \DateTimeImmutable())->format('Ymd_His');
        $extension = $this->formatStrategy->extension();

        return "back_issue_replacement_copies_batch_{$batchId}_{$timestamp}.{$extension}";
    }
}
