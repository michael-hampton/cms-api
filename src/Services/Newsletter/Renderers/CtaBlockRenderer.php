<?php

declare(strict_types=1);

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\CtaBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class CtaBlockRenderer implements EmailBlockRenderer
{
    public $type = 'cta';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $context): RenderedBlock
    {
        if (!$blockData instanceof CtaBlockData) {
            return RenderedBlock::skipped();
        }

        $alignStyle = match ($blockData->alignment) {
            'left' => 'text-align: left;',
            'right' => 'text-align: right;',
            default => 'text-align: center;',
        };

        $bgColor = match ($blockData->ctaStyle) {
            'secondary' => '#6c757d',
            'success' => '#28a745',
            'danger' => '#dc3545',
            'warning' => '#ffc107',
            default => '#007bff',
        };

        $textColor = in_array($blockData->ctaStyle, ['warning'], true) ? '#333' : 'white';

        $padding = match ($blockData->size) {
            'small' => 'padding: 8px 16px; font-size: 14px;',
            'large' => 'padding: 16px 40px; font-size: 18px;',
            default => 'padding: 12px 30px; font-size: 16px;',
        };

        $linkAttrs = '';
        if ($blockData->noFollow) $linkAttrs .= ' rel="nofollow"';
        if ($blockData->sponsored) $linkAttrs .= ' rel="sponsored"';
        if ($blockData->openInNewTab) $linkAttrs .= ' target="_blank"';

        $baseBtnStyle = "{$padding} background-color: {$bgColor}; color: {$textColor}; display: inline-block; text-decoration: none; border-radius: 4px; font-weight: bold;";
        $btnStyle = $blockData->style->mergeIntoCss($baseBtnStyle);

        $baseWrapperStyle = "margin: 25px 0; {$alignStyle}";
        $wrapperStyle = $blockData->style->mergeIntoCss($baseWrapperStyle);

        $html = "<div style=\"{$wrapperStyle}\">"
            . '<a href="' . Str::sanitize($blockData->url) . '"' . $linkAttrs . " style=\"{$btnStyle}\">"
            . Str::sanitize($blockData->text)
            . ($blockData->sponsored ? ' <span style="background-color: #ffc107; color: #333; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 5px;">Sponsored</span>' : '')
            . '</a></div>';

        return RenderedBlock::rendered($html);
    }
}