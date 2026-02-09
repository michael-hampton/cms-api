<?php

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\GalleryBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class GalleryBlockRenderer implements EmailBlockRenderer
{
    public $type = 'gallery';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof GalleryBlockData) {
            return RenderedBlock::skipped();
        }

        // For email, we'll render as a grid regardless of layout (carousel doesn't work well in email)
        $html = [];
        $html[] = '<div style="margin: 30px 0;">';
        $html[] = '<table style="width: 100%;"><tr>';

        $slideCount = count($blockData->slides);
        $columns = min($slideCount, 3);
        $cellWidth = floor(100 / $columns);

        foreach ($blockData->slides as $index => $slide) {
            if ($index > 0 && $index % $columns === 0) {
                $html[] = '</tr><tr>';
            }

            $html[] = "<td style=\"width: {$cellWidth}%; padding: 10px; vertical-align: top;\">";

            if (isset($slide['image']['src'])) {
                if (!empty($slide['link'])) {
                    $html[] = '<a href="' . Str::sanitize($slide['link']) . '">';
                }

                $html[] = '<img src="' . Str::sanitize($slide['image']['src']) . '" alt="' . Str::sanitize($slide['title'] ?? '') . '" style="width: 100%; height: auto; border-radius: 4px; display: block;">';

                if (!empty($slide['link'])) {
                    $html[] = '</a>';
                }
            }

            if (!empty($slide['title'])) {
                $html[] = '<h5 style="margin: 10px 0 5px 0; font-size: 16px; color: #333;">' . Str::sanitize($slide['title']) . '</h5>';
            }

            if (!empty($slide['description'])) {
                $html[] = '<p style="margin: 0; font-size: 14px; color: #666; line-height: 1.5;">' . Str::sanitize($slide['description']) . '</p>';
            }

            if (!empty($slide['caption'])) {
                $html[] = '<p style="margin: 5px 0 0 0; font-size: 12px; color: #999; font-style: italic;">' . Str::sanitize($slide['caption']) . '</p>';
            }

            $html[] = '</td>';
        }

        $html[] = '</tr></table>';
        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}