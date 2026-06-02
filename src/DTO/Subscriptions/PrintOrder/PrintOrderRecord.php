<?php

declare(strict_types=1);

namespace App\DTO\Subscriptions\PrintOrder;

/**
 * Complete print order for one issue or one regional edition of an issue.
 *
 * For non-regional issues, regional_edition_id is null and this record
 * covers the entire print run.
 *
 * For regional issues, one PrintOrderRecord is produced per region and
 * regional_edition_id identifies the IssueDeliveryRegion row.
 */
final class PrintOrderRecord
{
    public function __construct(
        public readonly int              $issueDeliveryId,
        public readonly ?int             $regionalEditionId,
        public readonly PrintOrderLine   $ukLine,
        public readonly PrintOrderLine   $exportLine,
    ) {}

    /** Total subscriber copies across UK + Export (no surplus). */
    public function subscriberTotal(): int
    {
        return $this->ukLine->subscriberCopies + $this->exportLine->subscriberCopies;
    }

    /** Grand total including surplus, across both regions. */
    public function grandTotal(): int
    {
        return $this->ukLine->total() + $this->exportLine->total();
    }

    public function isRegional(): bool
    {
        return $this->regionalEditionId !== null;
    }

    public function toArray(): array
    {
        return [
            'issue_delivery_id'   => $this->issueDeliveryId,
            'regional_edition_id' => $this->regionalEditionId,
            'uk'                  => $this->ukLine->toArray(),
            'export'              => $this->exportLine->toArray(),
            'subscriber_total'    => $this->subscriberTotal(),
            'grand_total'         => $this->grandTotal(),
        ];
    }
}