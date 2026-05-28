<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class ImageBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string  $src,
        public readonly string  $alt,
        public readonly ?string $caption,
        public readonly ?string $credit,
        public readonly ?string $linkUrl,
        public readonly bool    $noFollow,
        public readonly bool    $sponsored,
        public readonly bool    $openInNewTab,
        public readonly string  $layout,
        public readonly string  $alignment,
        public readonly ?string $context,
        public readonly ?string $imageWidth,
        public readonly ?string $maxHeight,
        public readonly ?string $objectFit,
        public readonly ?string $objectPosition,
        public readonly ?string $imagePadding,
        public readonly array $endorsements,
    )
    {
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            src: $data['src'] ?? '',
            alt: $data['alt'] ?? '',
            caption: $data['caption'] ?? null,
            credit: $data['credit'] ?? null,
            linkUrl: $data['linkUrl'] ?? null,
            noFollow: (bool)($data['noFollow'] ?? false),
            sponsored: (bool)($data['sponsored'] ?? false),
            openInNewTab: (bool)($data['openInNewTab'] ?? false),
            layout: $data['layout'] ?? 'full',
            alignment: $data['alignment'] ?? 'fullscreen',
            context: $data['context'] ?? null,
            imageWidth: $data['imageWidth'] ?? null,
            maxHeight: $data['maxHeight'] ?? null,
            objectFit: $data['objectFit'] ?? null,
            objectPosition: $data['objectPosition'] ?? null,
            imagePadding: $data['imagePadding'] ?? null,
            endorsements: $data['endorsements'] ?? [],
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}
