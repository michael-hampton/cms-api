<?php

namespace App\Services\Vouchers\Providers;


use App\Services\Vouchers\Contracts\DiscountProvider;
use App\Services\Vouchers\DiscountApplicationResult;
use App\Services\Vouchers\DiscountContext\DiscountContext;

class OfferDiscountProvider implements DiscountProvider
{
    public function priority(): int
    {
        return 10; // First priority - base product discounts
    }

    public function supports(DiscountContext $context): bool
    {
        // Check if any items have offer pricing
        foreach ($context->items as $item) {
            if ($this->hasOfferDiscount($item)) {
                return true;
            }
        }
        return false;
    }

    private function hasOfferDiscount(array $item): bool
    {
        // Check if item has offer or bundle pricing
        if (isset($item['item_type']) && in_array($item['item_type'], ['offer', 'bundle'])) {
            return true;
        }

        // Check if sale_price exists and differs from base price
        if (isset($item['price']) && isset($item['base_price']) && $item['price'] < $item['base_price']) {
            return true;
        }

        return false;
    }

    public function apply(DiscountContext $context): ?DiscountApplicationResult
    {
        $totalDiscountCents = 0;
        $affectedItemIds = [];

        foreach ($context->items as $item) {
            if (!$this->hasOfferDiscount($item)) {
                continue;
            }

            $basePrice = (int)round(($item['base_price'] ?? $item['price']) * 100);
            $offerPrice = (int)round($item['price'] * 100);
            $quantity = $item['quantity'] ?? 1;

            $itemDiscountCents = ($basePrice - $offerPrice) * $quantity;

            if ($itemDiscountCents > 0) {
                $totalDiscountCents += $itemDiscountCents;
                $affectedItemIds[] = $item['id'] ?? $item['product_id'];
            }
        }

        if ($totalDiscountCents === 0) {
            return null;
        }

        return new DiscountApplicationResult(
            discountAmountCents: $totalDiscountCents,
            affectedItemIds: $affectedItemIds,
            stackable: true, // Offers are always stackable with other discount types
            fundingSource: 'merchant', // Merchants fund their own offers
            type: 'offer',
            metadata: [
                'offer_count' => count($affectedItemIds)
            ]
        );
    }
}