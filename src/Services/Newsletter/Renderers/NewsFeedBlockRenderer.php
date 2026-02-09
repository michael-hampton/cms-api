<?php

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\NewsFeedBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class NewsFeedBlockRenderer implements EmailBlockRenderer
{
    public $type = 'news-feed';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof NewsFeedBlockData) {
            return RenderedBlock::skipped();
        }

        $html = [];
        $html[] = '<div style="margin: 30px 0;">';

        if ($blockData->title) {
            $html[] = '<h3 style="color: #333; margin: 0 0 10px 0; font-size: 24px;">' . Str::sanitize($blockData->title) . '</h3>';
        }

        if ($blockData->subtitle) {
            $html[] = '<p style="color: #666; margin: 0 0 20px 0; font-size: 16px;">' . Str::sanitize($blockData->subtitle) . '</p>';
        }

        $items = array_slice($blockData->items, 0, $blockData->limit);

        foreach ($items as $item) {
            $html[] = '<div style="border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; margin-bottom: 20px; display: table; width: 100%;">';

            if (!empty($item['imageUrl'])) {
                $html[] = '<div style="display: table-cell; width: 150px; vertical-align: top; padding-right: 20px;">';
                $html[] = '<a href="' . Str::sanitize($item['pageUrl']) . '">';
                $html[] = '<img src="' . Str::sanitize($item['imageUrl']) . '" alt="' . Str::sanitize($item['title']) . '" style="width: 150px; height: 100px; object-fit: cover; border-radius: 4px;">';
                $html[] = '</a>';
                $html[] = '</div>';
            }

            $html[] = '<div style="display: table-cell; vertical-align: top;">';

            if ($blockData->showCategory && !empty($item['category'])) {
                $html[] = '<div style="color: #007bff; font-size: 12px; text-transform: uppercase; margin-bottom: 5px;">' . Str::sanitize($item['category']) . '</div>';
            }

            $html[] = '<h4 style="margin: 0 0 8px 0; font-size: 18px;">';
            $html[] = '<a href="' . Str::sanitize($item['pageUrl']) . '" style="color: #333; text-decoration: none;">' . Str::sanitize($item['title']) . '</a>';
            $html[] = '</h4>';

            if ($blockData->showExcerpt && !empty($item['excerpt'])) {
                $html[] = '<p style="color: #666; font-size: 14px; margin: 0 0 10px 0; line-height: 1.5;">' . Str::sanitize($item['excerpt']) . '</p>';
            }

            $meta = [];
            if ($blockData->showAuthor && !empty($item['author'])) {
                $meta[] = 'By ' . Str::sanitize($item['author']);
            }
            if ($blockData->showDate && !empty($item['date'])) {
                $meta[] = Str::sanitize($item['date']);
            }
            if ($blockData->showReadTime && !empty($item['readTime'])) {
                $meta[] = Str::sanitize($item['readTime']);
            }

            if (!empty($meta)) {
                $html[] = '<div style="color: #999; font-size: 12px;">' . implode(' • ', $meta) . '</div>';
            }

            $html[] = '</div>';
            $html[] = '</div>';
        }

        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}