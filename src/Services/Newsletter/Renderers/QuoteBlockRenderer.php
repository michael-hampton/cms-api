<?php

declare(strict_types=1);

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\QuoteBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class QuoteBlockRenderer implements EmailBlockRenderer
{
    public $type = 'quote';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $context): RenderedBlock
    {
        if (!$blockData instanceof QuoteBlockData) {
            return RenderedBlock::skipped();
        }

        $baseStyle = 'border-left: 4px solid #007bff; padding-left: 20px; margin: 20px 0; font-style: italic;';
        $wrapperStyle = $blockData->style->mergeIntoCss($baseStyle);

        $baseTextStyle = 'color: #333; font-size: 18px; line-height: 1.6; margin: 0;';
        $textStyle = $blockData->style->mergeIntoCss($baseTextStyle);

        $html = [];
        $html[] = "<blockquote style=\"{$wrapperStyle}\">";
        $html[] = "<p style=\"{$textStyle}\">" . Str::sanitize($blockData->text) . '</p>';

        if ($blockData->attribution) {
            $html[] = '<cite style="color: #666; font-size: 14px; font-style: normal; display: block; margin-top: 10px;">— ' . Str::sanitize($blockData->attribution) . '</cite>';
        }

        $html[] = '</blockquote>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}