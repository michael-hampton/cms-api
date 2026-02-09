<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class DealBlockData extends BaseBlockData
{
    public function __construct(
        public readonly int $productId
    )
    {
    }

    public static function fromArray(array $data): self
    {
        if (!isset($data['product_id'])) {
            throw new \InvalidArgumentException('Missing required field: product_id');
        }

        return new self((int)$data['product_id']);
    }
}