<?php

namespace App\Parsers;

use App\Framework\Support\SiteContext;
use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\InRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\MinRule;
use App\Framework\Validation\Rules\RequiredRule;

class NewsFeedBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'news-feed';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [new MaxLengthRule(255)],
            'subtitle' => [new MaxLengthRule(500)],
            'layout' => [new InRule(['grid', 'list', 'cards', 'masonry'])],
            'columns' => [new InRule([2, 3, 4])],
            'showExcerpt' => [new BooleanRule()],
            'showDate' => [new BooleanRule()],
            'showAuthor' => [new BooleanRule()],
            'showCategory' => [new BooleanRule()],
            'showReadTime' => [new BooleanRule()],
            'items' => [new RequiredRule(), new ArrayRule()],
            'limit' => [new MinRule(1)]
        ];
    }

    public function parse(array $data): array
    {
        $items = $data['items'] ?? [];

        // Validate and format items
        $validatedItems = [];
        foreach ($items as $item) {
            if (empty($item['title'])) {
                continue;
            }

            $validatedItems[] = [
                'title' => trim($item['title']),
                'excerpt' => trim($item['excerpt'] ?? ''),
                'imageUrl' => $item['imageUrl'] ?? '',
                'imageId' => $item['imageId'] ?? null,
                'pageUrl' => $item['pageUrl'] ?? '',
                'pageId' => $item['pageId'] ?? null,
                'author' => trim($item['author'] ?? ''),
                'date' => $item['date'] ?? '',
                'category' => trim($item['category'] ?? ''),
                'readTime' => $item['readTime'] ?? '',
                'featured' => (bool)($item['featured'] ?? false)
            ];
        }

        return [
            'title' => trim($data['title'] ?? ''),
            'subtitle' => trim($data['subtitle'] ?? ''),
            'layout' => $data['layout'] ?? 'grid',
            'columns' => (int)($data['columns'] ?? 3),
            'showExcerpt' => (bool)($data['showExcerpt'] ?? true),
            'showDate' => (bool)($data['showDate'] ?? true),
            'showAuthor' => (bool)($data['showAuthor'] ?? true),
            'showCategory' => (bool)($data['showCategory'] ?? true),
            'showReadTime' => (bool)($data['showReadTime'] ?? true),
            'items' => $validatedItems,
            'limit' => (int)($data['limit'] ?? 6),
            'context' => $data['context'] ?? 'default',
            'total_items' => count($validatedItems)
        ];
    }

    public function generateHtml(array $parsedData): string
    {
        $context = $parsedData['context'] ?? 'default';

        if ($context === 'sidebar') {
            return $this->generateSidebarHtml($parsedData);
        }

        return $this->generateDefaultHtml($parsedData);
    }

    private function generateSidebarHtml(array $parsedData): string
    {
        $html = "<aside class=\"news-feed-sidebar\">";

        if (!empty($parsedData['title'])) {
            $html .= "<h3 class=\"news-feed-sidebar-title\">{$parsedData['title']}</h3>";
        }

        $html .= "<div class=\"news-feed-sidebar-list\">";

        $itemsToShow = array_slice($parsedData['items'], 0, min($parsedData['limit'], 5));

        foreach ($itemsToShow as $item) {
            $html .= $this->generateSidebarNewsItem($item, $parsedData);
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

    private function generateDefaultHtml(array $parsedData): string
    {
        $layout = $parsedData['layout'];
        $columns = $parsedData['columns'];

        $html = "<section class=\"news-feed-block news-feed-{$layout}\">";

        if (!empty($parsedData['title'])) {
            $html .= "<div class=\"news-feed-header\">";
            $html .= "<h2 class=\"news-feed-title\">{$parsedData['title']}</h2>";

            if (!empty($parsedData['subtitle'])) {
                $html .= "<p class=\"news-feed-subtitle\">{$parsedData['subtitle']}</p>";
            }

            $html .= "</div>";
        }

        $html .= "<div class=\"news-feed-grid\" style=\"--columns: {$columns};\">";

        $itemsToShow = array_slice($parsedData['items'], 0, $parsedData['limit']);

        foreach ($itemsToShow as $item) {
            $html .= $this->generateNewsItem($item, $parsedData);
        }

        $html .= "</div>";
        $html .= "</section>";

        return $html;
    }

    private function generateNewsItem(array $item, array $settings): string
    {
        $html = "<article class=\"news-item\">";

        $pageUrl = SiteContext::slug() . $item['pageUrl'];

        if (!empty($item['imageUrl'])) {
            $html .= "<a href=\"{$pageUrl}\" class=\"news-item-image-link\">";
            $html .= "<img src=\"{$item['imageUrl']}\" alt=\"{$item['title']}\" class=\"news-item-image\">";
            $html .= "</a>";
        }

        $html .= "<div class=\"news-item-content\">";

        if ($settings['showCategory'] && !empty($item['category'])) {
            $html .= "<span class=\"news-item-category\">{$item['category']}</span>";
        }

        $html .= "<h3 class=\"news-item-title\">";
        $html .= "<a href=\"{$pageUrl}\">{$item['title']}</a>";
        $html .= "</h3>";

        if ($settings['showExcerpt'] && !empty($item['excerpt'])) {
            $html .= "<p class=\"news-item-excerpt\">{$item['excerpt']}</p>";
        }

        $html .= "<div class=\"news-item-meta\">";

        if ($settings['showAuthor'] && !empty($item['author'])) {
            $html .= "<span class=\"news-item-author\">By {$item['author']}</span>";
        }

        if ($settings['showDate'] && !empty($item['date'])) {
            $html .= "<span class=\"news-item-date\">{$item['date']}</span>";
        }

        if ($settings['showReadTime'] && !empty($item['readTime'])) {
            $html .= "<span class=\"news-item-read-time\">{$item['readTime']}</span>";
        }

        $html .= "</div>";
        $html .= "</div>";
        $html .= "</article>";

        return $html;
    }
}