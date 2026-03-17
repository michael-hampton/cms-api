<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class TextBlockData extends BaseBlockData
{
    public function __construct(
        public readonly array $paragraphs,
    )
    {
    }

    public static function fromArray(array $data): self
    {
        $instance = new static(
            paragraphs: $data['paragraphs'] ?? [],
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}