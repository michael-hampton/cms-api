<?php

namespace App\Parsers\Renderers;

use App\Framework\Support\SiteContext;
use App\Parsers\Dtos\BlockDtoInterface;

class NewsFeedBlockRenderer extends BaseBlockRenderer
{
    public function render(BlockDtoInterface $dto): string
    {
        $context = $dto->context ?? 'default';

        if ($context === 'sidebar') {
            return $this->generateSidebarHtml($dto);
        }

        return $this->generateDefaultHtml($dto);
    }

    private function generateSidebarHtml(BlockDtoInterface $dto): string
    {
        $html = "<aside class=\"news-feed-sidebar\">";

        if (!empty($dto->title)) {
            $html .= "<h3 class=\"news-feed-sidebar-title\">{$dto->title}</h3>";
        }

        $html .= "<div class=\"news-feed-sidebar-list\">";

        $itemsToShow = array_slice($dto->items, 0, min($dto['limit'], 5));

        foreach ($itemsToShow as $item) {
            $html .= $this->generateSidebarNewsItem($item, $dto);
        }

        $html .= "</div>";
        $html .= "</aside>";

        return $html;
    }

    private function generateSidebarNewsItem(array $item, array $settings): string
    {
        $html = "<article class=\"news-sidebar-item\">";

        if (!empty($item['imageUrl'])) {
            $html .= "<a href=\"{$item['pageUrl']}\" class=\"news-sidebar-image-link\">";
            $html .= "<img src=\"{$item['imageUrl']}\" alt=\"{$item['title']}\" class=\"news-sidebar-image\">";
            $html .= "</a>";
        }

        $html .= "<div class=\"news-sidebar-content\">";

        $html .= "<h4 class=\"news-sidebar-title\">";
        $html .= "<a href=\"{$item['pageUrl']}\">{$item['title']}</a>";
        $html .= "</h4>";

        if ($settings['showDate'] && !empty($item['date'])) {
            $html .= "<span class=\"news-sidebar-date\">{$item['date']}</span>";
        }

        $html .= "</div>";
        $html .= "</article>";

        return $html;
    }

    private function generateDefaultHtml(BlockDtoInterface $dto): string
    {
        $layout = $dto->layout;
        $columns = $dto->columns;

        $html = "<section class=\"news-feed-block news-feed-{$layout}\">";

        if (!empty($dto->title)) {
            $html .= "<div class=\"news-feed-header\">";
            $html .= "<h2 class=\"news-feed-title\">{$dto->title}</h2>";

            if (!empty($dto->subtitle)) {
                $html .= "<p class=\"news-feed-subtitle\">{$dto->subtitle}</p>";
            }

            $html .= "</div>";
        }

        $html .= "<div class=\"news-feed-grid\" style=\"--columns: {$columns};\">";

        $itemsToShow = array_slice($dto->items, 0, $dto->limit);

        foreach ($itemsToShow as $item) {
            $html .= $this->generateNewsItem($item, $dto);
        }

        $html .= "</div>";
        $html .= "</section>";

        return $html;
    }

    private function generateNewsItem(array $item, BlockDtoInterface $dto): string
    {
        $html = "<article class=\"news-item\">";

        $pageUrl = SiteContext::slug() . $item['pageUrl'];

        if (!empty($item['image'])) {
            $html .= "<a href=\"{$pageUrl}\" class=\"news-item-image-link\">";
            $html .= "<img src=\"{$item['image']['src']}\" alt=\"{$item['image']['alt']}\" class=\"news-item-image\">";
            $html .= "</a>";
        }

        $html .= "<div class=\"news-item-content\">";

        if ($dto->showCategory && !empty($item['category'])) {
            $html .= "<span class=\"news-item-category\">{$item['category']}</span>";
        }

        $html .= "<h3 class=\"news-item-title\">";
        $html .= "<a href=\"{$pageUrl}\">{$item['title']}</a>";
        $html .= "</h3>";

        if ($dto->showExcerpts && !empty($item['excerpt'])) {
            $html .= "<p class=\"news-item-excerpt\">{$item['excerpt']}</p>";
        }

        $html .= "<div class=\"news-item-meta\">";

        if ($dto->showAuthor && !empty($item['author'])) {
            $html .= "<span class=\"news-item-author\">By {$item['author']}</span>";
        }

        if ($dto->showDates && !empty($item['date'])) {
            $html .= "<span class=\"news-item-date\">{$item['date']}</span>";
        }

        if ($dto->showReadTime && !empty($item['readTime'])) {
            $html .= "<span class=\"news-item-read-time\">{$item['readTime']}</span>";
        }

        $html .= "</div>";
        $html .= "</div>";
        $html .= "</article>";

        return $html;
    }

    protected function getSupportedType(): string
    {
        return 'news-feed';
    }
}