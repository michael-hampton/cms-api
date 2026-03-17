<?php

declare(strict_types=1);

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\SectionBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class SectionBlockRenderer implements EmailBlockRenderer
{
    private const SIZES = [
        'h1' => '32px', 'h2' => '28px', 'h3' => '24px',
        'h4' => '20px', 'h5' => '18px', 'h6' => '16px',
    ];
    public $type = 'section';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $context): RenderedBlock
    {
        if (!$blockData instanceof SectionBlockData) {
            return RenderedBlock::skipped();
        }

        $size = self::SIZES[$blockData->headingType] ?? '28px';
        $tag = $blockData->headingType;
        $baseStyle = "color: #333; font-size: {$size}; margin: 30px 0 15px 0; padding-bottom: 10px; border-bottom: 2px solid #007bff;";
        $style = $blockData->style->mergeIntoCss($baseStyle);

        return RenderedBlock::rendered(
            "<{$tag} style=\"{$style}\">" . Str::sanitize($blockData->title) . "</{$tag}>"
        );
    }
}