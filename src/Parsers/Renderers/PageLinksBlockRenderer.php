<?php

namespace App\Parsers\Renderers;


use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\PageLinksBlockDto;

class PageLinksBlockRenderer extends BaseBlockRenderer
{
    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof PageLinksBlockDto) {
            throw new \InvalidArgumentException('Expected PageLinksBlockDto');
        }

        if ($dto->context === 'sidebar') {
            return $this->renderSidebar($dto);
        }

        return $this->renderDefault($dto);
    }

    private function renderSidebar(PageLinksBlockDto $dto): string
    {
        $html = "<aside class=\"page-links-sidebar\">";

        if (!empty($dto->title)) {
            $html .= "<h3 class=\"page-links-sidebar-title\">" . $this->escape($dto->title) . "</h3>";
        }

        $html .= "<ul class=\"page-links-sidebar-list\">";

        foreach ($dto->links as $link) {
            $html .= "<li class=\"page-link-sidebar-item\">";
            $html .= "<a href=\"{$link['pageUrl']}\">";

            if (!empty($link['icon'])) {
                $html .= "<span class=\"page-link-sidebar-icon\">{$link['icon']}</span>";
            }

            $html .= "<span class=\"page-link-sidebar-title\">" . $this->escape($link['title']) . "</span>";
            $html .= "</a>";
            $html .= "</li>";
        }

        $html .= "</ul>";
        $html .= "</aside>";

        return $html;
    }

    private function renderDefault(PageLinksBlockDto $dto): string
    {
        $html = "<section class=\"page-links-block page-links-{$dto->layout}\">";

        if (!empty($dto->title)) {
            $html .= "<h2 class=\"page-links-title\">" . $this->escape($dto->title) . "</h2>";
        }

        $html .= "<div class=\"page-links-grid\" style=\"--columns: {$dto->columns};\">";

        foreach ($dto->links as $link) {
            $html .= "<a href=\"{$link['pageUrl']}\" class=\"page-link-item\">";

            if ($dto->showImages) {
                if (!empty($link['imageUrl'])) {
                    $html .= "<div class=\"page-link-image\">";
                    $html .= "<img src=\"{$link['imageUrl']}\" alt=\"" . $this->escape($link['title']) . "\">";
                    $html .= "</div>";
                } elseif (!empty($link['icon'])) {
                    $html .= "<div class=\"page-link-icon\">{$link['icon']}</div>";
                }
            }

            $html .= "<div class=\"page-link-content\">";
            $html .= "<h3 class=\"page-link-title\">" . $this->escape($link['title']) . "</h3>";

            if ($dto->showDescriptions && !empty($link['description'])) {
                $html .= "<p class=\"page-link-description\">" . $this->escape($link['description']) . "</p>";
            }

            $html .= "</div>";
            $html .= "</a>";
        }

        $html .= "</div>";
        $html .= "</section>";

        return $html;
    }

    protected function getSupportedType(): string
    {
        return 'page-links';
    }
}