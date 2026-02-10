<?php

namespace App\Parsers\Renderers;


use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\TeaserBlockDto;

class TeaserBlockRenderer extends BaseBlockRenderer
{
    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof TeaserBlockDto) {
            throw new \InvalidArgumentException('Expected TeaserBlockDto');
        }

        $componentId = !empty($dto->componentId) ? ' id="' . $this->escape($dto->componentId) . '"' : '';
        $contextClass = $dto->context === 'sidebar' ? ' teaser-sidebar' : '';

        $html = "<div class=\"teaser-block teaser-theme-{$dto->theme}{$contextClass}\"{$componentId}>";

        // Introductory copy
        if (!empty(trim(strip_tags($dto->copy)))) {
            $html .= "<div class=\"teaser-copy\">{$dto->copy}</div>";
        }

        // Teaser items
        if (!empty($dto->items)) {
            $html .= "<div class=\"teaser-items\">";

            foreach ($dto->items as $item) {
                $icon = $this->getIconHtml($item['icon']);

                $html .= "<a href=\"{$item['link']}\" class=\"teaser-item\">";
                $html .= "<span class=\"teaser-icon\">{$icon}</span>";
                $html .= "<div class=\"teaser-content\">";
                $html .= "<h3 class=\"teaser-title\">" . $this->escape($item['title']) . "</h3>";
                $html .= "<p class=\"teaser-description\">" . $this->escape($item['description']) . "</p>";
                $html .= "</div>";
                $html .= "</a>";
            }

            $html .= "</div>";
        }

        // Footer text
        if (!empty(trim($dto->footerText))) {
            $html .= "<div class=\"teaser-footer\">{$dto->footerText}</div>";
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

    protected function getSupportedType(): string
    {
        return 'teaser';
    }
}