<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class BannerBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string $bannerType,
        public readonly string  $title,
        public readonly ?string $subtitle,
        public readonly ?string $ctaText,
        public readonly ?string $ctaUrl,
        public readonly ?string $address,
        public readonly string  $backgroundColor,
        public readonly string $textColor,
        public readonly ?string $backgroundImageUrl,
        public readonly ?string $navBackgroundColor,
        public readonly ?array $image,
        public readonly array  $providers,
        public readonly float  $rating,
        public readonly int    $reviewCount,
        public readonly bool   $showDismiss,
        public readonly bool   $dismissible,
        public ?array          $navLinks = [],
    )
    {
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            bannerType: $data['bannerType'] ?? 'promo-header',
            title: $data['title'] ?? '',
            subtitle: $data['subtitle'] ?? null,
            ctaText: $data['ctaText'] ?? null,
            ctaUrl: $data['ctaUrl'] ?? null,
            address: $data['address'] ?? null,
            backgroundColor: $data['backgroundColor'] ?? '#007bff',
            textColor: $data['textColor'] ?? '#ffffff',
            backgroundImageUrl: $data['backgroundImageUrl'] ?? null,
            navBackgroundColor: $data['navBackgroundColor'] ?? null,
            image: $data['image'] ?? (!empty($data['backgroundImageUrl']) ? ['src' => $data['backgroundImageUrl'], 'alt' => $data['title'] ?? ''] : null),
            providers: $data['providers'] ?? [],
            rating: (float)($data['rating'] ?? 0),
            reviewCount: (int)($data['reviewCount'] ?? 0),
            showDismiss: (bool)($data['showDismiss'] ?? false),
            dismissible: (bool)($data['dismissible'] ?? false),
            navLinks: $data['navLinks'] ?? [],
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}
