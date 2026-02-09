<?php

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\CtaBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class CtaBlockRenderer implements EmailBlockRenderer
{
    public function supports(string $type): bool
    {
        return $type === 'cta';
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof CtaBlockData) {
            return RenderedBlock::skipped();
        }

        $alignStyle = match ($blockData->alignment) {
            'left' => 'text-align: left;',
            'right' => 'text-align: right;',
            default => 'text-align: center;'
        };

        $html = [];
        $html[] = "<div style=\"margin: 25px 0; {$alignStyle}\">";
        $html[] = sprintf(
            '<a href="%s" style="display: inline-block; padding: 12px 30px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 16px; font-weight: bold;">%s</a>',
            Str::sanitize($blockData->url),
            Str::sanitize($blockData->text)
        );
        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}