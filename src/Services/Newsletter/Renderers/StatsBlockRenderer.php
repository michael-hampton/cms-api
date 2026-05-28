<?php

declare(strict_types=1);

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\StatsBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class StatsBlockRenderer implements EmailBlockRenderer
{
    public $type = 'stats';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $context): RenderedBlock
    {
        if (!$blockData instanceof StatsBlockData) {
            return RenderedBlock::skipped();
        }

        $baseStyle = 'margin: 30px 0;';
        $wrapperStyle = $blockData->style->mergeIntoCss($baseStyle);

        $html = [];
        $html[] = "<div style=\"{$wrapperStyle}\">";

        if ($blockData->title) {
            $baseTitleStyle = 'color: #333; margin: 0 0 20px 0; font-size: 24px; text-align: center;';
            $titleStyle = $blockData->style->mergeIntoCss($baseTitleStyle);
            $html[] = "<h3 style=\"{$titleStyle}\">" . Str::sanitize($blockData->title) . '</h3>';
        }

        $tableStyle = $blockData->layout === 'horizontal'
            ? 'width: 100%; table-layout: fixed;'
            : 'width: 100%;';
        $html[] = '<table style="' . $tableStyle . '"><tr>';

        $statCount = count($blockData->stats);
        $cellWidth = $statCount > 0 ? floor(100 / $statCount) : 100;

        foreach ($blockData->stats as $stat) {
            $number = Str::sanitize($stat['number'] ?? '');
            $label = Str::sanitize($stat['label'] ?? '');
            $description = isset($stat['description']) ? Str::sanitize($stat['description']) : null;
            $icon = $stat['icon'] ?? '';

            $baseNumberStyle = 'color: #007bff; font-size: 36px; font-weight: bold; margin-bottom: 5px;';
            $numberStyle = $blockData->style->mergeIntoCss($baseNumberStyle);

            $html[] = "<td style=\"width: {$cellWidth}%; text-align: center; padding: 20px; vertical-align: top;\">";
            if ($icon) {
                $html[] = "<div style=\"font-size: 32px; margin-bottom: 10px;\">{$icon}</div>";
            }
            $html[] = "<div style=\"{$numberStyle}\">{$number}</div>";
            $html[] = "<div style=\"color: #333; font-size: 16px; font-weight: bold; margin-bottom: 5px;\">{$label}</div>";
            if ($description) {
                $html[] = "<div style=\"color: #666; font-size: 14px;\">{$description}</div>";
            }
            $html[] = '</td>';
        }

        $html[] = '</tr></table>';
        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}
