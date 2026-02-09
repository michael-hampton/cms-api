<?php

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\ProductBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class ProductBlockRenderer implements EmailBlockRenderer
{
    public $type = 'product';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof ProductBlockData) {
            return RenderedBlock::skipped();
        }

        $html = [];
        $html[] = '<div style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 20px 0;">';

        if ($blockData->image && isset($blockData->image['src'])) {
            $html[] = sprintf(
                '<img src="%s" alt="%s" style="max-width: 100%%; height: auto; border-radius: 4px; margin-bottom: 15px;">',
                Str::sanitize($blockData->image['src']),
                Str::sanitize($blockData->name)
            );
        }

        $html[] = '<h3 style="color: #333; margin: 0 0 10px 0; font-size: 20px;">' . Str::sanitize($blockData->name) . '</h3>';

        if ($blockData->description) {
            $html[] = '<p style="color: #666; margin: 0 0 15px 0; font-size: 14px;">' . Str::sanitize($blockData->description) . '</p>';
        }

        $html[] = '<div style="margin: 15px 0;">';
        if ($blockData->salePrice && $blockData->salePrice > 0 && $blockData->salePrice < $blockData->price) {
            $html[] = sprintf(
                '<span style="color: #999; text-decoration: line-through; margin-right: 10px;">%s%.2f</span>',
                Str::sanitize($blockData->currency),
                $blockData->price
            );
            $html[] = sprintf(
                '<span style="color: #d9534f; font-size: 20px; font-weight: bold;">%s%.2f</span>',
                Str::sanitize($blockData->currency),
                $blockData->salePrice
            );
        } else {
            $html[] = sprintf(
                '<span style="color: #333; font-size: 20px; font-weight: bold;">%s%.2f</span>',
                Str::sanitize($blockData->currency),
                $blockData->price
            );
        }
        $html[] = '</div>';

        if ($blockData->link) {
            $html[] = sprintf(
                '<a href="%s" style="display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;">%s</a>',
                Str::sanitize($blockData->link),
                Str::sanitize($blockData->linkText)
            );
        }

        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}