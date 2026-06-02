<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\PrintOrder;

use App\DTO\Subscriptions\PrintOrder\PrintOrderResult;
use App\Events\Subscriptions\PrintOrderGenerated;
use App\Framework\Support\Logger;
use App\Models\IssueDelivery;
use App\Repositories\Subscriptions\IssueDeliveryRegionRepository;
use App\Repositories\Subscriptions\PrintOrderRepository;

/**
 * Orchestrates print order generation for a single IssueDelivery.
 *
 * Responsibilities:
 *   1. Verify the issue is eligible (not already done, has print_order_date).
 *   2. Detect whether the issue has regional editions.
 *   3. Count subscribers per region (regional or non-regional path).
 *   4. Delegate calculation to PrintOrderCalculator.
 *   5. Persist the aggregate subscriber total and mark the issue as done.
 *   6. Emit PrintOrderGenerated event.
 *
 * This service does NOT:
 *   - Create fulfilment records (that is CreatePrintFulfillmentsJob).
 *   - Access sessions, requests, or globals.
 *   - Format data for presentation.
 *   - Call Stripe or any external API.
 */
final class PrintOrderService
{
    public function __construct(
        private readonly PrintOrderRepository        $repository,
        private readonly IssueDeliveryRegionRepository $regionRepository,
        private readonly PrintOrderCalculator        $calculator,
        private readonly Logger                      $logger,
    ) {}

    /**
     * Generate (or re-generate) the print order for a single IssueDelivery.
     *
     * @throws \RuntimeException When the issue is ineligible.
     */
    public function generate(IssueDelivery $issueDelivery): PrintOrderResult
    {
        $this->assertEligible($issueDelivery);

        $this->logger->info('PrintOrderService: generating print order', [
            'issue_delivery_id' => $issueDelivery->id,
            'print_order_date'  => $issueDelivery->print_order_date,
        ]);

        $editions = $this->regionRepository->findByIssueDelivery($issueDelivery->id);
        $isRegional = $editions->isNotEmpty();

        $result = $isRegional
            ? $this->generateRegional($issueDelivery, $editions)
            : $this->generateNonRegional($issueDelivery);

        $subscriberTotal = $result->totalSubscriberCopies();

        $this->repository->markPrintOrderDone($issueDelivery, $subscriberTotal);

        event(new PrintOrderGenerated($issueDelivery, $result));

        $this->logger->info('PrintOrderService: print order complete', [
            'issue_delivery_id' => $issueDelivery->id,
            'is_regional'       => $isRegional,
            'subscriber_total'  => $subscriberTotal,
            'record_count'      => count($result->records),
        ]);

        return $result;
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function assertEligible(IssueDelivery $issueDelivery): void
    {
        if (empty($issueDelivery->print_order_date)) {
            throw new \RuntimeException(
                "IssueDelivery #{$issueDelivery->id} has no print_order_date set."
            );
        }

        if ($issueDelivery->print_order_done) {
            throw new \RuntimeException(
                "IssueDelivery #{$issueDelivery->id} print order has already been generated."
            );
        }
    }

    private function generateNonRegional(IssueDelivery $issueDelivery): PrintOrderResult
    {
        $counts = $this->repository->countSubscribersByRegion($issueDelivery);

        return $this->calculator->calculateNonRegional(
            issueDeliveryId:  $issueDelivery->id,
            ukSubscribers:    $counts['uk'],
            exportSubscribers: $counts['export'],
            printOverrun:     (int) ($issueDelivery->print_overrun    ?? 0),
            additionalStock:  (int) ($issueDelivery->additional_stock ?? 0),
            exportOverrun:    (int) ($issueDelivery->export_overrun   ?? 0),
        );
    }

    private function generateRegional(IssueDelivery $issueDelivery, $editions): PrintOrderResult
    {
        $regionData = [];

        foreach ($editions as $edition) {
            $counts = $this->repository->countSubscribersByRegionForEdition(
                $issueDelivery,
                $edition,
            );

            $regionData[] = [
                'regional_edition_id' => $edition->id,
                'uk_subscribers'      => $counts['uk'],
                'export_subscribers'  => $counts['export'],
                'uk_surplus'          => (int) ($edition->uk_surplus     ?? 0),
                'export_surplus'      => (int) ($edition->export_surplus ?? 0),
            ];
        }

        return $this->calculator->calculateRegional(
            issueDeliveryId: $issueDelivery->id,
            regionData:      $regionData,
        );
    }
}