<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class QuoteBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string  $text,
        public readonly ?string $attribution = null,
    )
    {
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            text: $data['text'] ?? '',
            attribution: $data['attribution'] ?? null,
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}