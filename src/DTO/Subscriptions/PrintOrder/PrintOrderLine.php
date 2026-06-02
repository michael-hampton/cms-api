<?php

declare(strict_types=1);

namespace App\DTO\Subscriptions\PrintOrder;

use App\Enums\Subscriptions\PrintRegion;

/**
 * Immutable counts for one UK-or-Export line within a print order record.
 *
 * subscriber_copies  — actual subscriptions scheduled to receive the issue.
 * surplus            — extra copies (overrun + additional stock for UK; overrun for Export).
 * total              — subscriber_copies + surplus.
 */
final class PrintOrderLine
{
    public function __construct(
        public readonly PrintRegion $region,
        public readonly int         $subscriberCopies,
        public readonly int         $surplus,
    ) {}

    public function total(): int
    {
        return $this->subscriberCopies + $this->surplus;
    }

    public function toArray(): array
    {
        return [
            'region'            => $this->region->value,
            'region_label'      => $this->region->label(),
            'subscriber_copies' => $this->subscriberCopies,
            'surplus'           => $this->surplus,
            'total'             => $this->total(),
        ];
    }
}