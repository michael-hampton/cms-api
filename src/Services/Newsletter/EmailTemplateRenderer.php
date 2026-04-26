<?php

namespace App\Services\Newsletter;

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
 * This class is now a thin adapter over the shared newsletter rendering
 * pipeline (BlockDataFactory + EmailBlockRendererRegistry + LayoutBlockVariableResolver).
 * All per-block rendering logic lives in those collaborators, not here.
 *
 * Responsibilities:
 *   - Visible block filtering
 *   - Native block rendering (spacer, order_summary, ad_slot)
 *   - Delegating all other blocks to the shared newsletter renderer pipeline
 *   - Theme application and email chrome wrapping
 *
 * Does NOT:
 *   - Contain its own type registry (EmailTemplateBlockRegistry is deleted)
 *   - Duplicate BlockDataFactory's normalisation logic
 *   - Duplicate LayoutBlockVariableResolver's interpolation logic
 *   - Persist anything or send emails
 */
class EmailTemplateRenderer
{
    public function __construct(
        private readonly BlockDataFactory            $blockDataFactory,
        private readonly EmailBlockRendererRegistry  $rendererRegistry,
        private readonly AdResolver                  $adResolver,
        private readonly LayoutBlockVariableResolver $variableResolver,
        private readonly Logger                      $logger,
    )
    {
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Render a saved template with a live runtime data map.
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

        $bodyHtml = $this->renderBlocks(
            $template->getVisibleBlocks(),
            $runtimeData,
            $template->site_id,
            $theme,
            $context,
        );

        return $this->wrapInChrome($bodyHtml, $theme, $template->name);
    }

    /**
     * Render from raw (unsaved) editor blocks for live preview.
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

        $visible = array_values(array_filter($blocks, fn($b) => ($b['visible'] ?? true) === true));
        $bodyHtml = $this->renderBlocks($visible, $runtimeData, $siteId, $theme, $context);

        return $this->wrapInChrome($bodyHtml, $theme, 'Preview');
    }

    /**
     * Extract all {{ variable }} tokens from a block list (used by the frontend
     * to show unresolved token counts).
     *
     * @return string[]
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

    // ── Internal rendering ────────────────────────────────────────────────────

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
        $variables = array_merge(
            $this->variableResolver->buildVariableMap($context),
            $runtimeData,
        );

        $parts = [];

        foreach ($blocks as $block) {
            $type = $block['type'] ?? '';
            $data = $block['data'] ?? [];

            // Resolve {{ variable }} placeholders before hydration.
            $data = $this->variableResolver->resolveBlock($data, $variables);

            $html = $this->renderSingleBlock($type, $data, $siteId, $theme, $context);

            if ($html !== null && $html !== '') {
                $parts[] = $html;
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Dispatch a single block to the appropriate renderer.
     *
     * Native structural blocks (spacer, two_column, order_summary, ad_slot) are
     * handled inline here. Everything else goes through the shared newsletter
     * pipeline — BlockDataFactory → EmailBlockRendererRegistry — exactly as the
     * newsletter slot renderer does.
     *
     * Returns null to signal "omit this block entirely" (e.g. ad with no fill).
     */
    private function renderSingleBlock(
        string                  $type,
        array                   $data,
        int                     $siteId,
        ?EmailTheme             $theme,
        NewsletterRenderContext $context,
    ): ?string
    {
        // ── Native structural blocks ──────────────────────────────────────────
        if ($this->isNative($type)) {
            return match ($type) {
                'spacer' => $this->renderSpacer($data),
                'single_column',
                'two_column' => null,   // transparent structural wrappers
                'order_summary' => $this->renderOrderSummary($data),
                'ad_slot' => $this->renderAdSlot($data, $siteId, $context),
                default => null,
            };
        }

        // ── Skip unknown types ────────────────────────────────────────────────
        if (!$this->rendererRegistry->has($type) && $this->normaliseType($type) === null) {
            $this->logger->warning('EmailTemplateRenderer: unknown block type, skipped', ['type' => $type]);
            return null;
        }

        // ── Shared newsletter pipeline ────────────────────────────────────────
        // Normalise template-specific type aliases (e.g. 'button' → 'cta').
        $pipelineType = $this->normaliseType($type) ?? $type;
        $pipelineData = $this->normaliseData($type, $data);

        try {
            $blockData = $this->blockDataFactory->create($pipelineType, $pipelineData);
            $rendered = $this->rendererRegistry->render($pipelineType, $blockData, $context);

            return $rendered->wasRendered ? $rendered->html : null;
        } catch (\Throwable $e) {
            $this->logger->error('EmailTemplateRenderer: block render failed', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ── Type alias resolution ─────────────────────────────────────────────────

    /**
     * Block types that are handled natively (no newsletter pipeline).
     */
    private function isNative(string $type): bool
    {
        return in_array($type, [
            'spacer', 'single_column', 'two_column', 'order_summary', 'ad_slot',
        ], true);
    }

    /**
     * Map email-template-editor type aliases → newsletter pipeline type names.
     *
     * The email template editor uses a handful of simplified aliases that differ
     * from the canonical newsletter block types. All other types pass through
     * unchanged (the registry handles them directly).
     *
     * Returns null when the type is already canonical or unknown.
     */
    private function normaliseType(string $type): ?string
    {
        return match ($type) {
            'button' => 'cta',
            'product_card' => 'product',
            default => null,
        };
    }

    /**
     * Translate the raw data shape for aliased types.
     *
     * Only the aliased types (button → cta, product_card → product) need
     * translation. All canonical types pass their data through as-is because
     * their DTO fromArray() methods already handle the full field set.
     */
    private function normaliseData(string $type, array $data): array
    {
        return match ($type) {
            'button' => [
                'text' => $data['label'] ?? 'Click here',
                'url' => $data['url'] ?? '#',
                'ctaStyle' => $data['style'] ?? 'primary',
                'size' => 'medium',
                'alignment' => $data['align'] ?? 'center',
                'noFollow' => false,
                'sponsored' => false,
                'openInNewTab' => false,
            ],
            'product_card' => [
                'name' => $data['name'] ?? '',
                'description' => $data['description'] ?? null,
                'price' => (float)preg_replace('/[^0-9.]/', '', $data['price'] ?? '0'),
                'salePrice' => null,
                'currency' => '$',
                'link' => $data['url'] ?? null,
                'linkText' => 'View Product',
                'image' => !empty($data['image_url']) ? ['src' => $data['image_url']] : null,
            ],
            default => $data,
        };
    }

    // ── Native block renderers ────────────────────────────────────────────────

    private function renderSpacer(array $data): string
    {
        $height = max(4, (int)($data['height'] ?? 24));
        return sprintf(
            '<div style="height:%dpx;line-height:%dpx;font-size:1px;">&nbsp;</div>',
            $height,
            $height,
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

        $html .= '</td></tr></table>';
        return $html;
    }

    private function renderAdSlot(array $data, int $siteId, NewsletterRenderContext $context): ?string
    {
        $placement = $data['placement'] ?? 'mid';
        $fallback = $data['fallback'] ?? 'hide';

        $adBlock = $this->adResolver->resolveBlock($placement, $siteId, $context->member);

        if ($adBlock !== null) {
            try {
                $adType = $adBlock['type'] ?? '';
                $adData = $adBlock['data'] ?? [];
                $blockData = $this->blockDataFactory->create($adType, $adData);
                $rendered = $this->rendererRegistry->render($adType, $blockData, $context);
                return $rendered->wasRendered ? $rendered->html : null;
            } catch (\Throwable $e) {
                $this->logger->error('EmailTemplateRenderer: ad block render failed', ['error' => $e->getMessage()]);
                return null;
            }
        }

        if ($fallback === 'placeholder') {
            return '<div style="border:2px dashed #e9ecef;padding:16px;text-align:center;color:#aaa;font-size:12px;margin:16px 0;">Ad slot — ' . htmlspecialchars($placement) . '</div>';
        }

        return null; // 'hide' — no spacing residue
    }

    // ── Email chrome ──────────────────────────────────────────────────────────

    private function wrapInChrome(string $bodyHtml, ?EmailTheme $theme, string $subject): string
    {
        $appName = \App\Framework\Support\Config::get('app.name', 'Application');
        $appUrl = \App\Framework\Support\Config::get('app.url', 'http://localhost');
        $year = date('Y');

        $primary = $theme ? $theme->getColor('primary', '#667eea') : '#667eea';
        $secondary = $theme ? $theme->getColor('secondary', '#764ba2') : '#764ba2';
        $bgColor = $theme ? $theme->getColor('background', '#f6f6f6') : '#f6f6f6';
        $cardBg = $theme ? $theme->getColor('card_background', '#ffffff') : '#ffffff';
        $textColor = $theme ? $theme->getColor('text', '#333333') : '#333333';
        $textLight = $theme ? $theme->getColor('text_light', '#6c757d') : '#6c757d';
        $borderColor = $theme ? $theme->getColor('border', '#e9ecef') : '#e9ecef';

        $bodyFont = $theme ? $theme->getFont('body') : null;
        $fontFamily = $bodyFont['family'] ?? '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';
        $fontSize = $bodyFont['size'] ?? '15px';

        $maxWidth = $theme ? (int)$theme->getSetting('max_width', 600) : 600;
        $padding = $theme ? (int)$theme->getSetting('padding', 20) : 20;
        $borderRadius = $theme ? (int)$theme->getSetting('border_radius', 8) : 8;
        $showFooter = $theme ? (bool)$theme->getSetting('show_footer', true) : true;
        $showSocial = $theme ? (bool)$theme->getSetting('show_social_links', true) : true;
        $headerGrad = $theme
            ? $theme->getSetting('header_gradient', "linear-gradient(135deg, {$primary} 0%, {$secondary} 100%)")
            : "linear-gradient(135deg, {$primary} 0%, {$secondary} 100%)";

        // Logo
        $logo = $theme ? $theme->getAsset('logo') : null;
        $logoHtml = '';
        if ($logo && !empty($logo['url'])) {
            $w = !empty($logo['width']) ? ' width="' . (int)$logo['width'] . '"' : '';
            $h = !empty($logo['height']) ? ' height="' . (int)$logo['height'] . '"' : '';
            $alt = htmlspecialchars($logo['alt'] ?? $appName, ENT_QUOTES);
            $logoHtml = '<img src="' . htmlspecialchars($logo['url'], ENT_QUOTES) . '" alt="' . $alt . '"' . $w . $h . ' style="max-height:50px;display:block;margin:0 auto 10px;">';
        }

        // Footer
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
    <p style="margin:0 0 4px;font-size:12px;color:{$textLight};">&copy; {$year} {$appName}. All rights reserved.</p>
    <p style="margin:0;font-size:12px;color:{$textLight};"><a href="{$appUrl}" style="color:{$primary};text-decoration:none;">{$appUrl}</a></p>
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
        <tr>
          <td style="background:{$headerGrad};padding:32px 30px;text-align:center;">
            {$logoHtml}
            <h1 style="margin:0;color:#ffffff;font-size:24px;font-weight:700;">{$appName}</h1>
          </td>
        </tr>
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
}