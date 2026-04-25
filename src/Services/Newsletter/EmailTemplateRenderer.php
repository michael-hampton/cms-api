<?php

namespace App\Services\Newsletter;

use App\Framework\Support\Config;
use App\Framework\Support\Logger;
use App\Models\EmailTemplate;
use App\Models\EmailTheme;
use App\Models\Newsletter;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\Layout\LayoutBlockVariableResolver;
use App\Services\Newsletter\Renderers\EmailBlockRendererRegistry;
use App\Services\Newsletter\Services\BlockDataFactory;

/**
 * Renders an EmailTemplate's block array to a complete email HTML document.
 *
 * Rendering flow (matches ticket spec):
 *   Template blocks (JSON)
 *   → Inject runtime data variables ({{ variable }} interpolation)
 *   → Inject brand/theme config
 *   → Resolve ad_slot blocks via AdResolver
 *   → Render each visible block via BlockDataFactory + EmailBlockRendererRegistry
 *   → Wrap in the email chrome (doctype, header, footer)
 *   → Output HTML
 *
 * Responsibilities:
 *   - Visible block filtering
 *   - Variable interpolation ({{ variable }} → resolved value)
 *   - Ad slot resolution (missing ad → block removed cleanly, no spacing residue)
 *   - Structural block rendering (spacer, single_column, two_column, order_summary)
 *   - Delegating content blocks to the newsletter renderer pipeline
 *   - Theme application (colors, fonts, settings, logo)
 *
 * Does NOT:
 *   - Persist anything
 *   - Send emails
 *   - Know about recipients
 */
class EmailTemplateRenderer
{
    public function __construct(
        private readonly BlockDataFactory            $blockDataFactory,
        private readonly EmailBlockRendererRegistry  $rendererRegistry,
        private readonly EmailTemplateBlockRegistry  $blockRegistry,
        private readonly AdResolver                  $adResolver,
        private readonly LayoutBlockVariableResolver $variableResolver,
        private readonly Logger                      $logger,
    )
    {
    }

    // ── Public API ────────────────────────────────────────────

    /**
     * Render a saved template with a live runtime data map.
     *
     * @param EmailTemplate $template
     * @param array<string, mixed> $runtimeData Flat dot-notation map, e.g. ['user.first_name' => 'Sarah']
     * @param EmailTheme|null $themeOverride If null, the template's own theme is used (loaded by caller).
     */
    public function render(
        EmailTemplate $template,
        array         $runtimeData = [],
        ?EmailTheme   $themeOverride = null,
    ): string
    {
        $theme = $themeOverride ?? $template->theme;

        $context = $this->buildRenderContext($template->site_id, $runtimeData);
        $this->adResolver->warmCache($template->site_id, $context->member);

        $blocks = $template->getVisibleBlocks();
        $bodyHtml = $this->renderBlocks($blocks, $runtimeData, $template->site_id, $theme, $context);

        return $this->wrapInChrome($bodyHtml, $theme, $template->name);
    }

    private function buildRenderContext(int $siteId, array $runtimeData): NewsletterRenderContext
    {
        $newsletter = new Newsletter();
        $newsletter->id = (int)($runtimeData['newsletter.id'] ?? 0);
        $newsletter->title = (string)($runtimeData['newsletter.title'] ?? 'Email Template');
        $newsletter->slug = (string)($runtimeData['newsletter.slug'] ?? 'email-template');
        $newsletter->template = 'email-template';
        $newsletter->interval = 'adhoc';
        $newsletter->design_config = [];

        $member = $runtimeData['__member'] ?? null;

        return new NewsletterRenderContext(
            siteId: $siteId,
            newsletter: $newsletter,
            member: $member instanceof \App\Models\Member ? $member : null,
            sendId: null,
            includeTracking: false,
        );
    }

    private function renderBlocks(
        array                   $blocks,
        array                   $runtimeData,
        int                     $siteId,
        ?EmailTheme             $theme,
        NewsletterRenderContext $context,
    ): string
    {
        $parts = [];
        $variables = array_merge(
            $this->variableResolver->buildVariableMap($context),
            $runtimeData,
        );

        foreach ($blocks as $block) {
            $type = $block['type'] ?? '';
            $data = $block['data'] ?? [];

            $data = $this->variableResolver->resolveBlock($data, $variables);

            $html = $this->renderBlock($type, $data, $siteId, $theme, $context);

            if ($html !== null && $html !== '') {
                $parts[] = $html;
            }
            // null/'' = block cleanly removed (ad_slot with no ad, hidden block, etc.)
        }

        return implode("\n", $parts);
    }

    // ── Block rendering ───────────────────────────────────────

    /**
     * Render a single block. Returns null to signal "remove this block entirely".
     */
    private function renderBlock(
        string                  $type,
        array                   $data,
        int                     $siteId,
        ?EmailTheme             $theme,
        NewsletterRenderContext $context,
    ): ?string
    {
        if (!$this->blockRegistry->isValid($type)) {
            $this->logger->warning('EmailTemplateRenderer: unknown block type, skipped', ['type' => $type]);
            return null;
        }

        // Native blocks handled directly (no newsletter renderer)
        if ($this->blockRegistry->isNative($type)) {
            return $this->renderNativeBlock($type, $data, $siteId, $theme, $context);
        }

        // Delegate to newsletter block pipeline
        $newsletterType = $this->blockRegistry->getNewsletterType($type);

        if ($newsletterType === null) {
            return null;
        }

        $normalisedData = $this->blockRegistry->normaliseBlockData($type, $data);
        if ($normalisedData === null) {
            return null;
        }

        try {
            $blockData = $this->blockDataFactory->create($newsletterType, $normalisedData);

            $rendered = $this->rendererRegistry->render($newsletterType, $blockData, $context);

            return $rendered->wasRendered ? $rendered->html : null;

        } catch (\Throwable $e) {
            $this->logger->error('EmailTemplateRenderer: block render failed', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function renderNativeBlock(
        string                  $type,
        array                   $data,
        int                     $siteId,
        ?EmailTheme             $theme,
        NewsletterRenderContext $context,
    ): ?string
    {
        return match ($type) {
            'spacer' => $this->renderSpacer($data),
            'single_column' => null, // structural wrapper — transparent
            'two_column' => null, // structural wrapper — transparent
            'order_summary' => $this->renderOrderSummary($data),
            'ad_slot' => $this->renderAdSlot($data, $siteId, $context),
            default => null,
        };
    }

    // ── Native block renderers ────────────────────────────────

    private function renderSpacer(array $data): string
    {
        $height = max(4, (int)($data['height'] ?? 24));
        return sprintf(
            '<div style="height:%dpx;line-height:%dpx;font-size:1px;">&nbsp;</div>',
            $height,
            $height
        );
    }

    private function renderOrderSummary(array $data): string
    {
        $title = htmlspecialchars($data['title'] ?? 'Order Summary', ENT_QUOTES);
        $showItems = (bool)($data['show_line_items'] ?? true);
        $showTotals = (bool)($data['show_totals'] ?? true);
        $showShip = (bool)($data['show_shipping'] ?? true);

        $html = '<table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e9ecef;border-radius:6px;overflow:hidden;margin:16px 0;">';
        $html .= '<tr><td style="background:#f8f9fa;padding:12px 16px;font-weight:700;font-size:14px;color:#333;border-bottom:1px solid #e9ecef;">' . $title . '</td></tr>';
        $html .= '<tr><td style="padding:16px;">';

        if ($showItems) {
            $html .= '<table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;margin-bottom:12px;">';
            $html .= '<tr><td style="color:#333;padding:4px 0;">{{ order.items }}</td><td style="text-align:right;color:#333;padding:4px 0;">{{ order.subtotal }}</td></tr>';
            $html .= '</table>';
        }

        if ($showShip) {
            $html .= '<table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;">';
            $html .= '<tr><td style="color:#666;padding:2px 0;">Shipping</td><td style="text-align:right;color:#666;padding:2px 0;">{{ order.shipping_cost }}</td></tr>';
            $html .= '</table>';
        }

        if ($showTotals) {
            $html .= '<table width="100%" cellpadding="0" cellspacing="0" style="border-top:2px solid #e9ecef;margin-top:10px;padding-top:10px;font-size:14px;">';
            $html .= '<tr><td style="font-weight:700;color:#333;padding:4px 0;">Total</td><td style="text-align:right;font-weight:700;color:#333;padding:4px 0;">{{ order.total }}</td></tr>';
            $html .= '</table>';
        }

        $html .= '</td></tr>';
        $html .= '</table>';

        return $html;
    }

    private function renderAdSlot(array $data, int $siteId, NewsletterRenderContext $context): ?string
    {
        $placement = $data['placement'] ?? 'mid';
        $fallback = $data['fallback'] ?? 'hide';

        $adBlock = $this->adResolver->resolveBlock($placement, $siteId, $context->member);

        if ($adBlock !== null) {
            return $this->renderSharedBlock(
                $adBlock['type'] ?? '',
                $adBlock['data'] ?? [],
                $context,
            );
        }

        // No ad available
        if ($fallback === 'placeholder') {
            return '<div style="border:2px dashed #e9ecef;padding:16px;text-align:center;color:#aaa;font-size:12px;margin:16px 0;">Ad slot — ' . htmlspecialchars($placement) . '</div>';
        }

        // 'hide' — return null so no spacing artifact remains
        return null;
    }

    private function renderSharedBlock(string $type, array $data, NewsletterRenderContext $context): ?string
    {
        if ($type === '') {
            return null;
        }

        $blockData = $this->blockDataFactory->create($type, $data);
        $rendered = $this->rendererRegistry->render($type, $blockData, $context);

        return $rendered->wasRendered ? $rendered->html : null;
    }

    private function wrapInChrome(string $bodyHtml, ?EmailTheme $theme, string $subject): string
    {
        $appName = Config::get('app.name', 'Application');
        $appUrl = Config::get('app.url', 'http://localhost');
        $year = date('Y');

        // ── Theme values ──────────────────────────────────────
        $primary = $theme ? $theme->getColor('primary', '#667eea') : '#667eea';
        $secondary = $theme ? $theme->getColor('secondary', '#764ba2') : '#764ba2';
        $bgColor = $theme ? $theme->getColor('background', '#f6f6f6') : '#f6f6f6';
        $cardBg = $theme ? $theme->getColor('card_background', '#ffffff') : '#ffffff';
        $textColor = $theme ? $theme->getColor('text', '#333333') : '#333333';
        $textLightColor = $theme ? $theme->getColor('text_light', '#6c757d') : '#6c757d';
        $borderColor = $theme ? $theme->getColor('border', '#e9ecef') : '#e9ecef';

        $bodyFontData = $theme ? $theme->getFont('body') : null;
        $fontFamily = $bodyFontData['family'] ?? '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';
        $fontSize = $bodyFontData['size'] ?? '15px';

        $maxWidth = $theme ? (int)$theme->getSetting('max_width', 600) : 600;
        $padding = $theme ? (int)$theme->getSetting('padding', 20) : 20;
        $borderRadius = $theme ? (int)$theme->getSetting('border_radius', 8) : 8;
        $showFooter = $theme ? (bool)$theme->getSetting('show_footer', true) : true;
        $showSocial = $theme ? (bool)$theme->getSetting('show_social_links', true) : true;
        $headerGradient = $theme
            ? $theme->getSetting('header_gradient', "linear-gradient(135deg, {$primary} 0%, {$secondary} 100%)")
            : "linear-gradient(135deg, {$primary} 0%, {$secondary} 100%)";

        // ── Logo ──────────────────────────────────────────────
        $logo = $theme ? $theme->getAsset('logo') : null;
        $logoHtml = '';
        if ($logo && !empty($logo['url'])) {
            $w = !empty($logo['width']) ? ' width="' . (int)$logo['width'] . '"' : '';
            $h = !empty($logo['height']) ? ' height="' . (int)$logo['height'] . '"' : '';
            $alt = htmlspecialchars($logo['alt'] ?? $appName, ENT_QUOTES);
            $logoHtml = '<img src="' . htmlspecialchars($logo['url'], ENT_QUOTES) . '" alt="' . $alt . '"' . $w . $h . ' style="max-height:50px;display:block;margin:0 auto 10px;">';
        }

        // ── Footer ────────────────────────────────────────────
        $footerHtml = '';
        if ($showFooter) {
            $socialHtml = '';
            if ($showSocial) {
                $socialHtml = <<<HTML
<p style="margin:8px 0 0;">
  <a href="{$appUrl}" style="color:{$primary};text-decoration:none;font-size:12px;margin:0 6px;">Website</a>
  &middot;
  <a href="{$appUrl}/privacy" style="color:{$primary};text-decoration:none;font-size:12px;margin:0 6px;">Privacy</a>
</p>
HTML;
            }
            $footerHtml = <<<HTML
<tr>
  <td style="background:{$cardBg};padding:{$padding}px 30px;text-align:center;border-top:1px solid {$borderColor};">
    <p style="margin:0 0 4px;font-size:12px;color:{$textLightColor};">&copy; {$year} {$appName}. All rights reserved.</p>
    <p style="margin:0;font-size:12px;color:{$textLightColor};"><a href="{$appUrl}" style="color:{$primary};text-decoration:none;">{$appUrl}</a></p>
    {$socialHtml}
  </td>
</tr>
HTML;
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>{$subject}</title>
</head>
<body style="margin:0;padding:0;background-color:{$bgColor};font-family:{$fontFamily};color:{$textColor};font-size:{$fontSize};line-height:1.6;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:{$bgColor};padding:24px 0;">
  <tr>
    <td align="center">
      <table width="{$maxWidth}" cellpadding="0" cellspacing="0" style="max-width:{$maxWidth}px;width:100%;background-color:{$cardBg};border-radius:{$borderRadius}px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

        <!-- Header -->
        <tr>
          <td style="background:{$headerGradient};padding:32px 30px;text-align:center;">
            {$logoHtml}
            <h1 style="margin:0;color:#ffffff;font-size:24px;font-weight:700;">{$appName}</h1>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:{$padding}px 30px;">
            {$bodyHtml}
          </td>
        </tr>

        {$footerHtml}

      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML;
    }

    /**
     * Render from raw block data (unsaved editor state) for live preview.
     *
     * @param array $blocks Raw block array [{type, data, visible}]
     * @param array $runtimeData Variable map (from PreviewDataFactory)
     * @param int $siteId
     * @param EmailTheme|null $theme
     */
    public function renderPreview(
        array       $blocks,
        array       $runtimeData,
        int         $siteId,
        ?EmailTheme $theme = null,
    ): string
    {
        $context = $this->buildRenderContext($siteId, $runtimeData);
        $this->adResolver->warmCache($siteId, $context->member);

        $visibleBlocks = array_values(array_filter($blocks, fn($b) => ($b['visible'] ?? true) === true));

        $bodyHtml = $this->renderBlocks($visibleBlocks, $runtimeData, $siteId, $theme, $context);

        return $this->wrapInChrome($bodyHtml, $theme, 'Preview');
    }

    // ── Email chrome ──────────────────────────────────────────

    /**
     * Extract all {{ variable }} tokens from a block list.
     * Used by the frontend to show unresolved token counts.
     *
     * @return string[]  De-duplicated token names, e.g. ['user.first_name', 'order.total']
     */
    public function extractTokens(array $blocks): array
    {
        $pattern = '/\{\{\s*([\w.]+)\s*\}\}/';
        $tokens = [];

        $walk = static function (mixed $value) use (&$walk, &$tokens, $pattern): void {
            if (is_string($value)) {
                preg_match_all($pattern, $value, $matches);
                foreach ($matches[1] as $token) {
                    $tokens[$token] = true;
                }
            } elseif (is_array($value)) {
                foreach ($value as $v) {
                    $walk($v);
                }
            }
        };

        foreach ($blocks as $block) {
            $walk($block['data'] ?? []);
        }

        return array_keys($tokens);
    }
}
