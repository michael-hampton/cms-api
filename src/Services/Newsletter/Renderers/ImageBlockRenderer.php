<?php

declare(strict_types=1);

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\ImageBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class ImageBlockRenderer implements EmailBlockRenderer
{
    public $type = 'image';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $context): RenderedBlock
    {
        if (!$blockData instanceof ImageBlockData) {
            return RenderedBlock::skipped();
        }

        $alignmentStyle = match ($blockData->alignment) {
            'left' => 'margin-right: auto;',
            'right' => 'margin-left: auto;',
            'center' => 'margin: 0 auto;',
            default => '',
        };

        $widthStyle = match ($blockData->layout) {
            'small' => 'max-width: 300px;',
            'medium' => 'max-width: 500px;',
            'large' => 'max-width: 700px;',
            default => 'max-width: 100%;',
        };

        $baseWrapperStyle = "margin: 20px 0; {$alignmentStyle}";
        $wrapperStyle = $blockData->style->mergeIntoCss($baseWrapperStyle);

        $imgStyle = "{$widthStyle} height: auto; display: block; border-radius: 4px;";
        $imgTag = '<img src="' . Str::sanitize($blockData->src) . '" alt="' . Str::sanitize($blockData->alt) . "\" style=\"{$imgStyle}\">";

        $linkAttrs = '';
        if ($blockData->noFollow) $linkAttrs .= ' rel="nofollow"';
        if ($blockData->sponsored) $linkAttrs .= ' rel="sponsored"';
        if ($blockData->openInNewTab) $linkAttrs .= ' target="_blank"';

        $html = [];
        $html[] = "<div style=\"{$wrapperStyle}\">";
        $html[] = $blockData->linkUrl
            ? '<a href="' . Str::sanitize($blockData->linkUrl) . '"' . $linkAttrs . '>' . $imgTag . '</a>'
            : $imgTag;

        if ($blockData->caption) {
            $html[] = '<p style="color: #666; font-size: 14px; font-style: italic; margin: 10px 0 0 0; text-align: center;">' . Str::sanitize($blockData->caption) . '</p>';
        }

        if ($blockData->credit) {
            $html[] = '<p style="color: #999; font-size: 12px; margin: 5px 0 0 0; text-align: center;">📷 ' . Str::sanitize($blockData->credit) . '</p>';
        }

        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}