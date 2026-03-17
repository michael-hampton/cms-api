<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class CtaBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string $text,
        public readonly string $url,
        public readonly bool   $noFollow,
        public readonly bool   $sponsored,
        public readonly bool   $openInNewTab,
        public readonly string $ctaStyle,
        public readonly string $size,
        public readonly string $alignment,
    )
    {
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            text: $data['text'] ?? 'Click Here',
            url: $data['url'] ?? '#',
            noFollow: (bool)($data['noFollow'] ?? false),
            sponsored: (bool)($data['sponsored'] ?? false),
            openInNewTab: (bool)($data['openInNewTab'] ?? false),
            ctaStyle: $data['style'] ?? 'primary',
            size: $data['size'] ?? 'medium',
            alignment: $data['alignment'] ?? 'center',
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}