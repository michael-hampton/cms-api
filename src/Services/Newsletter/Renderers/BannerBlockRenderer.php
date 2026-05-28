<?php

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BannerBlockData;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class BannerBlockRenderer implements EmailBlockRenderer
{
    public $type = 'banner';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof BannerBlockData) {
            return RenderedBlock::skipped();
        }

        return match ($blockData->bannerType) {
            'masthead' => $this->renderMasthead($blockData),
            'footer-legal' => $this->renderFooterLegal($blockData),
            'review-banner' => $this->renderReviewBanner($blockData),
            'providers-banner' => $this->renderProvidersBanner($blockData),
            default => $this->renderPromoBanner($blockData),
        };
    }

    // -------------------------------------------------------------------------
    // Structural renderers — driven entirely by block data, not template name
    // -------------------------------------------------------------------------

    /**
     * Renders a masthead / brand header bar.
     *
     * Reads from block data:
     *   title            → newsletter title displayed below logo area
     *   subtitle         → "View in browser" label (linked via VIEW_IN_BROWSER_URL placeholder)
     *   backgroundColor  → header background colour
     *   textColor        → header text / link colour
     *   image            → optional logo image (src)
     *   ctaText          → optional nav tagline rendered beneath the title
     */
    private function renderMasthead(BannerBlockData $blockData): RenderedBlock
    {
        $bg = htmlspecialchars($blockData->backgroundColor);
        $color = htmlspecialchars($blockData->textColor);

        $viewInBrowserHref = '{{VIEW_IN_BROWSER_URL}}';
        $viewInBrowserLabel = $blockData->subtitle
            ? htmlspecialchars($blockData->subtitle)
            : 'View in browser';

        $logoHtml = '';
        if (!empty($blockData->image['src'])) {
            $logoHtml = sprintf(
                '<img src="%s" alt="%s" style="max-width:200px;height:auto;display:block;margin:0 auto 8px;">',
                Str::sanitize($blockData->image['src']),
                Str::sanitize($blockData->title)
            );
        }

        $titleHtml = sprintf(
            '<div style="font-size:26px;font-weight:800;letter-spacing:2px;color:%s;text-transform:uppercase;">%s</div>',
            $color,
            Str::sanitize($blockData->title)
        );

        // Nav links bar — supports navLinks array or falls back to single ctaText
        $navHtml = '';
        if (!empty($blockData->navLinks) && is_array($blockData->navLinks)) {
            $navItems = '';
            foreach ($blockData->navLinks as $index => $link) {
                $linkText = Str::sanitize($link['text'] ?? '');
                $linkUrl = Str::sanitize($link['url'] ?? '#');
                $isFirst = $index === 0;

                // First item gets a white box highlight to match the Ceros design
                $linkStyle = $isFirst
                    ? "font-size:11px;font-weight:700;letter-spacing:1.5px;color:{$bg};background-color:{$color};padding:3px 8px;text-decoration:none;text-transform:uppercase;display:inline-block;"
                    : "font-size:11px;font-weight:700;letter-spacing:1.5px;color:{$color};text-decoration:none;text-transform:uppercase;display:inline-block;padding:3px 8px;";

                $navItems .= sprintf(
                    '<a href="%s" style="%s">%s</a>',
                    $linkUrl,
                    $linkStyle,
                    $linkText
                );
            }

            $navHtml = sprintf(
                '<div style="text-align:center;padding:10px 20px;background-color:%s;">%s</div>',
                htmlspecialchars($blockData->navBackgroundColor ?: $bg),
                $navItems
            );
        } elseif (!empty($blockData->ctaText)) {
            // Legacy fallback — plain tagline text
            $navHtml = sprintf(
                '<div style="font-size:11px;letter-spacing:1.5px;color:%s;text-transform:uppercase;margin-top:6px;text-align:center;">%s</div>',
                $color,
                Str::sanitize($blockData->ctaText)
            );
        }

        $html = <<<HTML
<table width="100%" cellpadding="0" cellspacing="0" border="0"
       style="background-color:{$bg};">
  <tr>
    <td style="padding:6px 20px;text-align:right;border-bottom:1px solid rgba(255,255,255,0.15);">
      <a href="{$viewInBrowserHref}"
         style="font-size:11px;color:{$color};text-decoration:underline;letter-spacing:0.5px;"
      >{$viewInBrowserLabel}</a>
    </td>
  </tr>
  <tr>
    <td style="padding:28px 20px 16px;text-align:center;">
      {$logoHtml}
      {$titleHtml}
    </td>
  </tr>
  <tr>
    <td style="border-top:1px solid rgba(255,255,255,0.15);">
      {$navHtml}
    </td>
  </tr>
</table>
HTML;

        return RenderedBlock::rendered($html);
    }

    /**
     * Renders a legal footer strip.
     *
     * Reads from block data:
     *   title            → site / brand name
     *   backgroundColor  → footer background colour
     *   textColor        → footer text colour
     *   providers        → array of {platform, url} social link objects
     *   ctaText          → optional additional legal line (e.g. address)
     *
     * Unsubscribe / manage / privacy links are expected to be injected by the
     * outer template (NewsletterPageBuilderService::buildTemplate) or by the
     * dispatcher, so this renderer outputs a placeholder row that callers can
     * replace if needed. The footer renders safely even without those tokens.
     */
    private function renderFooterLegal(BannerBlockData $blockData): RenderedBlock
    {
        $bg = htmlspecialchars($blockData->backgroundColor);
        $color = htmlspecialchars($blockData->textColor);
        $brand = Str::sanitize($blockData->title);

        // ── Social icons ──────────────────────────────────────────────────────
        $socialHtml = '';
        if (!empty($blockData->providers)) {
            $icons = '';
            foreach ($blockData->providers as $social) {
                $platform = strtolower($social['platform'] ?? '');
                $url = htmlspecialchars($social['url'] ?? '#');
                $label = ucfirst($platform);
                $glyph = match ($platform) {
                    'instagram' => '📷',
                    'pinterest' => '📌',
                    'facebook' => 'f',
                    'twitter', 'x' => '𝕏',
                    'tiktok' => '♪',
                    default => '🔗',
                };
                $icons .= sprintf(
                    '<a href="%s" title="%s"'
                    . ' style="display:inline-block;margin:0 8px;color:%s;text-decoration:none;font-size:18px;">%s</a>',
                    $url,
                    $label,
                    $color,
                    $glyph
                );
            }
            $socialHtml = '<p style="margin:0 0 12px 0;">' . $icons . '</p>';
        }

        // ── Legal links — standard placeholders injected by dispatcher ────────
        $legalHtml = sprintf(
            '<p style="margin:0 0 10px 0;font-size:11px;">'
            . '<a href="{{PRIVACY_URL}}" style="color:%1$s;text-decoration:none;">PRIVACY</a>'
            . ' &nbsp;|&nbsp; '
            . '<a href="{{MANAGE_URL}}" style="color:%1$s;text-decoration:none;">MANAGE YOUR SUBSCRIPTIONS</a>'
            . ' &nbsp;|&nbsp; '
            . '<a href="{{UNSUBSCRIBE_URL}}" style="color:%1$s;text-decoration:none;">UNSUBSCRIBE</a>'
            . ' &nbsp;|&nbsp; '
            . '<a href="{{TERMS_URL}}" style="color:%1$s;text-decoration:none;">TERMS</a>'
            . '</p>',
            $color
        );

        // ── Optional address / extra line from ctaText ────────────────────────
        $addressHtml = '';
        if (!empty($blockData->address) || !empty($blockData->ctaText)) {
            $addressHtml = sprintf(
                '<p style="margin:0 0 8px 0;font-size:11px;color:%s;">%s</p>',
                $color,
                Str::sanitize($blockData->address ?: $blockData->ctaText)
            );
        }

        $copyrightHtml = sprintf(
            '<p style="margin:0;font-size:11px;color:%s;">&copy; %s %s. All rights reserved.</p>',
            $color,
            date('Y'),
            $brand
        );

        $html = <<<HTML
<table width="100%" cellpadding="0" cellspacing="0" border="0"
       style="background-color:{$bg};">
  <tr>
    <td style="padding:28px 20px;text-align:center;color:{$color};">
      {$socialHtml}
      {$legalHtml}
      {$addressHtml}
      {$copyrightHtml}
    </td>
  </tr>
</table>
HTML;

        return RenderedBlock::rendered($html);
    }

    // -------------------------------------------------------------------------
    // Content renderers (unchanged)
    // -------------------------------------------------------------------------

    private function renderReviewBanner(BannerBlockData $blockData): RenderedBlock
    {
        $html = [];
        $html[] = "<div style=\"background-color: {$blockData->backgroundColor}; color: {$blockData->textColor}; padding: 25px; border-radius: 8px; margin: 20px 0; display: table; width: 100%;\">";

        if ($blockData->image && isset($blockData->image['src'])) {
            $html[] = '<div style="display: table-cell; width: 120px; vertical-align: middle; padding-right: 20px;">';
            $html[] = '<img src="' . Str::sanitize($blockData->image['src']) . '" alt="' . Str::sanitize($blockData->title) . '" style="width: 120px; height: auto; border-radius: 4px;">';
            $html[] = '</div>';
        }

        $html[] = '<div style="display: table-cell; vertical-align: middle;">';
        $html[] = "<h3 style=\"color: {$blockData->textColor}; margin: 0 0 10px 0; font-size: 22px;\">" . Str::sanitize($blockData->title) . "</h3>";

        if ($blockData->rating > 0) {
            $stars = str_repeat('★', (int)$blockData->rating) . str_repeat('☆', 5 - (int)$blockData->rating);
            $html[] = "<div style=\"color: #ffc107; font-size: 20px; margin-bottom: 5px;\">{$stars}</div>";
            $html[] = "<div style=\"color: {$blockData->textColor}; font-size: 14px; margin-bottom: 10px;\">";
            $html[] = "{$blockData->rating}/5";
            if ($blockData->reviewCount > 0) {
                $html[] = " ({$blockData->reviewCount} reviews)";
            }
            $html[] = "</div>";
        }

        if ($blockData->ctaText && $blockData->ctaUrl) {
            $html[] = '<a href="' . Str::sanitize($blockData->ctaUrl) . '" style="display: inline-block; padding: 10px 20px; background-color: white; color: ' . $blockData->backgroundColor . '; text-decoration: none; border-radius: 4px; font-weight: bold; margin-top: 10px;">';
            $html[] = Str::sanitize($blockData->ctaText);
            $html[] = '</a>';
        }

        $html[] = '</div>';
        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }

    private function renderProvidersBanner(BannerBlockData $blockData): RenderedBlock
    {
        $html = [];
        $html[] = "<div style=\"background-color: {$blockData->backgroundColor}; color: {$blockData->textColor}; padding: 25px; border-radius: 8px; margin: 20px 0;\">";
        $html[] = "<h3 style=\"color: {$blockData->textColor}; margin: 0 0 10px 0; font-size: 24px; text-align: center;\">" . Str::sanitize($blockData->title) . "</h3>";

        if ($blockData->subtitle) {
            $html[] = "<p style=\"color: {$blockData->textColor}; margin: 0 0 20px 0; font-size: 16px; text-align: center;\">" . Str::sanitize($blockData->subtitle) . "</p>";
        }

        if (!empty($blockData->providers)) {
            $html[] = '<table style="width: 100%; margin: 20px 0;"><tr>';
            $providerCount = count($blockData->providers);
            $cellWidth = floor(100 / min($providerCount, 4));

            foreach (array_slice($blockData->providers, 0, 4) as $provider) {
                $html[] = "<td style=\"width: {$cellWidth}%; text-align: center; padding: 10px; vertical-align: middle;\">";
                if (!empty($provider['logo'])) {
                    $html[] = '<img src="' . Str::sanitize($provider['logo']) . '" alt="' . Str::sanitize($provider['name']) . '" style="max-width: 100px; height: auto;">';
                } else {
                    $html[] = '<div style="font-weight: bold; color: ' . $blockData->textColor . ';">' . Str::sanitize($provider['name']) . '</div>';
                }
                $html[] = '</td>';
            }

            $html[] = '</tr></table>';
        }

        if ($blockData->ctaText && $blockData->ctaUrl) {
            $html[] = '<div style="text-align: center; margin-top: 20px;">';
            $html[] = '<a href="' . Str::sanitize($blockData->ctaUrl) . '" style="display: inline-block; padding: 12px 30px; background-color: white; color: ' . $blockData->backgroundColor . '; text-decoration: none; border-radius: 4px; font-weight: bold;">';
            $html[] = Str::sanitize($blockData->ctaText);
            $html[] = '</a>';
            $html[] = '</div>';
        }

        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }

    private function renderPromoBanner(BannerBlockData $blockData): RenderedBlock
    {
        $html = [];
        $html[] = "<div style=\"background-color: {$blockData->backgroundColor}; color: {$blockData->textColor}; padding: 25px; border-radius: 8px; margin: 20px 0; position: relative;\">";

        if ($blockData->dismissible) {
            $html[] = '<div style="position: absolute; top: 10px; right: 10px; color: ' . $blockData->textColor . '; cursor: pointer; font-size: 20px; line-height: 1;">×</div>';
        }

        if (!empty($blockData->image['src'])) {
            $html[] = sprintf(
                '<img src="%s" alt="%s" style="width:100%%;height:auto;display:block;margin-bottom:15px;border-radius:4px;">',
                Str::sanitize($blockData->image['src']),
                Str::sanitize($blockData->title)
            );
        }

        $html[] = "<h3 style=\"color: {$blockData->textColor}; margin: 0 0 10px 0; font-size: 24px;\">" . Str::sanitize($blockData->title) . "</h3>";

        if ($blockData->subtitle) {
            $html[] = "<p style=\"color: {$blockData->textColor}; margin: 0 0 15px 0; font-size: 16px;\">" . Str::sanitize($blockData->subtitle) . "</p>";
        }

        if ($blockData->ctaText && $blockData->ctaUrl) {
            $html[] = '<a href="' . Str::sanitize($blockData->ctaUrl) . '" style="display: inline-block; padding: 10px 20px; background-color: white; color: ' . $blockData->backgroundColor . '; text-decoration: none; border-radius: 4px; font-weight: bold;">';
            $html[] = Str::sanitize($blockData->ctaText);
            $html[] = '</a>';
        }

        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}
