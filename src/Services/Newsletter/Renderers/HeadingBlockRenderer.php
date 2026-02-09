<?php

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
        6 => '16px'
    ];

    public function supports(string $type): bool
    {
        return $type === 'heading';
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof HeadingBlockData) {
            return RenderedBlock::skipped();
        }

        $size = self::SIZES[$blockData->level] ?? '24px';
        $level = $blockData->level;

        $html = [];
        $html[] = "<h{$level} style=\"color: #333; font-size: {$size}; margin: 20px 0 10px 0; font-weight: bold;\">";
        $html[] = Str::sanitize($blockData->text);
        $html[] = "</h{$level}>";

        if ($blockData->subtitle) {
            $html[] = '<p style="color: #666; font-size: 16px; margin: 0 0 20px 0;">';
            $html[] = Str::sanitize($blockData->subtitle);
            $html[] = '</p>';
        }

        return RenderedBlock::rendered(implode("\n", $html));
    }
}