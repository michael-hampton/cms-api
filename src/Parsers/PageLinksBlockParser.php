<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\InRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;

class PageLinksBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'page-links';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [new MaxLengthRule(255)],
            'layout' => [new InRule(['grid', 'list', 'compact'])],
            'columns' => [new InRule([2, 3, 4, 5])],
            'showImages' => [new BooleanRule()],
            'showDescriptions' => [new BooleanRule()],
            'links' => [new RequiredRule(), new ArrayRule()]
        ];
    }

    public function parse(array $data): array
    {
        $links = $data['links'] ?? [];

        // Validate and format links
        $validatedLinks = [];
        foreach ($links as $link) {
            if (empty($link['title'])) {
                continue;
            }

            $validatedLinks[] = [
                'title' => trim($link['title']),
                'description' => trim($link['description'] ?? ''),
                'imageUrl' => $link['imageUrl'] ?? '',
                'imageId' => $link['imageId'] ?? null,
                'pageUrl' => $link['pageUrl'] ?? '',
                'pageId' => $link['pageId'] ?? null,
                'icon' => $link['icon'] ?? ''
            ];
        }

        return [
            'title' => trim($data['title'] ?? ''),
            'layout' => $data['layout'] ?? 'grid',
            'columns' => (int)($data['columns'] ?? 3),
            'showImages' => (bool)($data['showImages'] ?? true),
            'showDescriptions' => (bool)($data['showDescriptions'] ?? true),
            'links' => $validatedLinks,
            'context' => $data['context'] ?? 'default',
            'total_links' => count($validatedLinks)
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
        $html = "<aside class=\"page-links-sidebar\">";

        if (!empty($parsedData['title'])) {
            $html .= "<h3 class=\"page-links-sidebar-title\">{$parsedData['title']}</h3>";
        }

        $html .= "<ul class=\"page-links-sidebar-list\">";

        foreach ($parsedData['links'] as $link) {
            $html .= $this->generateSidebarPageLink($link);
        }

        $html .= "</ul>";
        $html .= "</aside>";

        return $html;
    }

    private function generateSidebarPageLink(array $link): string
    {
        $html = "<li class=\"page-link-sidebar-item\">";
        $html .= "<a href=\"{$link['pageUrl']}\">";

        if (!empty($link['icon'])) {
            $html .= "<span class=\"page-link-sidebar-icon\">{$link['icon']}</span>";
        }

        $html .= "<span class=\"page-link-sidebar-title\">{$link['title']}</span>";
        $html .= "</a>";
        $html .= "</li>";

        return $html;
    }

    private function generateDefaultHtml(array $parsedData): string
    {
        $layout = $parsedData['layout'];
        $columns = $parsedData['columns'];

        $html = "<section class=\"page-links-block page-links-{$layout}\">";

        if (!empty($parsedData['title'])) {
            $html .= "<h2 class=\"page-links-title\">{$parsedData['title']}</h2>";
        }

        $html .= "<div class=\"page-links-grid\" style=\"--columns: {$columns};\">";

        foreach ($parsedData['links'] as $link) {
            $html .= $this->generatePageLink($link, $parsedData);
        }

        $html .= "</div>";
        $html .= "</section>";

        return $html;
    }

    private function generatePageLink(array $link, array $settings): string
    {
        $html = "<a href=\"{$link['pageUrl']}\" class=\"page-link-item\">";

        if ($settings['showImages']) {
            if (!empty($link['imageUrl'])) {
                $html .= "<div class=\"page-link-image\">";
                $html .= "<img src=\"{$link['imageUrl']}\" alt=\"{$link['title']}\">";
                $html .= "</div>";
            } elseif (!empty($link['icon'])) {
                $html .= "<div class=\"page-link-icon\">{$link['icon']}</div>";
            }
        }

        $html .= "<div class=\"page-link-content\">";
        $html .= "<h3 class=\"page-link-title\">{$link['title']}</h3>";

        if ($settings['showDescriptions'] && !empty($link['description'])) {
            $html .= "<p class=\"page-link-description\">{$link['description']}</p>";
        }

        $html .= "</div>";
        $html .= "</a>";

        return $html;
    }
}