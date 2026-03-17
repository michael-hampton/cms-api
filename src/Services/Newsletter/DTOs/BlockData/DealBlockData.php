<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

use InvalidArgumentException;

class DealBlockData extends BaseBlockData
{
    public function __construct(
        public readonly int $productId,
    )
    {
    }

    public static function fromArray(array $data): static
    {
        if (!isset($data['product_id'])) {
            throw new InvalidArgumentException('Missing required field: product_id');
        }

        $instance = new static(
            productId: (int)$data['product_id'],
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}