<?php

namespace App\Services\Adverts\Boost;

use App\Contracts\Boost\BoostableInterface;
use App\Enums\Boost\BoostableType;
use App\Repositories\Adverts\Boost\BoostRepository;
use App\Repositories\Offers\ProductOfferRepository;
use App\Repositories\Product\MerchantRepository;
use App\Repositories\Product\ProductRepository;

class BoostEligibilityService
{
    public function __construct(
        private readonly BoostRepository        $boostRepository,
        private readonly ProductRepository      $productRepository,
        private readonly ProductOfferRepository $offerRepository,
        private readonly MerchantRepository     $merchantRepository
    )
    {
    }

    /**
     * @throws \RuntimeException
     */
    public function assertEligible(BoostableInterface $target, string $boostableType, int $merchantId): void
    {
        $this->assertTargetIsActive($target);
        $this->assertTargetIsInStock($target);
        $this->assertMerchantIsCompliant($merchantId);
        $this->assertNoConflictingBoost($boostableType, $target->getBoostableId());
        $this->assertTypeSpecificRules($target, $boostableType);
    }

    private function assertTargetIsActive(BoostableInterface $target): void
    {
        if (!$target->isEligibleForBoost()) {
            throw new \RuntimeException('Boost target is not active or eligible.');
        }
    }

    private function assertTargetIsInStock(BoostableInterface $target): void
    {
        if (!$target->isInStock()) {
            throw new \RuntimeException('Boost target is out of stock.');
        }
    }

    private function assertMerchantIsCompliant(int $merchantId): void
    {
        $merchant = $this->merchantRepository->find($merchantId);

        if (!$merchant || !$merchant->is_active) {
            throw new \RuntimeException('Merchant is not active or compliant.');
        }
    }

    private function assertNoConflictingBoost(string $boostableType, int $boostableId): void
    {
        if ($this->boostRepository->hasActiveBoost($boostableType, $boostableId)) {
            throw new \RuntimeException('An active boost already exists for this target.');
        }
    }

    private function assertTypeSpecificRules(BoostableInterface $target, string $boostableType): void
    {
        match ($boostableType) {
            BoostableType::Product->value => $this->assertProductRules($target),
            BoostableType::Offer->value => $this->assertOfferRules($target),
            default => throw new \RuntimeException("Unknown boostable type: {$boostableType}"),
        };
    }

    private function assertProductRules(BoostableInterface $target): void
    {
        // Product-specific rules: active + in stock already enforced above.
        // Extend here as domain evolves (e.g. minimum price threshold).
    }

    private function assertOfferRules(BoostableInterface $target): void
    {
        // Offer-specific: isEligibleForBoost() calls isCurrentlyActive() on ProductOffer.
        // If we need to check expiry explicitly, do it here.
        if (!$target->isEligibleForBoost()) {
            throw new \RuntimeException('Offer has expired or is not currently active.');
        }
    }
}