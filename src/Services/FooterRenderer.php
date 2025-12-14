<?php

namespace App\Services;

use App\Framework\Support\Collection;
use App\Models\Menu;

class FooterRenderer
{
    public function renderFooter(Menu $menu, array $options = []): string
    {
        $cssClasses = $options['css_classes'] ?? '';

        $columns = $this->groupItemsByColumn($menu->activeItems);

        $footerStyle = $menu->layout_config['footer_style'] ?? 'default';

        return $this->buildDynamicFooter($menu, $columns, $footerStyle, $cssClasses);
    }

    private function buildDynamicFooter(Menu $menu, array $columns, string $style, string $cssClasses): string
    {
        $config = $menu->getAttribute('layout_config') ?? [];

        $html = "<footer class='site-footer footer-{$style} {$cssClasses}'>";

        // Brand/About Section (if configured)
        if (!empty($config['show_brand_section'])) {
            $html .= $this->renderBrandSection($config);
        }

        // Main footer content
        $html .= "<div class='footer-main'>";
        $html .= "<div class='footer-container'>";

        // REMOVED the inline style that was limiting width
        $html .= "<div class='footer-grid'>";

        // Dynamic columns from menu items
        foreach ($columns as $columnGroup => $columnItems) {
            $html .= $this->renderFooterColumn($columnItems, $config);
        }

        // Newsletter section (if configured)
        if (!empty($config['show_newsletter'])) {
            $html .= $this->renderNewsletterColumn($config);
        }

        $html .= "</div></div></div>";

        // Footer bottom
        $html .= $this->renderFooterBottom($config);

        $html .= "</footer>";

        return $html;
    }

    private function renderBrandSection(array $config): string
    {
        $html = "<div class='footer-brand-section'>";
        $html .= "<div class='footer-container'>";

        // Logo
        if (!empty($config['logo_type'])) {
            $html .= "<div class='footer-logo'>";

            if ($config['logo_type'] === 'text') {
                $html .= "<div class='logo-text-wrapper'>";
                if (!empty($config['logo_main'])) {
                    $html .= "<span class='logo-main'>" . htmlspecialchars($config['logo_main']) . "</span>";
                }
                if (!empty($config['logo_sub'])) {
                    $html .= "<span class='logo-sub'>" . htmlspecialchars($config['logo_sub']) . "</span>";
                }
                $html .= "</div>";
            } elseif ($config['logo_type'] === 'image' && !empty($config['logo_image'])) {
                $html .= "<img src='" . htmlspecialchars($config['logo_image']) . "' alt='Logo'>";
            } elseif ($config['logo_type'] === 'icon') {
                $html .= "<div class='logo-icon'>" . htmlspecialchars($config['logo_icon'] ?? '🏠') . "</div>";
                $html .= "<div class='logo-brand-name'>" . htmlspecialchars($config['brand_name'] ?? '') . "</div>";
            }

            $html .= "</div>";
        }

        // Tagline/Description
        if (!empty($config['footer_description'])) {
            $html .= "<p class='footer-description'>" . htmlspecialchars($config['footer_description']) . "</p>";
        }

        // Social links
        if (!empty($config['social_links'])) {
            $html .= $this->renderSocialLinks($config['social_links'], $config['social_style'] ?? 'simple');
        }

        $html .= "</div></div>";

        return $html;
    }

    private function renderFooterColumn(array $items, array $config): string
    {
        if (empty($items)) {
            return '';
        }

        $html = "<div class='footer-column'>";

        // First item is the column header
        $header = array_shift($items);
        $html .= "<h4 class='footer-column-title'>" . htmlspecialchars($header->label) . "</h4>";

        // Remaining items are links
        if (!empty($items)) {
            $html .= "<ul class='footer-links'>";
            foreach ($items as $item) {
                $target = $item->open_in_new_tab ? ' target="_blank" rel="noopener"' : '';
                $icon = $item->icon ? "<i class='icon-{$item->icon}'></i> " : '';
                $html .= "<li><a href='{$item->url}'{$target}>{$icon}" . htmlspecialchars($item->label) . "</a></li>";
            }
            $html .= "</ul>";
        }

        $html .= "</div>";

        return $html;
    }

    private function renderNewsletterColumn(array $config): string
    {
        $html = "<div class='footer-column footer-newsletter'>";
        $html .= "<h4 class='footer-column-title'>" . htmlspecialchars($config['newsletter_title'] ?? 'Newsletter') . "</h4>";

        if (!empty($config['newsletter_description'])) {
            $html .= "<p class='newsletter-description'>" . htmlspecialchars($config['newsletter_description']) . "</p>";
        }

        $html .= "<form class='newsletter-form' id='footer-newsletter-form'>";
        $html .= "<div class='newsletter-input-group'>";
        $html .= "<input type='email' name='email' class='newsletter-input' id='footer-newsletter-email' placeholder='" .
            htmlspecialchars($config['newsletter_placeholder'] ?? 'Your email address') . "' required>";
        $html .= "<button type='submit' class='newsletter-button' id='footer-newsletter-submit'>" .
            htmlspecialchars($config['newsletter_button_text'] ?? 'Subscribe') . "</button>";
        $html .= "</div>";

        // Account creation option
        $html .= "<div class='newsletter-account-option'>";
        $html .= "<button type='button' class='newsletter-create-account-link' id='footer-create-account-btn'>";
        $html .= "✨ Create account to unlock exclusive features";
        $html .= "</button>";
        $html .= "</div>";

        $html .= "<div class='newsletter-message' id='footer-newsletter-message'></div>";
        $html .= "</form>";

        $html .= "</div>";

        return $html;
    }

    private function renderSocialLinks(array $links, string $style = 'simple'): string
    {
        if (empty($links)) {
            return '';
        }

        $html = "<div class='footer-social social-style-{$style}'>";

        foreach ($links as $platform => $url) {
            if (empty($url)) continue;

            if ($style === 'icons-svg') {
                $icon = $this->getSocialIconSVG($platform);
            } else {
                $icon = $this->getSocialIconEmoji($platform);
            }

            $html .= "<a href='" . htmlspecialchars($url) . "' class='social-link social-{$platform}' aria-label='{$platform}' target='_blank' rel='noopener'>{$icon}</a>";
        }

        $html .= "</div>";

        return $html;
    }

    private function renderFooterBottom(array $config): string
    {
        $year = date('Y');
        $brandName = htmlspecialchars($config['brand_name'] ?? 'Company');

        $html = "<div class='footer-bottom'>";
        $html .= "<div class='footer-container'>";
        $html .= "<div class='footer-bottom-content'>";

        // Copyright
        $copyrightText = $config['copyright_text'] ?? "&copy; {$year} {$brandName}. All rights reserved.";
        $copyrightText = str_replace('{year}', $year, $copyrightText);
        $copyrightText = str_replace('{brand}', $brandName, $copyrightText);
        $html .= "<p class='footer-copyright'>{$copyrightText}</p>";

        // Legal links
        if (!empty($config['legal_links'])) {
            $html .= "<ul class='footer-legal-links'>";
            foreach ($config['legal_links'] as $link) {
                $html .= "<li><a href='" . htmlspecialchars($link['url']) . "'>" .
                    htmlspecialchars($link['label']) . "</a></li>";
            }
            $html .= "</ul>";
        }

        // Custom bottom text (e.g., age warnings)
        if (!empty($config['footer_bottom_text'])) {
            $html .= "<p class='footer-bottom-text'>" . htmlspecialchars($config['footer_bottom_text']) . "</p>";
        }

        $html .= "</div></div></div>";

        return $html;
    }

    private function getColumnCount(array $columns, array $config): int
    {
        $columnCount = count($columns);

        if (!empty($config['show_newsletter'])) {
            $columnCount++;
        }

        if (!empty($config['show_brand_section']) && $config['brand_in_grid'] ?? false) {
            $columnCount++;
        }

        return max(1, min($columnCount, $config['max_columns'] ?? 5));
    }

    private function getSocialIconEmoji(string $platform): string
    {
        $icons = [
            'facebook' => '📘',
            'twitter' => '🐦',
            'instagram' => '📷',
            'pinterest' => '📌',
            'youtube' => '▶️',
            'linkedin' => '💼',
            'github' => '🐙',
            'tiktok' => '🎵',
            'snapchat' => '👻'
        ];

        return $icons[$platform] ?? '🔗';
    }

    private function getSocialIconSVG(string $platform): string
    {
        // Return SVG icons for common platforms
        $svgs = [
            'twitter' => '<svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z"></path></svg>',
            'github' => '<svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>',
            'linkedin' => '<svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>',
            'facebook' => '<svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>'
        ];

        return $svgs[$platform] ?? '';
    }

    private function groupItemsByColumn(Collection $items): array
    {
        $grouped = [];
        foreach ($items as $item) {
            $column = $item->column_group ?? 0;
            if (!isset($grouped[$column])) {
                $grouped[$column] = [];
            }
            $grouped[$column][] = $item;
        }
        return $grouped;
    }
}