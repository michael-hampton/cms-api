<?php

namespace App\DTO\Pricing;

use DateTimeImmutable;

final readonly class PriceDisclosureContext
{
    public function __construct(
        public string $locale,
        public string $currency,
        public int $quantity,
        public int $itemAmountMinor,
        public ?int $initialChargeAmountMinor,
        public ?int $renewalAmountMinor,
        public bool $isRecurring,
        public ?int $trialDays,
        public ?int $introCycles,
        public ?string $initialChargePeriodLabel,
        public ?string $introPeriodLabel,
        public ?string $renewalPeriodLabel,
        public ?DateTimeImmutable $renewalDate,
        public ?string $pricingLabel = null,
        public array $badges = [],
        public array $experienceLanguageLines = [],
        public array $storeLanguageLines = [],
        public ?string $rawPeriodLabel = null,
        public ?string $numericPeriodLabel = null,
        public ?string $wordedPeriodLabel = null,
    ) {
    }

    public function hasTrial(): bool
    {
        return $this->trialDays !== null && $this->trialDays > 0;
    }

    public function hasValidInitialCharge(): bool
    {
        return $this->initialChargeAmountMinor !== null
            && $this->initialChargeAmountMinor > 0
            && $this->initialChargePeriodLabel !== null;
    }
}
