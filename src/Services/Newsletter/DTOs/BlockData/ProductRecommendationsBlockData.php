<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class ProductRecommendationsBlockData extends BaseBlockData
{
    public function __construct(
        /** How many products to surface at render time */
        public readonly int     $limit,

        /** Fallback strategy when the recommendation engine returns nothing */
        public readonly string  $fallback,

        /** Optional section heading */
        public readonly ?string $title,

        /** Display toggles */
        public readonly bool    $showImage,
        public readonly bool    $showPrice,

        /**
         * Mock items stored at insert time for builder preview.
         * Shape: [{ name, price, currency, link, image_url }]
         */
        public readonly array   $mockItems,
    )
    {
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            limit: (int)($data['limit'] ?? 4),
            fallback: $data['fallback'] ?? 'top_sellers',
            title: $data['title'] ?? null,
            showImage: (bool)($data['show_image'] ?? true),
            showPrice: (bool)($data['show_price'] ?? true),
            mockItems: $data['mock_items'] ?? [],
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}