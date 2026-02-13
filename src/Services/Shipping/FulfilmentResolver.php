<?php

namespace App\Services\Shipping;

use App\Models\Product;
use App\Models\SubscriptionPlan;
use DomainException;

class FulfilmentResolver
{
    public function resolve(mixed $purchasable): FulfilmentTypeInterface
    {
        if ($purchasable instanceof Product) {
            return new PhysicalProductFulfilment($purchasable);
        }

        if ($purchasable instanceof SubscriptionPlan) {
            return $purchasable->hasDigitalOption() && !$purchasable->print_shipping_required
                ? new DigitalSubscriptionFulfilment($purchasable)
                : new PrintedSubscriptionFulfilment($purchasable);
        }

        throw new DomainException('Unsupported purchasable type');
    }
}