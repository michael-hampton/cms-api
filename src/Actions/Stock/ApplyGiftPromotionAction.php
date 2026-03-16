<?php

namespace App\Actions\Stock;

use App\Exceptions\Stock\StockException;
use App\Models\IssueDelivery;
use App\Models\Product;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Subscriptions\IssueDeliveryRepository;

/**
 * High-level use case: allocate stock for a gift promotion.
 *
 * A gift promotion targets either:
 *   - A physical Product  (reduces products.stock_quantity), or
 *   - An IssueDelivery    (reduces issue_deliveries.stock_quantity).
 *
 * The caller is responsible for determining which model to pass based on the
 * promotion's configuration. This action accepts the already-resolved model
 * to remain decoupled from the GiftPromotion model shape.
 *
 * MUST be called inside the caller's open transaction.
 *
 * @example — product gift
 *   $product = $this->productRepository->lockForUpdate($promotion->product_id);
 *   $this->applyGiftPromotionAction->execute($product, 1);
 *
 * @example — subscription issue gift
 *   $issue = $this->issueDeliveryRepository->lockForUpdate($promotion->issue_delivery_id);
 *   $this->applyGiftPromotionAction->execute($issue, 1);
 */
class ApplyGiftPromotionAction
{
    public function __construct(
        private readonly PurchaseProductAction    $purchaseProductAction,
        private readonly FulfilSubscriptionAction $fulfilSubscriptionAction,
    )
    {
    }

    /**
     * @param Product|IssueDelivery $target The resolved gift target.
     * @param int $quantity How many units of the gift to allocate.
     *
     * @throws StockException           if the target has insufficient stock.
     * @throws \InvalidArgumentException if an unsupported model type is passed.
     */
    public function execute(Product|IssueDelivery $target, int $quantity, int $lowStockThreshold = 5): void
    {
        match (true) {
            $target instanceof Product => $this->purchaseProductAction->execute(
                $target,
                $quantity,
                $lowStockThreshold,
            ),

            $target instanceof IssueDelivery => $this->fulfilSubscriptionAction->reserve(
                $target,
                $quantity,
                $lowStockThreshold,
            ),
        };
    }
}