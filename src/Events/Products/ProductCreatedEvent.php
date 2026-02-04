<?php
// src/Events/Product/ProductCreatedEvent.php

namespace App\Events\Products;

use App\Models\Product;

class ProductCreatedEvent
{
    public function __construct(
        public readonly Product $product,
        public readonly array   $metadata = []
    )
    {
    }
}