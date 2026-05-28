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

        $customWidth = $blockData->imageWidth ? 'width: ' . Str::sanitize($blockData->imageWidth) . ';' : '';
        $maxHeight = $blockData->maxHeight ? 'max-height: ' . Str::sanitize($blockData->maxHeight) . ';' : '';
        $objectFit = $blockData->objectFit ? 'object-fit: ' . Str::sanitize($blockData->objectFit) . ';' : '';
        $objectPosition = $blockData->objectPosition ? 'object-position: ' . Str::sanitize($blockData->objectPosition) . ';' : '';
        $padding = $blockData->imagePadding ? 'padding: ' . Str::sanitize($blockData->imagePadding) . ';' : '';
        $imgStyle = "{$widthStyle}{$customWidth}{$maxHeight}{$objectFit}{$objectPosition}{$padding} height: auto; display: block; border-radius: 4px;";
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

        // Endorsements
        if (!empty($blockData->endorsements)) {
            $html[] = '<div style="position: relative; margin-top: -30px;">';
            foreach ($blockData->endorsements as $position => $endorsement) {
                $positionStyle = match ($position) {
                    'top-left' => 'top: 10px; left: 10px;',
                    'top-right' => 'top: 10px; right: 10px;',
                    'bottom-left' => 'bottom: 10px; left: 10px;',
                    'bottom-right' => 'bottom: 10px; right: 10px;',
                    default => 'top: 10px; right: 10px;'
                };

                if (isset($endorsement['url'])) {
                    $html[] = '<img src="' . Str::sanitize($endorsement['url']) . '" alt="' . Str::sanitize($endorsement['alt'] ?? 'Endorsement') . '" style="position: absolute; ' . $positionStyle . ' max-width: 100px; height: auto;">';
                }
            }
            $html[] = '</div>';
        }

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
