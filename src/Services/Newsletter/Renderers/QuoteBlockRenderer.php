<?php

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\QuoteBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class QuoteBlockRenderer implements EmailBlockRenderer
{
    public function supports(string $type): bool
    {
        return $type === 'quote';
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof QuoteBlockData) {
            return RenderedBlock::skipped();
        }

        $html = [];
        $html[] = '<blockquote style="border-left: 4px solid #007bff; padding-left: 20px; margin: 20px 0; font-style: italic;">';
        $html[] = '<p style="color: #333; font-size: 18px; line-height: 1.6; margin: 0;">';
        $html[] = Str::sanitize($blockData->text);
        $html[] = '</p>';

        if ($blockData->attribution) {
            $html[] = '<cite style="color: #666; font-size: 14px; font-style: normal; display: block; margin-top: 10px;">';
            $html[] = '— ' . Str::sanitize($blockData->attribution);
            $html[] = '</cite>';
        }

        $html[] = '</blockquote>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}