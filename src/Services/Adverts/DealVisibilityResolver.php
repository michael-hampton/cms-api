<?php

namespace App\Services\Adverts;

use App\Enums\Adverts\SuppressionReason;
use App\Models\Product;
use App\Repositories\Product\ProductRepository;

class DealVisibilityResolver
{
    public function __construct(
        private readonly ProductRepository      $productRepository,
        private readonly EligibilityRuleFactory $ruleFactory
    )
    {
    }

    public function resolveMultiple(array $productIds, RenderContext $context): array
    {
        $decisions = [];

        foreach ($productIds as $productId) {
            $product = $this->productRepository->find($productId);

            if (!$product) {
                continue;
            }

            $decision = $this->resolve($product, $context);
            if ($decision->shouldRender) {
                $decisions[] = [
                    'product' => $product,
                    'decision' => $decision,
                ];
            }
        }

        return $decisions;
    }

    public function resolve(Product $product, RenderContext $context): VisibilityDecision
    {
        // Check if product exists and is active
        if (!$product->is_active) {
            return VisibilityDecision::hide(SuppressionReason::DEAL_INACTIVE);
        }

        // Check if product has a valid sale (sale_price < price)
        if (!$product->sale_price || $product->sale_price <= 0 || $product->sale_price >= $product->price) {
            return VisibilityDecision::hide(SuppressionReason::NO_ACTIVE_SALE);
        }

        // Check eligibility rules if any are defined
        // For now, deals typically don't have eligibility rules, but we'll support them
        $member = $context->memberId ? \App\Models\Member::find($context->memberId) : null;

        // Deals are generally public, but we can add eligibility rules in the future
        // For example, exclusive deals for paid members

        return VisibilityDecision::show([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'original_price' => $product->price,
            'sale_price' => $product->sale_price,
            'discount_percentage' => $product->discount_percentage,
        ]);
    }
}