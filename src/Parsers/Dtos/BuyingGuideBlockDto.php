<?php

namespace App\Parsers\Dtos;

final class BuyingGuideBlockDto extends BaseBlockDto
{
    private const KNOWN_KEYS = [
        'title', 'subtitle', 'specs', 'pros', 'cons',
        'showReviewPanel',
    ];

    public function __construct(
        public string $title,
        public string $subtitle,
        public string $url,
        public string $linkText,
        public array  $specs,
        public array  $pros,
        public array  $cons,
        public bool   $showReviewPanel,
        public string $displayAs,
        public bool   $noFollow,
        public bool   $sponsored,
        public bool   $openInNewTab,
        public ?array $image
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'title' => '',
            'subtitle' => '',
            'url' => '',
            'linkText' => 'Learn More',
            'specs' => [],
            'pros' => [],
            'cons' => [],
            'showReviewPanel' => false,
            'displayAs' => 'button',
            'noFollow' => false,
            'sponsored' => false,
            'openInNewTab' => false,
            'image' => null
        ]);

        $specs = [];
        foreach ($data['specs'] ?? [] as $spec) {
            if (!empty($spec['text']) && !empty($spec['value'])) {
                $specs[] = [
                    'text' => trim($spec['text']),
                    'value' => trim($spec['value'])
                ];
            }
        }

        return new self(
            trim($data['title']),
            trim($data['subtitle']),
            $data['url'],
            $data['linkText'],
            $specs,
            array_filter($data['pros'] ?? []),
            array_filter($data['cons'] ?? []),
            (bool)$data['showReviewPanel'],
            $data['displayAs'],
            (bool)$data['noFollow'],
            (bool)$data['sponsored'],
            (bool)$data['openInNewTab'],
            $data['image']
        );
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'url' => $this->url,
            'linkText' => $this->linkText,
            'specs' => $this->specs,
            'pros' => $this->pros,
            'cons' => $this->cons,
            'showReviewPanel' => $this->showReviewPanel,
            'displayAs' => $this->displayAs,
            'noFollow' => $this->noFollow,
            'sponsored' => $this->sponsored,
            'openInNewTab' => $this->openInNewTab,
            'has_specs' => !empty($this->specs),
            'has_pros_cons' => !empty($this->pros) || !empty($this->cons),
            'image' => $this->image,
            'has_image' => !empty($this->image)
        ];
    }

    public function getType(): string
    {
        return 'buying-guide';
    }
}