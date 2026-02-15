<?php

namespace App\Exceptions\Cart;

class ProductNotFoundException extends CartException
{
    public function __construct(
        public readonly int  $productId,
        public readonly ?int $variantId = null,
    )
    {
        $message = $this->variantId
            ? "Variant #{$this->variantId} not found for product #{$this->productId}"
            : "Product #{$this->productId} not found";

        parent::__construct($message);
    }

    public function getUserMessage(): string
    {
        return $this->variantId
            ? 'Variant not found'
            : 'Product not found or inactive';
    }
}