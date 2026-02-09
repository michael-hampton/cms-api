<?php

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
        public readonly array   $endorsements
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
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
            endorsements: $data['endorsements'] ?? []
        );
    }
}