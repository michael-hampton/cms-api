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
    public $type = 'image';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof ImageBlockData) {
            return RenderedBlock::skipped();
        }

        $alignmentStyle = match ($blockData->alignment) {
            'left' => 'margin-right: auto;',
            'right' => 'margin-left: auto;',
            'center' => 'margin: 0 auto;',
            default => ''
        };

        $widthStyle = match ($blockData->layout) {
            'small' => 'max-width: 300px;',
            'medium' => 'max-width: 500px;',
            'large' => 'max-width: 700px;',
            default => 'max-width: 100%;'
        };

        $html = [];
        $html[] = '<div style="margin: 20px 0; ' . $alignmentStyle . '">';

        $imgTag = '<img src="' . Str::sanitize($blockData->src) . '" alt="' . Str::sanitize($blockData->alt) . '" style="' . $widthStyle . ' height: auto; display: block; border-radius: 4px;">';

        $linkAttrs = '';
        if ($blockData->noFollow) $linkAttrs .= ' rel="nofollow"';
        if ($blockData->sponsored) $linkAttrs .= ' rel="sponsored"';
        if ($blockData->openInNewTab) $linkAttrs .= ' target="_blank"';

        if ($blockData->linkUrl) {
            $html[] = '<a href="' . Str::sanitize($blockData->linkUrl) . '"' . $linkAttrs . '>' . $imgTag . '</a>';
        } else {
            $html[] = $imgTag;
        }

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
            $html[] = '<p style="color: #666; font-size: 14px; font-style: italic; margin: 10px 0 0 0; text-align: center;">';
            $html[] = Str::sanitize($blockData->caption);
            $html[] = '</p>';
        }

        if ($blockData->credit) {
            $html[] = '<p style="color: #999; font-size: 12px; margin: 5px 0 0 0; text-align: center;">';
            $html[] = '📷 ' . Str::sanitize($blockData->credit);
            $html[] = '</p>';
        }

        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}