<?php

namespace App\Events\Orders;

use App\Models\Product;

class ProductStockUpdated
{
    public function __construct(
        public readonly Product $product,
        public readonly int     $oldStock,
        public readonly int     $newStock
    )
    {
    }
}