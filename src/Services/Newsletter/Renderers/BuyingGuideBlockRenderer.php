<?php

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\BuyingGuideBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class BuyingGuideBlockRenderer implements EmailBlockRenderer
{
    public $type = 'buying-guide';
    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof BuyingGuideBlockData) {
            return RenderedBlock::skipped();
        }

        $html = [];
        $html[] = '<div style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 20px 0;">';

        if ($blockData->sponsored) {
            $html[] = '<span style="background-color: #ffc107; color: #333; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; display: inline-block; margin-bottom: 15px;">Sponsored</span>';
        }

        if ($blockData->image && isset($blockData->image['src'])) {
            $html[] = '<img src="' . Str::sanitize($blockData->image['src']) . '" alt="' . Str::sanitize($blockData->title) . '" style="max-width: 100%; height: auto; border-radius: 4px; margin-bottom: 15px;">';
        }

        $html[] = '<h3 style="color: #333; margin: 0 0 10px 0; font-size: 22px;">' . Str::sanitize($blockData->title) . '</h3>';

        if ($blockData->subtitle) {
            $html[] = '<p style="color: #666; margin: 0 0 20px 0; font-size: 16px;">' . Str::sanitize($blockData->subtitle) . '</p>';
        }

        // Specs
        if (!empty($blockData->specs)) {
            $html[] = '<h4 style="color: #333; margin: 20px 0 10px 0; font-size: 18px;">Specifications</h4>';
            $html[] = '<table style="width: 100%; border-collapse: collapse;">';
            foreach ($blockData->specs as $spec) {
                $specText = Str::sanitize($spec['text'] ?? '');
                $specValue = Str::sanitize($spec['value'] ?? '');
                $html[] = '<tr>';
                $html[] = "<td style=\"padding: 8px; border-bottom: 1px solid #eee; font-weight: bold; color: #333;\">{$specText}</td>";
                $html[] = "<td style=\"padding: 8px; border-bottom: 1px solid #eee; color: #666;\">{$specValue}</td>";
                $html[] = '</tr>';
            }
            $html[] = '</table>';
        }

        // Pros and Cons
        if ($blockData->showReviewPanel && (!empty($blockData->pros) || !empty($blockData->cons))) {
            $html[] = '<div style="margin-top: 20px;">';
            $html[] = '<table style="width: 100%;">';
            $html[] = '<tr>';

            if (!empty($blockData->pros)) {
                $html[] = '<td style="width: 50%; padding-right: 10px; vertical-align: top;">';
                $html[] = '<h5 style="color: #28a745; margin: 0 0 10px 0;">✓ Advantages</h5>';
                $html[] = '<ul style="margin: 0; padding-left: 20px; color: #333;">';
                foreach ($blockData->pros as $pro) {
                    $html[] = '<li style="margin-bottom: 5px;">' . Str::sanitize($pro) . '</li>';
                }
                $html[] = '</ul>';
                $html[] = '</td>';
            }

            if (!empty($blockData->cons)) {
                $html[] = '<td style="width: 50%; padding-left: 10px; vertical-align: top;">';
                $html[] = '<h5 style="color: #dc3545; margin: 0 0 10px 0;">✗ Considerations</h5>';
                $html[] = '<ul style="margin: 0; padding-left: 20px; color: #333;">';
                foreach ($blockData->cons as $con) {
                    $html[] = '<li style="margin-bottom: 5px;">' . Str::sanitize($con) . '</li>';
                }
                $html[] = '</ul>';
                $html[] = '</td>';
            }

            $html[] = '</tr>';
            $html[] = '</table>';
            $html[] = '</div>';
        }

        if ($blockData->url) {
            $html[] = '<div style="margin-top: 20px;">';
            $html[] = '<a href="' . Str::sanitize($blockData->url) . '" style="display: inline-block; padding: 12px 30px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;">';
            $html[] = Str::sanitize($blockData->linkText);
            $html[] = '</a>';
            $html[] = '</div>';
        }

        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}