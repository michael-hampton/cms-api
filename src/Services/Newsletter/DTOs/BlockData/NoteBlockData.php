<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class NoteBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string  $title,
        public readonly array   $paragraphs,
        public readonly ?string $linkUrl,
        public readonly string  $linkText,
        public readonly bool    $sponsored,
        public readonly ?array  $image
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? '',
            paragraphs: $data['paragraphs'] ?? [],
            linkUrl: $data['linkUrl'] ?? null,
            linkText: $data['linkText'] ?? 'Learn More',
            sponsored: (bool)($data['sponsored'] ?? false),
            image: $data['image'] ?? null
        );
    }
}