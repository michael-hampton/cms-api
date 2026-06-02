<?php

declare(strict_types=1);

namespace App\DTO\Subscriptions\PrintOrder;

/**
 * The complete print order result for one IssueDelivery.
 *
 * Contains one PrintOrderRecord per regional edition (or a single record for
 * non-regional issues), plus the aggregate subscriber_total written back to
 * the issue.
 *
 * @property-read PrintOrderRecord[] $records
 */
final class PrintOrderResult
{
    /** @param PrintOrderRecord[] $records */
    public function __construct(
        public readonly int   $issueDeliveryId,
        public readonly array $records,
    ) {}

    /**
     * Sum of subscriber copies across all records (UK + Export, no surplus).
     * This is the value written back to issue_deliveries.subscription_total.
     */
    public function totalSubscriberCopies(): int
    {
        return array_sum(
            array_map(fn(PrintOrderRecord $r) => $r->subscriberTotal(), $this->records)
        );
    }

    public function isRegional(): bool
    {
        return count($this->records) > 1
            || (count($this->records) === 1 && $this->records[0]->isRegional());
    }

    public function toArray(): array
    {
        return [
            'issue_delivery_id'      => $this->issueDeliveryId,
            'is_regional'            => $this->isRegional(),
            'records'                => array_map(fn($r) => $r->toArray(), $this->records),
            'total_subscriber_copies'=> $this->totalSubscriberCopies(),
        ];
    }
}