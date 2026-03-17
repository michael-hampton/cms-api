<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class ProductBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string  $name,
        public readonly ?string $description,
        public readonly float   $price,
        public readonly ?float  $salePrice,
        public readonly string  $currency,
        public readonly ?string $link,
        public readonly string  $linkText,
        public readonly ?array $image,
    )
    {
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            name: $data['name'] ?? '',
            description: $data['description'] ?? null,
            price: (float)($data['price'] ?? 0),
            salePrice: isset($data['salePrice']) ? (float)$data['salePrice'] : null,
            currency: $data['currency'] ?? '$',
            link: $data['link'] ?? null,
            linkText: $data['linkText'] ?? 'View Product',
            image: $data['image'] ?? null,
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}