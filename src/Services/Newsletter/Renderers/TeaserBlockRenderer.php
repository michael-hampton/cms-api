<?php

declare(strict_types=1);

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\TeaserBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class TeaserBlockRenderer implements EmailBlockRenderer
{
    public $type = 'teaser';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $context): RenderedBlock
    {
        if (!$blockData instanceof TeaserBlockData) {
            return RenderedBlock::skipped();
        }

        $bgColor = match ($blockData->theme) {
            'dark' => '#333333',
            'primary' => '#007bff',
            'success' => '#28a745',
            default => '#f8f9fa',
        };
        $textColor = in_array($blockData->theme, ['dark', 'primary', 'success'], true) ? '#ffffff' : '#333333';

        $baseStyle = "background-color: {$bgColor}; color: {$textColor}; padding: 30px 20px; border-radius: 8px; margin: 30px 0;";
        $wrapperStyle = $blockData->style->mergeIntoCss($baseStyle);

        $html = [];
        $html[] = "<div style=\"{$wrapperStyle}\">";

        if ($blockData->copy) {
            $baseCopyStyle = 'margin-bottom: 25px; font-size: 16px; line-height: 1.6;';
            $copyStyle = $blockData->style->mergeIntoCss($baseCopyStyle);
            $html[] = "<div style=\"{$copyStyle}\">" . $blockData->copy . '</div>';
        }

        if (!empty($blockData->items)) {
            $html[] = '<table style="width: 100%;"><tr>';
            $itemCount = count($blockData->items);
            $cellWidth = floor(100 / min($itemCount, 3));

            foreach (array_slice($blockData->items, 0, 3) as $item) {
                $icon = match ($item['icon'] ?? 'arrow') {
                    'check' => '✓',
                    'star' => '★',
                    'circle' => '●',
                    'info' => 'ℹ️',
                    'link' => '🔗',
                    default => '→',
                };

                $html[] = "<td style=\"width: {$cellWidth}%; padding: 15px; vertical-align: top; text-align: center;\">";
                $html[] = "<div style=\"font-size: 32px; margin-bottom: 10px;\">{$icon}</div>";

                if (!empty($item['title'])) {
                    $html[] = '<h4 style="margin: 0 0 8px 0; font-size: 18px; color: ' . $textColor . ';">' . Str::sanitize($item['title']) . '</h4>';
                }
                if (!empty($item['description'])) {
                    $html[] = '<p style="margin: 0 0 15px 0; font-size: 14px; color: ' . $textColor . '; opacity: 0.9; line-height: 1.5;">' . Str::sanitize($item['description']) . '</p>';
                }
                if (!empty($item['link'])) {
                    $btnBg = in_array($blockData->theme, ['dark', 'primary'], true) ? '#ffffff' : '#007bff';
                    $btnText = in_array($blockData->theme, ['dark', 'primary'], true) ? '#333333' : '#ffffff';
                    $html[] = '<a href="' . Str::sanitize($item['link']) . '" style="display: inline-block; padding: 8px 16px; background-color: ' . $btnBg . '; color: ' . $btnText . '; text-decoration: none; border-radius: 4px; font-size: 14px; font-weight: bold;">Learn More</a>';
                }

                $html[] = '</td>';
            }

            $html[] = '</tr></table>';
        }

        if ($blockData->footerText) {
            $html[] = '<div style="margin-top: 25px; font-size: 14px; text-align: center; opacity: 0.9;">' . $blockData->footerText . '</div>';
        }

        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}