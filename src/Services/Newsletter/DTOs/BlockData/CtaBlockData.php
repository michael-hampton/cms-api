<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class CtaBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string $text,
        public readonly string $url,
        public readonly string $alignment = 'center'
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            text: $data['text'] ?? 'Click Here',
            url: $data['url'] ?? '#',
            alignment: $data['alignment'] ?? 'center'
        );
    }
}