<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class ImageBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string  $src,
        public readonly string  $alt = '',
        public readonly ?string $caption = null,
        public readonly ?string $linkUrl = null
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            src: $data['src'] ?? '',
            alt: $data['alt'] ?? '',
            caption: $data['caption'] ?? null,
            linkUrl: $data['linkUrl'] ?? null
        );
    }
}