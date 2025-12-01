<?php

namespace App\Services;

use App\Framework\Support\Collection;
use App\Framework\Support\SiteContext;
use App\Models\Menu;
use App\Models\MenuItem;

class MenuRenderer
{
    public function render(Menu $menu, array $options = []): string
    {
        $layoutType = $options['layout'] ?? $menu->getAttribute('layout_config')['type'] ?? 'horizontal';
        $cssClasses = $options['css_classes'] ?? '';
        $showIcons = $options['show_icons'] ?? true;

        switch ($layoutType) {
            case 'vertical':
                return $this->renderVertical($menu->activeItems, $cssClasses, $showIcons, $options);
            case 'tiles':
                return $this->renderTiles($menu->activeItems, $options);
            case 'mega':
                return $this->renderMegaMenu($menu->activeItems, $cssClasses, $options);
            default:
                return $this->renderHorizontal($menu->activeItems, $cssClasses, $showIcons);
        }
    }

    private function renderHorizontal(Collection $items, string $cssClasses, bool $showIcons): string
    {
        if ($items->isEmpty()) {
            return '';
        }

        $html = "<nav class='menu-horizontal {$cssClasses}'><ul class='main-menu'>";

        foreach ($items as $item) {
            $html .= $this->renderMenuItem($item, $showIcons);
        }

        $html .= "</ul></nav>";
        return $html;
    }

    private function renderMegaMenu(Collection $items, string $cssClasses, array $options): string
    {
        if ($items->isEmpty()) {
            return '';
        }

        $html = "<nav class='mega-menu-nav {$cssClasses}'>";

        if (!empty($options['logo'])) {
            $html .= '<a href="/" class="logo">' . $options['title'] . '</a>';
        }

        $html .= "<ul class='mega-menu'>";

        foreach ($items as $item) {
            $hasChildren = $item->activeChildren->isNotEmpty();
            $icon = !empty($item->icon) ? "<i class='{$item->icon}'></i> " : '';
            $target = $item->open_in_new_tab ? ' target="_blank" rel="noopener"' : '';
            $cssClass = $item->css_class ? " {$item->css_class}" : '';

            // Check if this item has children grouped by column
            $hasColumnGroups = $hasChildren && $item->activeChildren->where('column_group', '>', 0)->isNotEmpty();
            $url = '/' . trim(SiteContext::slug(), '/') . '/' . ltrim($item->url, '/');

            $html .= "<li class='mega-menu-item" . ($hasChildren ? ' has-dropdown' : '') . "{$cssClass}'>";
            $html .= "<a href='{$url}'{$target} class='mega-menu-link'>{$icon}{$item->label}";

            if ($hasChildren) {
                $html .= '<svg class="dropdown-icon" width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2.5 4.5L6 8L9.5 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            }

            $html .= "</a>";

            if ($hasChildren) {
                if ($hasColumnGroups) {
                    // Render mega dropdown with columns
                    $html .= $this->renderMegaDropdown($item->activeChildren);
                } else {
                    // Render standard dropdown
                    $html .= $this->renderStandardDropdown($item->activeChildren);
                }
            }

            $html .= "</li>";
        }

        $html .= "</ul></nav>";
        return $html;
    }

    private function renderStandardDropdown(Collection $children): string
    {
        $html = "<div class='dropdown-menu'><ul class='dropdown-list'>";

        foreach ($children as $child) {
            $target = $child->open_in_new_tab ? ' target="_blank" rel="noopener"' : '';
            $icon = !empty($child->icon) ? "<i class='{$child->icon}'></i> " : '';
            $url = '/' . trim(SiteContext::slug(), '/') . '/' . ltrim($child->url, '/');

            $html .= "<li class='dropdown-item'>";
            $html .= "<a href='{$url}'{$target} class='dropdown-link'>{$icon}{$child->label}</a>";
            $html .= "</li>";
        }

        $html .= "</ul></div>";
        return $html;
    }

    private function renderMegaDropdown(Collection $children): string
    {
        // Group children by column_group
        $grouped = $children->groupBy('column_group');

        $html = "<div class='mega-dropdown'><div class='mega-dropdown-inner'>";

        foreach ($grouped as $columnGroup => $items) {
            $html .= "<div class='mega-column'>";

            die('here');

            // First item in column is the header
            $header = $items->first();
            $html .= "<h3 class='mega-column-title'>{$header->label}</h3>";
            $html .= "<ul class='mega-column-list'>";

            // Skip first item (already used as header), render rest
            foreach ($items->slice(1) as $item) {
                $target = $item->open_in_new_tab ? ' target="_blank" rel="noopener"' : '';
                $icon = !empty($item->icon) ? "<i class='{$item->icon}'></i> " : '';
                $url = '/' . trim(SiteContext::slug(), '/') . '/' . ltrim($item->url, '/');

                $html .= "<li class='mega-column-item'>";
                $html .= "<a href='{$url}'{$target} class='mega-column-link'>{$icon}{$item->label}</a>";
                $html .= "</li>";
            }

            $html .= "</ul></div>";
        }

        $html .= "</div></div>";
        return $html;
    }

    private function renderVertical(Collection $items, string $cssClasses, bool $showIcons, array $options): string
    {
        if ($items->isEmpty()) {
            return '';
        }

        $html = "<nav class='nav {$cssClasses}'>";

        if(!empty($options['logo'])) {
            $html .= '<a href="#home" class="logo">'.$options['title'].'</a>';
        }

        $html .= "<ul class='nav-menu'>";

        foreach ($items as $item) {
            $html .= $this->renderMenuItem($item, $showIcons);
        }

        $html .= "</ul></nav>";
        return $html;
    }

    private function renderTiles(Collection $items, array $options): string
    {
        if ($items->isEmpty()) {
            return '';
        }

        $cssClasses = $options['css_classes'] ?? '';
        $html = "<div class='menu-tiles {$cssClasses}'>";

        foreach ($items as $item) {
            $html .= $this->renderTileItem($item);
        }

        $html .= "</div>";
        return $html;
    }

    private function renderMenuItem(MenuItem $item, bool $showIcons, int $level = 0): string
    {
        $hasChildren = $item->activeChildren->isNotEmpty();
        $icon = $showIcons && $item->icon ? "<i class='{$item->icon}'></i> " : '';
        $target = $item->open_in_new_tab ? ' target="_blank" rel="noopener"' : '';
        $cssClass = $item->css_class ? " class='{$item->css_class}'" : '';

        $html = "<li{$cssClass}>";
        $html .= "<a href='{$item->url}'{$target}>{$icon}{$item->label}</a>";

        if ($hasChildren) {
            $html .= "<ul class='submenu'>";
            foreach ($item->activeChildren as $child) {
                $html .= $this->renderMenuItem($child, $showIcons, $level + 1);
            }
            $html .= "</ul>";
        }

        $html .= "</li>";
        return $html;
    }

    private function renderTileItem(MenuItem $item): string
    {
        $targetData = $item->target_data;
        $image = $targetData['image'] ?? '/images/default-tile.jpg';
        $title = $item->label;
        $description = $targetData['excerpt'] ?? $targetData['description'] ?? '';
        $target = $item->open_in_new_tab ? ' target="_blank" rel="noopener"' : '';

        return "
            <div class='menu-tile'>
                <a href='{$item->url}'{$target}>
                    <div class='tile-image'>
                        <img src='{$image}' alt='{$title}' loading='lazy'>
                    </div>
                    <div class='tile-content'>
                        <h3 class='tile-title'>{$title}</h3>
                        <p class='tile-description'>{$description}</p>
                    </div>
                </a>
            </div>
        ";
    }
}