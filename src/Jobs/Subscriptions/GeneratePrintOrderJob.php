<?php

declare(strict_types=1);

namespace App\Jobs\Subscriptions;

use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Services\Subscriptions\PrintOrder\PrintOrderService;

/**
 * Generates the print order (copy counts) for a single IssueDelivery.
 *
 * Dispatched by AllFulfilmentsCreatedListener (or equivalent) after
 * CreateFulfilmentsChunkJob workers have completed Phase 1, so the
 * subscriber set is stable before copy counts are calculated.
 *
 * This job is intentionally separate from fulfilment creation — it is a
 * printer-facing document, not a delivery record.
 *
 * Idempotency:
 *   PrintOrderService::generate() throws if print_order_done is already true,
 *   which prevents double-counting on accidental re-dispatch.
 *
 * Retries:
 *   3 attempts with 60-second back-off covers transient DB blips without
 *   risking a double-generation on a fast retry.
 */
final class GeneratePrintOrderJob extends BaseJob
{
    public ?string $queue  = 'print';
    public int     $tries  = 3;
    public int     $backoff = 60;

    private IssueDeliveryRepository $issueDeliveryRepository;
    private PrintOrderService       $printOrderService;
    private Logger                  $logger;

    public function __construct(
        public readonly int $issueDeliveryId,
    ) {}

    public function handle(): void
    {
        $issueDelivery = $this->issueDeliveryRepository->find($this->issueDeliveryId);

        if (!$issueDelivery) {
            $this->logger->error('GeneratePrintOrderJob: IssueDelivery not found', [
                'issue_delivery_id' => $this->issueDeliveryId,
            ]);
            return;
        }

        // Guard: already done — idempotent exit without throwing.
        if ($issueDelivery->print_order_done) {
            $this->logger->info('GeneratePrintOrderJob: already done, skipping', [
                'issue_delivery_id' => $this->issueDeliveryId,
            ]);
            return;
        }

        // Guard: no print_order_date means this issue is not managed by the
        // print order workflow at all — skip silently.
        if (empty($issueDelivery->print_order_date)) {
            $this->logger->info('GeneratePrintOrderJob: no print_order_date, skipping', [
                'issue_delivery_id' => $this->issueDeliveryId,
            ]);
            return;
        }

        try {
            $result = $this->printOrderService->generate($issueDelivery);

            $this->logger->info('GeneratePrintOrderJob: complete', [
                'issue_delivery_id' => $this->issueDeliveryId,
                'subscriber_total'  => $result->totalSubscriberCopies(),
                'is_regional'       => $result->isRegional(),
            ]);
        } catch (\RuntimeException $e) {
            // RuntimeException from PrintOrderService means the issue is
            // ineligible (already done, missing date, etc.) — do not retry.
            $this->logger->warning('GeneratePrintOrderJob: ineligible, will not retry', [
                'issue_delivery_id' => $this->issueDeliveryId,
                'reason'            => $e->getMessage(),
            ]);
            $this->fail($e);
        }
    }
}