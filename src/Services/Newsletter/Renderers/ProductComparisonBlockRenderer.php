<?php

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\ProductComparisonBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class ProductComparisonBlockRenderer implements EmailBlockRenderer
{
    public $type = 'product-comparison';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof ProductComparisonBlockData) {
            return RenderedBlock::skipped();
        }

        $html = [];
        $html[] = '<div style="margin: 20px 0;">';
        $html[] = '<h3 style="color: #333; margin: 0 0 20px 0; font-size: 24px;">' . Str::sanitize($blockData->title) . '</h3>';

        $html[] = '<table style="width: 100%; border-collapse: collapse;">';

        // Header row
        $html[] = '<tr>';
        $html[] = '<th style="background-color: #f8f9fa; padding: 12px; text-align: left; border: 1px solid #ddd; font-weight: bold;"></th>';
        $html[] = '<th style="background-color: #e7f3ff; padding: 12px; text-align: center; border: 1px solid #ddd; font-weight: bold;">' . Str::sanitize($blockData->productA) . '</th>';
        $html[] = '<th style="background-color: #fff3e7; padding: 12px; text-align: center; border: 1px solid #ddd; font-weight: bold;">' . Str::sanitize($blockData->productB) . '</th>';
        $html[] = '</tr>';

        // Comparison rows — prefer itemA/itemB (frontend form), fall back to items[].value (seeders/legacy)
        foreach ($blockData->comparisons as $comparison) {
            $subtitle = Str::sanitize($comparison['subtitle'] ?? '');
            $itemA = Str::sanitize(
                $comparison['itemA']
                    ?? $comparison['items'][0]['value']
                    ?? ''
            );
            $itemB = Str::sanitize(
                $comparison['itemB']
                    ?? $comparison['items'][1]['value']
                    ?? ''
            );

            $html[] = '<tr>';
            $html[] = "<td style=\"padding: 12px; border: 1px solid #ddd; font-weight: bold; color: #333;\">{$subtitle}</td>";
            $html[] = "<td style=\"padding: 12px; border: 1px solid #ddd; text-align: center; color: #333;\">{$itemA}</td>";
            $html[] = "<td style=\"padding: 12px; border: 1px solid #ddd; text-align: center; color: #333;\">{$itemB}</td>";
            $html[] = '</tr>';
        }

        $html[] = '</table>';
        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}