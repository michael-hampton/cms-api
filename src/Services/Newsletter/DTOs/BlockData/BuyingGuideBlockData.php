<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class BuyingGuideBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string  $title,
        public readonly ?string $subtitle,
        public readonly array   $specs,
        public readonly array   $pros,
        public readonly array   $cons,
        public readonly bool    $showReviewPanel,
        public readonly ?string $url,
        public readonly string  $linkText,
        public readonly bool    $sponsored,
        public readonly ?array $image,
    )
    {
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            title: $data['title'] ?? '',
            subtitle: $data['subtitle'] ?? null,
            specs: $data['specs'] ?? [],
            pros: $data['pros'] ?? [],
            cons: $data['cons'] ?? [],
            showReviewPanel: (bool)($data['showReviewPanel'] ?? false),
            url: $data['url'] ?? null,
            linkText: $data['linkText'] ?? 'Learn More',
            sponsored: (bool)($data['sponsored'] ?? false),
            image: $data['image'] ?? null,
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}