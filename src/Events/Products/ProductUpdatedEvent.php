<?php

namespace App\Events\Products;

use App\Models\Product;

class ProductUpdatedEvent
{
    public function __construct(
        public readonly Product $product,
        public readonly array   $changedAttributes = []
    )
    {
    }
}