<?php

declare(strict_types=1);

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\HeadingBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class HeadingBlockRenderer implements EmailBlockRenderer
{
    private const SIZES = [
        1 => '32px',
        2 => '28px',
        3 => '24px',
        4 => '20px',
        5 => '18px',
        6 => '16px',
    ];
    public $type = 'heading';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof HeadingBlockData) {
            return RenderedBlock::skipped();
        }

        $defaultSize = self::SIZES[$blockData->level] ?? '24px';
        $level = $blockData->level;

        // Start from renderer defaults, then merge block-level style overrides.
        $baseHeadingStyle = "color: #333; font-size: {$defaultSize}; margin: 20px 0 10px 0; font-weight: bold;";
        $headingStyle = $blockData->style->mergeIntoCss($baseHeadingStyle);

        $html = "<h{$level} style=\"{$headingStyle}\">" . Str::sanitize($blockData->text) . "</h{$level}>\n";

        if ($blockData->subtitle) {
            $baseSubStyle = 'color: #666; font-size: 16px; margin: 0 0 20px 0;';
            $subStyle = $blockData->style->mergeIntoCss($baseSubStyle);
            $html .= "<p style=\"{$subStyle}\">" . Str::sanitize($blockData->subtitle) . "</p>\n";
        }

        // If padding or background colour is set, wrap the heading group.
        if ($blockData->style->padding !== null || $blockData->style->backgroundColor !== null) {
            $wrapperCss = $blockData->style->toWrapperCss();
            // Strip font-size and color from the wrapper to avoid double-applying.
            $wrapperOnly = preg_replace('/font-size:[^;]+;?/', '', $wrapperCss);
            $wrapperOnly = preg_replace('/color:[^;]+;?/', '', $wrapperOnly ?? '');
            if (trim($wrapperOnly ?? '', ';') !== '') {
                $html = "<div style=\"{$wrapperOnly}\">{$html}</div>";
            }
        }

        return RenderedBlock::rendered($html);
    }
}