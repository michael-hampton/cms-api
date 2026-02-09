<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class TextBlockData extends BaseBlockData
{
    public function __construct(
        public readonly array $paragraphs
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            paragraphs: $data['paragraphs'] ?? []
        );
    }
}