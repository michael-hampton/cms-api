<?php

namespace App\Services\Newsletter;

use App\Models\NewsletterBrandingConfiguration;
use App\Services\Newsletter\Branding\CssSanitizer;

/**
 * Responsible for injecting branding (logo, header, footer, CSS)
 * into a rendered newsletter HTML string.
 *
 * Does NOT touch layout structure — only branding layer.
 */
class BrandingRendererService
{
    public function __construct(
        private readonly CssSanitizer $cssSanitizer,
    )
    {
    }

    /**
     * Apply branding configuration to base newsletter HTML.
     * Returns HTML with branding injected via wrapper ID for CSS scoping.
     */
    public function applyBranding(
        string                           $baseHtml,
        int                              $newsletterId,
        ?NewsletterBrandingConfiguration $branding
    ): string
    {
        if ($branding === null) {
            return $baseHtml;
        }

        $html = $baseHtml;
        $html = $this->wrapWithScopeId($html, $newsletterId);
        $html = $this->injectCustomCss($html, $branding->custom_css, $newsletterId);
        $html = $this->injectHeaderOverride($html, $branding);
        $html = $this->injectFooterOverride($html, $branding);

        return $html;
    }

    /**
     * Apply branding from a raw snapshot array (used when re-rendering historical snapshots).
     */
    public function applyBrandingFromSnapshot(
        string $baseHtml,
        int    $newsletterId,
        ?array $brandingSnapshot
    ): string
    {
        if ($brandingSnapshot === null) {
            return $baseHtml;
        }

        $branding = new NewsletterBrandingConfiguration();
        $branding->logo_url = $brandingSnapshot['logo_url'] ?? null;
        $branding->header_text = $brandingSnapshot['header_text'] ?? null;
        $branding->footer_text = $brandingSnapshot['footer_text'] ?? null;
        $branding->theme_json = $brandingSnapshot['theme_json'] ?? null;
        $branding->custom_css = $brandingSnapshot['custom_css'] ?? null;

        return $this->applyBranding($baseHtml, $newsletterId, $branding);
    }

    /**
     * Render the logo HTML for use in templates.
     */
    public function renderLogoHtml(
        ?NewsletterBrandingConfiguration $branding,
        string                           $fallbackSiteName,
        ?string                          $fallbackLogoUrl
    ): string
    {
        $logoUrl = $branding?->logo_url ?? $fallbackLogoUrl;

        if ($logoUrl) {
            return sprintf(
                '<img src="%s" alt="%s" style="max-width: 200px; height: auto;">',
                htmlspecialchars($logoUrl),
                htmlspecialchars($fallbackSiteName)
            );
        }

        // Text fallback
        return sprintf(
            '<div style="font-size: 24px; font-weight: bold; color: #1a202c;">%s</div>',
            htmlspecialchars($fallbackSiteName)
        );
    }

    private function wrapWithScopeId(string $html, int $newsletterId): string
    {
        // Wrap the body content in the scoping div if not already wrapped
        $scopeId = "newsletter-{$newsletterId}";

        if (strpos($html, "id=\"{$scopeId}\"") !== false) {
            return $html;
        }

        return str_replace(
            '<body',
            "<body><div id=\"{$scopeId}\"",
            str_replace('</body>', '</div></body>', $html)
        );
    }

    private function injectCustomCss(string $html, ?string $customCss, int $newsletterId): string
    {
        if (empty($customCss)) {
            return $html;
        }

        $sanitized = $this->cssSanitizer->sanitizeAndScope($customCss, $newsletterId);

        if (empty(trim($sanitized))) {
            return $html;
        }

        $styleTag = "<style>\n{$sanitized}\n</style>";

        return str_replace('</head>', "{$styleTag}\n</head>", $html);
    }

    private function injectHeaderOverride(string $html, NewsletterBrandingConfiguration $branding): string
    {
        if (empty($branding->header_text)) {
            return $html;
        }

        $headerHtml = sprintf(
            '<div style="text-align: center; padding: 10px; font-size: 14px; color: #555;">%s</div>',
            htmlspecialchars($branding->header_text)
        );

        // Insert after opening body tag
        return preg_replace(
            '/(<body[^>]*>)/i',
            '$1' . $headerHtml,
            $html,
            1
        );
    }

    private function injectFooterOverride(string $html, NewsletterBrandingConfiguration $branding): string
    {
        if (!$branding->footer_text) {
            return $html;
        }

        $footerHtml = sprintf(
            '<div style="text-align: center; padding: 20px; font-size: 12px; color: #999; border-top: 1px solid #eee; margin-top: 20px;">%s</div>',
            nl2br(htmlspecialchars($branding->footer_text))
        );

        return str_replace('</body>', "{$footerHtml}\n</body>", $html);
    }
}