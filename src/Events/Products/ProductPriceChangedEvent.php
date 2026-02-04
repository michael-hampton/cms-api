<?php

namespace App\Events\Products;

use App\Enums\Products\PriceChangeType;
use App\Models\Product;

class ProductPriceChangedEvent
{
    public function __construct(
        public readonly Product         $product,
        public readonly PriceChangeType $changeType,
        public readonly ?float          $oldPrice,
        public readonly ?float          $newPrice,
        public readonly ?float          $oldSalePrice = null,
        public readonly ?float          $newSalePrice = null,
        public readonly ?int            $merchantId = null
    )
    {
    }
}