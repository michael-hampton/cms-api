<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class HeadingBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string  $text,
        public readonly ?string $subtitle = null,
        public readonly int $level = 2,
    )
    {
    }

    public static function fromArray(array $data): self
    {
        $instance = new static(
            text: $data['text'] ?? '',
            subtitle: $data['subtitle'] ?? null,
            level: $data['level'] ?? 2,
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}