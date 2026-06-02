<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\PrintOrder;

use App\DTO\Subscriptions\PrintOrder\PrintOrderLine;
use App\DTO\Subscriptions\PrintOrder\PrintOrderRecord;
use App\DTO\Subscriptions\PrintOrder\PrintOrderResult;
use App\Enums\Subscriptions\PrintRegion;

/**
 * Pure calculation collaborator: converts raw subscriber counts and surplus
 * figures into structured PrintOrderResult DTOs.
 *
 * No database access. No side effects. Single reason to change: the
 * rules for how copy counts and surplus are combined.
 *
 * Surplus rules
 * ─────────────
 * Non-regional:
 *   UK surplus   = print_overrun + additional_stock   (both from issue)
 *   Export surplus = export_overrun                   (from issue)
 *
 * Regional:
 *   UK surplus   = uk_surplus    (from the IssueDeliveryRegion record)
 *   Export surplus = export_surplus (from the IssueDeliveryRegion record)
 */
class PrintOrderCalculator
{
    /**
     * Calculate a non-regional print order.
     *
     * @param int $issueDeliveryId
     * @param int $ukSubscribers      UK-addressed subscribers for this issue.
     * @param int $exportSubscribers  Non-UK-addressed subscribers for this issue.
     * @param int $printOverrun       Overrun copies (UK), from issue.
     * @param int $additionalStock    Additional stock (UK), from issue.
     * @param int $exportOverrun      Overrun copies (Export), from issue.
     */
    public function calculateNonRegional(
        int $issueDeliveryId,
        int $ukSubscribers,
        int $exportSubscribers,
        int $printOverrun,
        int $additionalStock,
        int $exportOverrun,
    ): PrintOrderResult {
        $ukSurplus     = $printOverrun + $additionalStock;
        $exportSurplus = $exportOverrun;

        $record = new PrintOrderRecord(
            issueDeliveryId:   $issueDeliveryId,
            regionalEditionId: null,
            ukLine: new PrintOrderLine(
                region:           PrintRegion::UK,
                subscriberCopies: $ukSubscribers,
                surplus:          $ukSurplus,
            ),
            exportLine: new PrintOrderLine(
                region:           PrintRegion::Export,
                subscriberCopies: $exportSubscribers,
                surplus:          $exportSurplus,
            ),
        );

        return new PrintOrderResult(
            issueDeliveryId: $issueDeliveryId,
            records:         [$record],
        );
    }

    /**
     * Calculate a regional print order.
     *
     * Each element of $regionData represents one regional edition and must
     * contain the following keys:
     *   regional_edition_id (int)
     *   uk_subscribers      (int)
     *   export_subscribers  (int)
     *   uk_surplus          (int)
     *   export_surplus      (int)
     *
     * @param int   $issueDeliveryId
     * @param array $regionData  Array of per-region data arrays (see above).
     */
    public function calculateRegional(
        int   $issueDeliveryId,
        array $regionData,
    ): PrintOrderResult {
        $records = [];

        foreach ($regionData as $region) {
            $records[] = new PrintOrderRecord(
                issueDeliveryId:   $issueDeliveryId,
                regionalEditionId: (int) $region['regional_edition_id'],
                ukLine: new PrintOrderLine(
                    region:           PrintRegion::UK,
                    subscriberCopies: (int) $region['uk_subscribers'],
                    surplus:          (int) $region['uk_surplus'],
                ),
                exportLine: new PrintOrderLine(
                    region:           PrintRegion::Export,
                    subscriberCopies: (int) $region['export_subscribers'],
                    surplus:          (int) $region['export_surplus'],
                ),
            );
        }

        return new PrintOrderResult(
            issueDeliveryId: $issueDeliveryId,
            records:         $records,
        );
    }
}