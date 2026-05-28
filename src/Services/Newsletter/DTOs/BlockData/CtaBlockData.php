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
        public readonly ?string $context,
        public readonly ?string $buttonWidth,
        public readonly ?string $paddingTop,
        public readonly ?string $paddingBottom,
        public readonly ?string $imagePadding,
        public readonly ?string $borderRadius,
        public readonly ?string $buttonBorder,
        public readonly bool $roundedButton,
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
            ctaStyle: $data['ctaStyle'] ?? $data['style'] ?? 'primary',
            size: $data['size'] ?? 'medium',
            alignment: $data['alignment'] ?? 'center',
            context: $data['context'] ?? null,
            buttonWidth: $data['buttonWidth'] ?? null,
            paddingTop: $data['paddingTop'] ?? null,
            paddingBottom: $data['paddingBottom'] ?? null,
            imagePadding: $data['imagePadding'] ?? null,
            borderRadius: $data['borderRadius'] ?? null,
            buttonBorder: $data['buttonBorder'] ?? null,
            roundedButton: (bool)($data['roundedButton'] ?? false),
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}
