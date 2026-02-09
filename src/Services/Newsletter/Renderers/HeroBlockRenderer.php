<?php

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\HeroBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class HeroBlockRenderer implements EmailBlockRenderer
{
    public function supports(string $type): bool
    {
        return $type === 'hero';
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof HeroBlockData) {
            return RenderedBlock::skipped();
        }

        $html = [];
        $html[] = '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 30px; border-radius: 8px; text-align: center; margin: 20px 0;">';
        $html[] = '<h1 style="color: white; margin: 0 0 15px 0; font-size: 32px;">' . Str::sanitize($blockData->title) . '</h1>';

        if ($blockData->subtitle) {
            $html[] = '<p style="color: white; margin: 0 0 20px 0; font-size: 18px;">' . Str::sanitize($blockData->subtitle) . '</p>';
        }

        if ($blockData->ctaText && $blockData->ctaUrl) {
            $html[] = '<a href="' . Str::sanitize($blockData->ctaUrl) . '" style="display: inline-block; padding: 12px 30px; background-color: white; color: #667eea; text-decoration: none; border-radius: 4px; font-size: 16px; font-weight: bold;">';
            $html[] = Str::sanitize($blockData->ctaText);
            $html[] = '</a>';
        }

        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}