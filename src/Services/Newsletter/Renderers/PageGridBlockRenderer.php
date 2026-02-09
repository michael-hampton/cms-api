<?php

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\PageGridBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class PageGridBlockRenderer implements EmailBlockRenderer
{
    public $type = 'page_grid';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof PageGridBlockData) return RenderedBlock::skipped();

        $html = [];
        if ($blockData->title) {
            $html[] = '<h2 style="font-size: 24px; color: #333; margin-bottom: 10px; text-align: center;">' . Str::sanitize($blockData->title) . '</h2>';
        }

        $html[] = '<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>';

        $colWidth = floor(100 / $blockData->columns) . '%';
        foreach ($blockData->pages as $index => $page) {
            if ($index > 0 && $index % $blockData->columns == 0) $html[] = '</tr><tr>';

            $html[] = "<td width=\"{$colWidth}\" style=\"padding: 10px; vertical-align: top;\">";
            $html[] = '<div style="border: 1px solid #eee; border-radius: 8px; overflow: hidden; background: #ffffff;">';

            if ($blockData->showImage && !empty($page['image']['src'])) {
                $html[] = '<img src="' . Str::sanitize($page['image']['src']) . '" style="width: 100%; height: auto; display: block;">';
            }

            $html[] = '<div style="padding: 15px;">';
            $html[] = '<h3 style="font-size: 16px; margin: 0 0 10px 0;">' . Str::sanitize($page['title']) . '</h3>';
            if ($blockData->showExcerpt && !empty($page['excerpt'])) {
                $html[] = '<p style="font-size: 13px; color: #666; line-height: 1.4;">' . Str::sanitize($page['excerpt']) . '</p>';
            }
            $html[] = '</div></div></td>';
        }

        $html[] = '</tr></table>';
        return RenderedBlock::rendered(implode("\n", $html));
    }
}