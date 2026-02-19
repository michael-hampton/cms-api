<?php

namespace App\Services\Adverts\Boost;

use App\Enums\Boost\BoostableType;
use App\Enums\Boost\BoostContext;

class BoostPricingService
{
    private array $baseRatesPerDay;
    private array $typeMultipliers;

    public function __construct()
    {
        $this->baseRatesPerDay = config('boost.base_rates');
        $this->typeMultipliers = config('boost.type_multipliers');
    }

    public function calculate(
        string             $boostableType,
        string             $context,
        \DateTimeInterface $startsAt,
        \DateTimeInterface $endsAt,
        ?array             $campaignOverride = null
    ): float
    {
        $this->assertValidContext($context);
        $this->assertValidBoostableType($boostableType);

        $days = $this->calculateDays($startsAt, $endsAt);

        if ($days <= 0) {
            throw new \InvalidArgumentException('Boost duration must be greater than zero.');
        }

        if ($campaignOverride !== null && isset($campaignOverride['fixed_price'])) {
            return (float)$campaignOverride['fixed_price'];
        }

        $baseRate = $this->baseRatesPerDay[$context];
        $typeMultiplier = $this->typeMultipliers[$boostableType];
        $price = $baseRate * $days * $typeMultiplier;

        if ($campaignOverride !== null && isset($campaignOverride['discount_percent'])) {
            $discount = max(0, min(100, (float)$campaignOverride['discount_percent']));
            $price = $price * (1 - $discount / 100);
        }

        return round($price, 2);
    }

    private function assertValidContext(string $context): void
    {
        BoostContext::from($context);
    }

    private function assertValidBoostableType(string $boostableType): void
    {
        BoostableType::from($boostableType);
    }

    private function calculateDays(\DateTimeInterface $startsAt, \DateTimeInterface $endsAt): int
    {
        $diff = $startsAt->diff($endsAt);
        $hours = ($diff->days * 24) + $diff->h;

        return (int)ceil($hours / 24);
    }
}