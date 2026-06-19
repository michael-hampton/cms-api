<?php

namespace App\Parsers\Dtos;

final class HeroBlockDto extends BaseBlockDto
{
    private const KNOWN_KEYS = [
        'title', 'subtitle'
    ];

    public function __construct(
        public string  $title,
        public string  $subtitle,
        public ?string $backgroundImage,
        public string  $ctaText,
        public string  $ctaUrl,
        public string  $secondaryCtaText,
        public string  $secondaryCtaUrl,
        public bool    $showSearch
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'title' => '',
            'subtitle' => '',
            'backgroundImage' => null,
            'ctaText' => 'Get Started',
            'ctaUrl' => '#',
            'secondaryCtaText' => '',
            'secondaryCtaUrl' => '#',
            'showSearch' => false
        ]);

        return new self(
            trim($data['title']),
            trim($data['subtitle']),
            $data['backgroundImage'],
            $data['ctaText'],
            $data['ctaUrl'],
            $data['secondaryCtaText'],
            $data['secondaryCtaUrl'],
            (bool)$data['showSearch']
        );
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'backgroundImage' => $this->backgroundImage,
            'ctaText' => $this->ctaText,
            'ctaUrl' => $this->ctaUrl,
            'secondaryCtaText' => $this->secondaryCtaText,
            'secondaryCtaUrl' => $this->secondaryCtaUrl,
            'showSearch' => $this->showSearch,
            'formatted_title' => htmlspecialchars($this->title),
            'formatted_subtitle' => htmlspecialchars($this->subtitle),
            'has_secondary_cta' => !empty($this->secondaryCtaText),
            'has_background' => !empty($this->backgroundImage)
        ];
    }

    public function getType(): string
    {
        return 'hero';
    }
}