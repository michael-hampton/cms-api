<?php

namespace App\DTO\Pricing;

use DateTimeImmutable;

final readonly class PriceDisclosureContext
{
    public function __construct(
        public string $locale,
        public string $currency,
        public int $quantity,
        public int $unitAmountMinor,
        public string $billingPeriod,
        public int $billingInterval = 1,
        public ?int $trialDays = null,
        public ?DateTimeImmutable $renewalDate = null,
        public bool $isRecurring = true,
        public array $copyOverrides = [],
        public array $formatterSettings = [],
    ) {
    }

    public function lineAmountMinor(): int
    {
        return $this->unitAmountMinor * max(1, $this->quantity);
    }
}
