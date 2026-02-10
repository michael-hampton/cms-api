<?php

namespace App\Parsers\Dtos;

final class TeaserBlockDto extends BaseBlockDto
{
    private const ALLOWED_THEMES = ['default', 'light', 'dark', 'colored'];
    private const ALLOWED_ICONS = ['arrow', 'check', 'star', 'circle', 'info', 'link'];
    private const ALLOWED_CONTEXTS = ['default', 'sidebar'];

    private const KNOWN_KEYS = [
        'componentId', 'theme', 'copy', 'items', 'footerText', 'context'
    ];

    public function __construct(
        public string $componentId,
        public string $theme,
        public string $copy,
        public array  $items,
        public string $footerText,
        public string $context
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'componentId' => '',
            'theme' => 'default',
            'copy' => '',
            'items' => [],
            'footerText' => '',
            'context' => 'default'
        ]);

        $items = [];
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $items[] = [
                    'link' => trim($item['link'] ?? ''),
                    'icon' => self::validateEnum($item['icon'] ?? 'arrow', self::ALLOWED_ICONS, 'arrow', 'icon'),
                    'title' => trim($item['title'] ?? ''),
                    'description' => trim($item['description'] ?? ''),
                    'formatted_title' => htmlspecialchars($item['title'] ?? ''),
                    'formatted_description' => htmlspecialchars($item['description'] ?? '')
                ];
            }
        }

        return new self(
            trim($data['componentId']),
            self::validateEnum($data['theme'], self::ALLOWED_THEMES, 'default', 'theme'),
            $data['copy'],
            $items,
            $data['footerText'],
            self::validateEnum($data['context'], self::ALLOWED_CONTEXTS, 'default', 'context')
        );
    }

    public function toArray(): array
    {
        return [
            'componentId' => $this->componentId,
            'theme' => $this->theme,
            'copy' => $this->copy,
            'items' => $this->items,
            'footerText' => $this->footerText,
            'formatted_copy' => $this->copy,
            'has_copy' => !empty(trim(strip_tags($this->copy))),
            'has_footer' => !empty(trim($this->footerText)),
            'items_count' => count($this->items),
            'context' => $this->context
        ];
    }

    public function getType(): string
    {
        return 'teaser';
    }
}