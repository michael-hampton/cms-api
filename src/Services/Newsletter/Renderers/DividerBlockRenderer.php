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

        $baseStyle = "border: none; border-top: 2px {$borderCss} #ddd; margin: 25px 0;";
        $resolvedStyle = $blockData->style->mergeIntoCss($baseStyle);

        return RenderedBlock::rendered("<hr style=\"{$resolvedStyle}\">");
    }
}