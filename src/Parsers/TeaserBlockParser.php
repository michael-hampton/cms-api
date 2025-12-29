<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;

class TeaserBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'teaser';
    }

    public function getValidationRules(): array
    {
        return [
            'componentId' => [
                new MaxLengthRule(100)
            ],
            'theme' => [
                new RequiredRule(),
                new MaxLengthRule(50)
            ],
            'copy' => [
                new MaxLengthRule(5000)
            ],
            'items' => [
                new RequiredRule(),
                new ArrayRule()
            ],
            'footerText' => [
                new MaxLengthRule(500)
            ],
            'context' => [
                new MaxLengthRule(20)
            ]
        ];
    }

    public function parse(array $data): array
    {
        $items = [];

        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $items[] = [
                    'link' => trim($item['link'] ?? ''),
                    'icon' => $item['icon'] ?? 'arrow',
                    'title' => trim($item['title'] ?? ''),
                    'description' => trim($item['description'] ?? ''),
                    'formatted_title' => htmlspecialchars($item['title'] ?? ''),
                    'formatted_description' => htmlspecialchars($item['description'] ?? '')
                ];
            }
        }

        return [
            'componentId' => trim($data['componentId'] ?? ''),
            'theme' => $data['theme'] ?? 'default',
            'copy' => $data['copy'] ?? '',
            'items' => $items,
            'footerText' => $this->sanitizeFooterText($data['footerText'] ?? ''),
            'formatted_copy' => $this->formatCopy($data['copy'] ?? ''),
            'has_copy' => !empty(trim(strip_tags($data['copy'] ?? ''))),
            'has_footer' => !empty(trim($data['footerText'] ?? '')),
            'items_count' => count($items),
            'context' => $data['context'] ?? 'default'
        ];
    }

    private function sanitizeFooterText(string $text): string
    {
        // Only allow <br>, <a>, <sup> tags
        $text = strip_tags($text, '<br><a><sup>');
        return trim($text);
    }

    private function formatCopy(string $copy): string
    {
        // Allow basic HTML and preserve anchor classes
        return $copy;
    }

    public function generateHtml(array $parsedData): string
    {
        $componentId = !empty($parsedData['componentId'])
            ? ' id="' . htmlspecialchars($parsedData['componentId']) . '"'
            : '';

        $theme = htmlspecialchars($parsedData['theme']);
        $contextClass = $parsedData['context'] === 'sidebar' ? ' teaser-sidebar' : '';

        $html = "<div class=\"teaser-block teaser-theme-{$theme}{$contextClass}\"{$componentId}>";

        // Introductory copy
        if ($parsedData['has_copy']) {
            $html .= "<div class=\"teaser-copy\">{$parsedData['formatted_copy']}</div>";
        }

        // Teaser items
        if (!empty($parsedData['items'])) {
            $html .= "<div class=\"teaser-items\">";

            foreach ($parsedData['items'] as $item) {
                $icon = $this->getIconHtml($item['icon']);

                $html .= "<a href=\"{$item['link']}\" class=\"teaser-item\">";
                $html .= "<span class=\"teaser-icon\">{$icon}</span>";
                $html .= "<div class=\"teaser-content\">";
                $html .= "<h3 class=\"teaser-title\">{$item['formatted_title']}</h3>";
                $html .= "<p class=\"teaser-description\">{$item['formatted_description']}</p>";
                $html .= "</div>";
                $html .= "</a>";
            }

            $html .= "</div>";
        }

        // Footer text
        if ($parsedData['has_footer']) {
            $html .= "<div class=\"teaser-footer\">{$parsedData['footerText']}</div>";
        }

        $html .= "</div>";

        return $html;
    }

    private function getIconHtml(string $iconType): string
    {
        $icons = [
            'arrow' => '→',
            'check' => '✓',
            'star' => '★',
            'circle' => '●',
            'info' => 'ℹ️',
            'link' => '🔗'
        ];

        return $icons[$iconType] ?? $icons['arrow'];
    }
}