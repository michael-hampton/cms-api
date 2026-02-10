<?php

namespace App\Parsers\Dtos;

final class BannerBlockDto extends BaseBlockDto
{
    private const ALLOWED_TYPES = ['promo-header', 'review-banner', 'providers-banner'];
    private const ALLOWED_CONTEXTS = ['default', 'sidebar'];

    private const KNOWN_KEYS = [
        'bannerType', 'title', 'backgroundColor', 'textColor',
    ];

    public function __construct(
        public string $bannerType,
        public string $title,
        public string $subtitle,
        public string $ctaText,
        public string $ctaUrl,
        public string $backgroundColor,
        public string $textColor,
        public ?array $image,
        public array  $providers,
        public float  $rating,
        public int    $reviewCount,
        public bool   $showDismiss,
        public bool   $dismissible,
        public string $context
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'bannerType' => 'promo-header',
            'title' => '',
            'subtitle' => '',
            'ctaText' => '',
            'ctaUrl' => '',
            'backgroundColor' => '#007bff',
            'textColor' => '#ffffff',
            'image' => null,
            'providers' => [],
            'rating' => 0.0,
            'reviewCount' => 0,
            'showDismiss' => false,
            'dismissible' => false,
            'context' => 'default'
        ]);

        return new self(
            self::validateEnum($data['bannerType'], self::ALLOWED_TYPES, 'promo-header', 'bannerType'),
            trim($data['title']),
            trim($data['subtitle']),
            trim($data['ctaText']),
            $data['ctaUrl'],
            $data['backgroundColor'],
            $data['textColor'],
            $data['image'],
            $data['providers'],
            (float)$data['rating'],
            (int)$data['reviewCount'],
            (bool)$data['showDismiss'],
            (bool)$data['dismissible'],
            self::validateEnum($data['context'], self::ALLOWED_CONTEXTS, 'default', 'context')
        );
    }

    public function toArray(): array
    {
        return [
            'bannerType' => $this->bannerType,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'ctaText' => $this->ctaText,
            'ctaUrl' => $this->ctaUrl,
            'backgroundColor' => $this->backgroundColor,
            'textColor' => $this->textColor,
            'image' => $this->image,
            'providers' => $this->providers,
            'rating' => $this->rating,
            'reviewCount' => $this->reviewCount,
            'showDismiss' => $this->showDismiss,
            'dismissible' => $this->dismissible,
            'context' => $this->context
        ];
    }

    public function getType(): string
    {
        return 'banner';
    }
}