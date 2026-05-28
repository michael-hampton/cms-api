<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class NoteBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string  $title,
        public readonly array   $paragraphs,
        public readonly ?string $alignment,
        public readonly ?string $context,
        public readonly ?string $linkUrl,
        public readonly string  $linkText,
        public readonly bool    $sponsored,
        public readonly ?array $image,
    )
    {
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            title: $data['title'] ?? '',
            paragraphs: $data['paragraphs'] ?? [],
            alignment: $data['alignment'] ?? null,
            context: $data['context'] ?? null,
            linkUrl: $data['linkUrl'] ?? null,
            linkText: $data['linkText'] ?? 'Learn More',
            sponsored: (bool)($data['sponsored'] ?? false),
            image: $data['image'] ?? null,
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}
