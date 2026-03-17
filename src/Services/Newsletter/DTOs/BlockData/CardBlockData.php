<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class CardBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string  $title,
        public readonly ?string $description,
        public readonly ?array  $image,
        public readonly ?array  $endorsement,
        public readonly ?string $linkUrl,
        public readonly string  $buttonType,
        public readonly string  $buttonText,
        public readonly bool    $noFollow,
        public readonly bool    $sponsored,
        public readonly bool    $openInNewTab,
        public readonly ?array  $sponsorDeclaration,
        public readonly string  $layout,
        public readonly string  $alignment,
        public readonly int $itemsPerRow,
    )
    {
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            title: $data['title'] ?? '',
            description: $data['description'] ?? null,
            image: $data['image'] ?? null,
            endorsement: $data['endorsement'] ?? null,
            linkUrl: $data['linkUrl'] ?? null,
            buttonType: $data['buttonType'] ?? 'primary',
            buttonText: $data['buttonText'] ?? 'Learn More',
            noFollow: (bool)($data['noFollow'] ?? false),
            sponsored: (bool)($data['sponsored'] ?? false),
            openInNewTab: (bool)($data['openInNewTab'] ?? false),
            sponsorDeclaration: $data['sponsorDeclaration'] ?? null,
            layout: $data['layout'] ?? 'full',
            alignment: $data['alignment'] ?? 'center',
            itemsPerRow: (int)($data['itemsPerRow'] ?? 3),
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}