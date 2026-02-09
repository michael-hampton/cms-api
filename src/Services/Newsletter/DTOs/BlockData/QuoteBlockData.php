<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class QuoteBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string  $text,
        public readonly ?string $attribution = null
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            text: $data['text'] ?? '',
            attribution: $data['attribution'] ?? null
        );
    }
}