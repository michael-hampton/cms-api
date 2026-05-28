<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class TrendingContentBlockData extends BaseBlockData
{
    public function __construct(
        /** How many products to surface at render time */
        public readonly int     $limit,

        /** Fallback strategy when the recommendation engine returns nothing */
        public readonly string  $fallback,

        /** Optional section heading */
        public readonly ?string $title,
        public readonly ?string $timeframe,

        /**
         * Mock items stored at insert time for builder preview.
         * Shape: [{ name, price, currency, link, image_url }]
         */
        public readonly array   $mockItems,
        public bool             $showImage = true,
        public bool             $showExcerpt = true
    )
    {
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            limit: (int)($data['limit'] ?? 4),
            fallback: $data['fallback'] ?? 'top_sellers',
            title: $data['title'] ?? null,
            timeframe: $data['timeframe'] ?? null,
            mockItems: $data['mock_items'] ?? [],
            showImage: $data['showImage'] ?? true,
            showExcerpt: $data['showExcerpt'] ?? true
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}
