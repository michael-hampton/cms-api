<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class HeadingBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string  $text,
        public readonly ?string $subtitle = null,
        public readonly int $level = 2,
        public readonly ?string $context = null,
        public readonly ?string $textColor = null,
        public readonly ?string $fontStyle = null,
        public readonly ?string $fontFamily = null,
        public readonly ?string $fontWeight = null,
        public readonly ?string $textTransform = null,
    )
    {
    }

    public static function fromArray(array $data): self
    {
        $instance = new static(
            text: $data['text'] ?? '',
            subtitle: $data['subtitle'] ?? null,
            level: $data['level'] ?? 2,
            context: $data['context'] ?? null,
            textColor: $data['textColor'] ?? null,
            fontStyle: $data['fontStyle'] ?? null,
            fontFamily: $data['fontFamily'] ?? null,
            fontWeight: $data['fontWeight'] ?? null,
            textTransform: $data['textTransform'] ?? null,
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}
