<?php

namespace App\Exceptions\Cart;

class InsufficientStockException extends CartException
{
    public function __construct(
        public readonly int  $requestedQuantity,
        public readonly ?int $availableQuantity,
        public readonly ?int $productId = null,
        public readonly ?int $variantId = null,
    )
    {
        $message = $this->buildMessage();
        parent::__construct($message);
    }

    private function buildMessage(): string
    {
        if ($this->availableQuantity === null) {
            return 'Insufficient stock';
        }

        return sprintf(
            'Cannot add %d items. Only %d available.',
            $this->requestedQuantity,
            $this->availableQuantity
        );
    }

    /**
     * Get user-friendly message.
     */
    public function getUserMessage(): string
    {
        return 'Cannot add more items. Stock limit reached.';
    }
}