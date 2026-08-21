<?php

namespace App\DTO\Subscriptions;

use App\Enums\Subscriptions\OfferType;

/**
 * Typed filter bag passed from the controller into the service/repository.
 * Keeps raw request data out of the service layer.
 */
final class SubscriptionOfferFilters
{
    public function __construct(
        public readonly ?string    $search          = null,
        public readonly ?int       $siteId          = null,
        public readonly ?int       $planId          = null,
        public readonly ?OfferType $offerType       = null,
        public readonly ?bool      $hasDiscount     = null,
        public readonly ?bool      $hasIntroPricing = null,
        public readonly ?bool      $hasVoucher      = null,
        public readonly ?bool      $isActive        = true,
        public readonly int        $page            = 1,
        public readonly int        $perPage         = 15,
        public readonly ?int $minPrice    = null,
        public readonly ?int $maxPrice      = null,
        public readonly ?string $createdFrom = null,
        public readonly ?string $createdTo   = null,
        public readonly ?string $updatedFrom = null,
        public readonly ?string $updatedTo   = null,
    ) {}
}