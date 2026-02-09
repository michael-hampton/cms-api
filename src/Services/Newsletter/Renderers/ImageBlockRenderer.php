<?php

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\ImageBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class ImageBlockRenderer implements EmailBlockRenderer
{
    public function supports(string $type): bool
    {
        return $type === 'image';
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof ImageBlockData) {
            return RenderedBlock::skipped();
        }

        $html = [];
        $html[] = '<div style="margin: 20px 0;">';

        $imgTag = sprintf(
            '<img src="%s" alt="%s" style="max-width: 100%%; height: auto; display: block;">',
            Str::sanitize($blockData->src),
            Str::sanitize($blockData->alt)
        );

        if ($blockData->linkUrl) {
            $html[] = sprintf(
                '<a href="%s">%s</a>',
                Str::sanitize($blockData->linkUrl),
                $imgTag
            );
        } else {
            $html[] = $imgTag;
        }

        if ($blockData->caption) {
            $html[] = '<p style="color: #666; font-size: 14px; font-style: italic; margin: 10px 0 0 0;">';
            $html[] = Str::sanitize($blockData->caption);
            $html[] = '</p>';
        }

        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}