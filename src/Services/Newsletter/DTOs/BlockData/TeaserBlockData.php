<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class TeaserBlockData extends BaseBlockData
{
    public function __construct(
        public readonly ?string $componentId,
        public readonly string  $theme,
        public readonly ?string $copy,
        public readonly array   $items,
        public readonly ?string $footerText,
    )
    {
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            componentId: $data['componentId'] ?? null,
            theme: $data['theme'] ?? 'default',
            copy: $data['copy'] ?? null,
            items: $data['items'] ?? [],
            footerText: $data['footerText'] ?? null,
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}