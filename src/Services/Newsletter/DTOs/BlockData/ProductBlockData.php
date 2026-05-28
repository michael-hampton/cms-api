<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class ProductBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string  $name,
        public readonly ?string $productName,
        public readonly ?string $brand,
        public readonly ?string $description,
        public readonly float   $price,
        public readonly ?float  $salePrice,
        public readonly string  $currency,
        public readonly ?string $link,
        public readonly string  $linkText,
        public readonly ?string $layout,
        public readonly array $specs,
        public readonly ?array $image,
    )
    {
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            name: $data['name'] ?? ($data['productName'] ?? ''),
            productName: $data['productName'] ?? ($data['name'] ?? null),
            brand: $data['brand'] ?? null,
            description: $data['description'] ?? null,
            price: (float)($data['price'] ?? 0),
            salePrice: isset($data['salePrice']) ? (float)$data['salePrice'] : null,
            currency: $data['currency'] ?? '$',
            link: $data['link'] ?? null,
            linkText: $data['linkText'] ?? 'View Product',
            layout: $data['layout'] ?? null,
            specs: $data['specs'] ?? [],
            image: $data['image'] ?? (!empty($data['imageSrc']) ? ['src' => $data['imageSrc'], 'alt' => $data['name'] ?? ($data['productName'] ?? '')] : null),
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}
