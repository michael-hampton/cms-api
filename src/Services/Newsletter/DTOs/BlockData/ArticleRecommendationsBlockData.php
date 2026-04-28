<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class ArticleRecommendationsBlockData extends BaseBlockData
{
    public function __construct(
        /** How many articles to surface at render time */
        public readonly int     $limit,

        /** Fallback strategy when the recommendation engine returns nothing */
        public readonly string  $fallback,

        /** Optional section heading shown above the recommendations */
        public readonly ?string $title,

        /** Display toggles */
        public readonly bool    $showImage,
        public readonly bool    $showExcerpt,

        /**
         * Mock items stored at insert time so the builder can render a
         * realistic preview without calling any backend service.
         * Shape: [{ title, slug, description, hero_image_url }]
         */
        public readonly array   $mockItems,
    )
    {
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            limit: (int)($data['limit'] ?? 4),
            fallback: $data['fallback'] ?? 'trending',
            title: $data['title'] ?? null,
            showImage: (bool)($data['show_image'] ?? true),
            showExcerpt: (bool)($data['show_excerpt'] ?? true),
            mockItems: $data['mock_items'] ?? [],
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}