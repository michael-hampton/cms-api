<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class AwardBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string  $subcategory,
        public readonly string  $productName,
        public readonly ?string $caption,
        public readonly ?string $strapline,
        public readonly int     $rating,
        public readonly bool    $winner,
        public readonly ?array $image,
    )
    {
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            subcategory: $data['subcategory'] ?? '',
            productName: $data['productName'] ?? '',
            caption: $data['caption'] ?? null,
            strapline: $data['strapline'] ?? null,
            rating: (int)($data['rating'] ?? 0),
            winner: (bool)($data['winner'] ?? false),
            image: $data['image'] ?? null,
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}