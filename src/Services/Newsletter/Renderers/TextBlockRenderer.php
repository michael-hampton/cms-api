<?php

declare(strict_types=1);

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\TextBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class TextBlockRenderer implements EmailBlockRenderer
{
    public $type = 'text';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof TextBlockData) {
            return RenderedBlock::skipped();
        }

        // Build the base paragraph style, then merge any block-level overrides.
        $textColor = $blockData->textColor ? Str::sanitize($blockData->textColor) : '#333';
        $baseParaStyle = "color: {$textColor}; font-size: 16px; line-height: 1.6; margin: 0 0 15px 0;";
        $paraStyle = $blockData->style->mergeIntoCss($baseParaStyle);

        // If the block has a background colour or padding, wrap paragraphs in a container div.
        $needsWrapper = $blockData->style->backgroundColor !== null || $blockData->style->padding !== null;

        $paragraphsHtml = '';
        foreach ($blockData->paragraphs as $paragraph) {
            $paragraphsHtml .= '<p style="' . $paraStyle . '">' . Str::sanitize($paragraph) . '</p>' . "\n";
        }

        if ($needsWrapper) {
            $wrapperCss = $blockData->style->toWrapperCss();
            $html = "<div style=\"{$wrapperCss}\">{$paragraphsHtml}</div>";
        } else {
            $html = $paragraphsHtml;
        }

        return RenderedBlock::rendered($html);
    }
}
