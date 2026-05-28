<?php

declare(strict_types=1);

namespace App\Services\Newsletter\Renderers;

use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\DividerBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class DividerBlockRenderer implements EmailBlockRenderer
{
    public $type = 'divider';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $context): RenderedBlock
    {
        if (!$blockData instanceof DividerBlockData) {
            return RenderedBlock::skipped();
        }

        $borderCss = match ($blockData->lineStyle) {
            'dashed' => 'dashed',
            'dotted' => 'dotted',
            'double' => 'double',
            default => 'solid',
        };

        $thickness = $blockData->thickness ? Str::sanitize($blockData->thickness) : '2px';
        $color = $blockData->dividerColor ? Str::sanitize($blockData->dividerColor) : '#ddd';
        $marginTop = $blockData->marginTop ? Str::sanitize($blockData->marginTop) : '25px';
        $marginBottom = $blockData->marginBottom ? Str::sanitize($blockData->marginBottom) : '25px';
        $baseStyle = "border: none; border-top: {$thickness} {$borderCss} {$color}; margin-top: {$marginTop}; margin-bottom: {$marginBottom};";
        $resolvedStyle = $blockData->style->mergeIntoCss($baseStyle);

        return RenderedBlock::rendered("<hr style=\"{$resolvedStyle}\">");
    }
}
