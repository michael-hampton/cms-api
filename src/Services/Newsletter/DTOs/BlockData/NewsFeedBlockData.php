<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class NewsFeedBlockData extends BaseBlockData
{
    public function __construct(
        public readonly ?string $title,
        public readonly ?string $subtitle,
        public readonly string  $layout,
        public readonly int     $columns,
        public readonly bool    $showExcerpt,
        public readonly bool    $showDate,
        public readonly bool    $showAuthor,
        public readonly bool    $showCategory,
        public readonly bool    $showReadTime,
        public readonly array   $items,
        public readonly int     $limit
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? null,
            subtitle: $data['subtitle'] ?? null,
            layout: $data['layout'] ?? 'grid',
            columns: (int)($data['columns'] ?? 3),
            showExcerpt: (bool)($data['showExcerpt'] ?? true),
            showDate: (bool)($data['showDate'] ?? true),
            showAuthor: (bool)($data['showAuthor'] ?? true),
            showCategory: (bool)($data['showCategory'] ?? true),
            showReadTime: (bool)($data['showReadTime'] ?? true),
            items: $data['items'] ?? [],
            limit: (int)($data['limit'] ?? 6)
        );
    }
}