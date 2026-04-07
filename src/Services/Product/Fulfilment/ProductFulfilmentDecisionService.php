<?php

declare(strict_types=1);

namespace App\Services\Product\Fulfilment;

use App\DTO\Subscriptions\FulfilmentDecisionContext;
use App\Models\Order;
use App\Models\OrderItem;

/**
 * Produces a FulfilmentDecisionContext for a single order line.
 *
 * Parallel to FulfilmentDecisionService in the print pipeline.
 * FulfilmentDecisionService is closed for modification — this class is
 * new and operates on Order/OrderLine rather than Subscription/IssueDelivery.
 *
 * Reuses without modification:
 *   - PostcodeRegionResolver via PostcodeOnlyRegionResolver (territory lookup)
 *   - FulfilmentDecisionContext DTO
 *
 * Single reason to change: the decision rules for product territory/address.
 */
class ProductFulfilmentDecisionService
{
    public function __construct(
        private readonly ProductAddressResolver     $addressResolver,
        private readonly PostcodeOnlyRegionResolver $regionResolver,
    )
    {
    }

    /**
     * @throws \RuntimeException When no valid delivery address exists for the order.
     */
    public function decide(Order $order, OrderItem $orderLine): FulfilmentDecisionContext
    {
        $resolvedAddress = $this->addressResolver->resolve($order);

        $territory = $this->regionResolver->resolve($resolvedAddress['postcode'] ?? null);

        return new FulfilmentDecisionContext(
            territory: $territory,
            addressSnapshot: $resolvedAddress['snapshot'],
            channelMetadata: [
                'order_id' => $order->id,
                'order_line_id' => $orderLine->id,
            ],
            fullName: $resolvedAddress['full_name']
        );
    }
}