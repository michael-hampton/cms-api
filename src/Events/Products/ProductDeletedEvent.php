<?php

namespace App\Events\Products;

class ProductDeletedEvent
{
    public function __construct(
        public readonly int    $productId,
        public readonly string $productName
    )
    {
    }
}