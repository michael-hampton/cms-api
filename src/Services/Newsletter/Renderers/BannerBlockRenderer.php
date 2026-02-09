<?php

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BannerBlockData;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class BannerBlockRenderer implements EmailBlockRenderer
{
    public function supports(string $type): bool
    {
        return $type === 'banner';
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof BannerBlockData) {
            return RenderedBlock::skipped();
        }

        $html = [];
        $html[] = "<div style=\"background-color: {$blockData->backgroundColor}; color: {$blockData->textColor}; padding: 25px; border-radius: 8px; margin: 20px 0;\">";
        $html[] = "<h3 style=\"color: {$blockData->textColor}; margin: 0 0 10px 0; font-size: 24px;\">" . Str::sanitize($blockData->title) . "</h3>";

        if ($blockData->subtitle) {
            $html[] = "<p style=\"color: {$blockData->textColor}; margin: 0 0 15px 0; font-size: 16px;\">" . Str::sanitize($blockData->subtitle) . "</p>";
        }

        if ($blockData->ctaText && $blockData->ctaUrl) {
            $html[] = '<a href="' . Str::sanitize($blockData->ctaUrl) . '" style="display: inline-block; padding: 10px 20px; background-color: white; color: ' . $blockData->backgroundColor . '; text-decoration: none; border-radius: 4px; font-weight: bold;">';
            $html[] = Str::sanitize($blockData->ctaText);
            $html[] = '</a>';
        }

        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}