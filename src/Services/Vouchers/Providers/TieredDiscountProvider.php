<?php

namespace App\Services\Vouchers\Providers;

use App\Repositories\Offers\TieredPromotionRepository;
use App\Services\Vouchers\Contracts\DiscountProvider;
use App\Services\Vouchers\DiscountApplicationResult;
use App\Services\Vouchers\DiscountContext;

class TieredDiscountProvider implements DiscountProvider
{
    public function __construct(
        private readonly TieredPromotionRepository $repository
    )
    {
    }

    public function priority(): int
    {
        return 20; // After offers (10), before vouchers (30)
    }

    public function supports(DiscountContext $context): bool
    {
        $promotion = $this->repository->findApplicablePromotion(
            $context->currentSubtotalCents,
            $context->isSubscription
        );

        return $promotion !== null;
    }

    public function apply(DiscountContext $context): ?DiscountApplicationResult
    {
        // Use post-offer subtotal
        $promotion = $this->repository->findApplicablePromotion(
            $context->currentSubtotalCents,
            $context->isSubscription
        );

        if (!$promotion) {
            return null;
        }

        $discountCents = $this->calculateDiscount($promotion, $context->currentSubtotalCents);

        if ($discountCents === 0) {
            return null;
        }

        return new DiscountApplicationResult(
            discountAmountCents: $discountCents,
            affectedItemIds: array_map(fn($item) => $item['id'] ?? $item['product_id'], $context->items),
            stackable: $promotion->stackable,
            fundingSource: 'platform',
            type: 'tiered',
            metadata: [
                'promotion_id' => $promotion->id,
                'promotion_name' => $promotion->name,
                'min_subtotal_cents' => $promotion->min_subtotal_cents,
                'discount_type' => $promotion->discount_type,
                'applies_to' => $promotion->applies_to,
            ]
        );
    }

    private function calculateDiscount(object $promotion, int $subtotalCents): int
    {
        if ($promotion->discount_type === 'percentage') {
            return (int)round($subtotalCents * ($promotion->value / 100));
        }

        // Fixed discount
        $fixedCents = (int)round($promotion->value * 100);
        return min($fixedCents, $subtotalCents);
    }
}