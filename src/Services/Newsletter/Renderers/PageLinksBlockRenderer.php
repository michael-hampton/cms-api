<?php

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\PageLinksBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class PageLinksBlockRenderer implements EmailBlockRenderer
{
    public $type = 'page-links';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof PageLinksBlockData) {
            return RenderedBlock::skipped();
        }

        $html = [];
        $html[] = '<div style="margin: 30px 0;">';

        if ($blockData->title) {
            $html[] = '<h3 style="color: #333; margin: 0 0 20px 0; font-size: 24px;">' . Str::sanitize($blockData->title) . '</h3>';
        }

        $html[] = '<table style="width: 100%;"><tr>';

        $cellWidth = floor(100 / $blockData->columns);
        $counter = 0;

        foreach ($blockData->links as $link) {
            if ($counter > 0 && $counter % $blockData->columns === 0) {
                $html[] = '</tr><tr>';
            }

            $html[] = "<td style=\"width: {$cellWidth}%; padding: 15px; vertical-align: top;\">";

            if ($blockData->showImages && isset($link['imageUrl'])) {
                $html[] = '<a href="' . Str::sanitize($link['pageUrl']) . '">';
                $html[] = '<img src="' . Str::sanitize($link['imageUrl']) . '" alt="' . Str::sanitize($link['title']) . '" style="width: 100%; height: auto; border-radius: 4px; margin-bottom: 10px;">';
                $html[] = '</a>';
            } elseif (!empty($link['icon'])) {
                $html[] = '<div style="font-size: 32px; margin-bottom: 10px; text-align: center;">' . $link['icon'] . '</div>';
            }

            $html[] = '<h4 style="margin: 0 0 8px 0; font-size: 16px;">';
            $html[] = '<a href="' . Str::sanitize($link['pageUrl']) . '" style="color: #333; text-decoration: none; font-weight: bold;">' . Str::sanitize($link['title']) . '</a>';
            $html[] = '</h4>';

            if ($blockData->showDescriptions && !empty($link['description'])) {
                $html[] = '<p style="color: #666; font-size: 14px; margin: 0; line-height: 1.5;">' . Str::sanitize($link['description']) . '</p>';
            }

            $html[] = '</td>';
            $counter++;
        }

        $html[] = '</tr></table>';
        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}