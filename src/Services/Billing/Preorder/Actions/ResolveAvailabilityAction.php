<?php

namespace App\Services\Billing\Preorder\Actions;

use App\Enums\Orders\OrderLineStatus;
use App\Models\Product;

class ResolveAvailabilityAction
{
    public function execute(Product $product, int $quantity): array
    {
        $policy = $product->availabilityPolicy();

        if (!$policy->canPurchase()) {
            throw new \Exception('Product not available for purchase');
        }

        // Determine order line status
        if ($product->stock_quantity >= $quantity) {
            return [
                'status' => OrderLineStatus::READY_TO_SHIP->value,
                'expected_ship_date' => null,
                'is_preorder' => false,
            ];
        }

        if ($policy->isPreOrder()) {
            $shipDate = $policy->getExpectedShipDate();

            if (!$shipDate instanceof \DateTimeInterface) {
                throw new \Exception('Preorder requires expected ship date');
            }

            return [
                'status' => OrderLineStatus::PENDING_PREORDER->value,
                'expected_ship_date' => $shipDate,
                'is_preorder' => true,
            ];
        }

        throw new \Exception('Invalid availability state');
    }
}