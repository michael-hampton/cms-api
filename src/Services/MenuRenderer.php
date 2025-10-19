<?php

namespace App\Services;

use App\Framework\Support\Collection;
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
            default:
                return $this->renderHorizontal($menu->activeItems, $cssClasses, $showIcons);
        }
    }

    private function renderHorizontal(Collection $items, string $cssClasses, bool $showIcons): string
    {
        if ($items->isEmpty()) {
            die('a');
            return '';
        }

        $html = "<nav class='menu-horizontal {$cssClasses}'><ul>";

        foreach ($items as $item) {
            $html .= $this->renderMenuItem($item, $showIcons);
        }

        $html .= "</ul></nav>";
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