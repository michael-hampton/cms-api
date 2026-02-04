<?php

namespace App\Events\Products;

use App\Models\Product;

class ProductViewedEvent
{
    public function __construct(
        public readonly Product $product,
        public readonly ?int    $userId,
        public readonly string  $sessionId,
        public readonly ?string $ipAddress
    )
    {
    }
}