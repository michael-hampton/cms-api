<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

/**
 * Email template article card block.
 *
 * page_id is authoritative at render time — the renderer fetches the live
 * page by ID and falls back to the cached fields when the page is not found
 * (e.g. deleted or unpublished since the block was inserted).
 *
 * The cached fields (title, slug, description, hero_image_url) are stored at
 * insert time by the Angular frontend so the block editor can show a meaningful
 * preview without a second API call.
 */
class ArticleCardBlockData extends BaseBlockData
{
    public function __construct(
        /** Authoritative page reference — resolved at render time */
        public readonly ?int    $pageId,

        /** Cached at insert time — used as fallback if page is gone */
        public readonly string  $title,
        public readonly string  $slug,
        public readonly string  $description,
        public readonly ?string $heroImageUrl,

        /** Display options */
        public readonly bool    $showImage,
        public readonly bool    $showDescription,
        public readonly string  $align,
    )
    {
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            pageId: isset($data['page_id']) ? (int)$data['page_id'] : null,
            title: $data['title'] ?? '',
            slug: $data['slug'] ?? '',
            description: $data['description'] ?? '',
            heroImageUrl: $data['hero_image_url'] ?? null,
            showImage: (bool)($data['show_image'] ?? true),
            showDescription: (bool)($data['show_description'] ?? true),
            align: $data['align'] ?? 'left',
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}