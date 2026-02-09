<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class StaticDealBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string  $title,
        public readonly string  $productName,
        public readonly ?string $brand,
        public readonly ?string $description,
        public readonly float   $price,
        public readonly float   $salePrice,
        public readonly string  $currency,
        public readonly float   $savings,
        public readonly int     $savingsPercent,
        public readonly ?string $link,
        public readonly ?string $voucherId,
        public readonly bool    $sponsored,
        public readonly ?array  $image
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? '',
            productName: $data['productName'] ?? '',
            brand: $data['brand'] ?? null,
            description: $data['description'] ?? null,
            price: (float)($data['price'] ?? 0),
            salePrice: (float)($data['salePrice'] ?? 0),
            currency: $data['currency'] ?? '£',
            savings: (float)($data['savings'] ?? 0),
            savingsPercent: (int)($data['savings_percent'] ?? 0),
            link: $data['link'] ?? null,
            voucherId: $data['voucherId'] ?? null,
            sponsored: (bool)($data['sponsored'] ?? false),
            image: $data['image'] ?? null
        );
    }
}