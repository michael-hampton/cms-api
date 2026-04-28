<?php

namespace App\Services\Newsletter;

use App\Framework\Support\Config;
use App\Framework\Support\Logger;
use App\Models\EmailTheme;
use App\Models\Newsletter;
use App\Models\NewsletterBrandingConfiguration;
use App\Models\NewsletterLayout;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\Layout\LayoutBlockVariableResolver;
use App\Services\Newsletter\Renderers\EmailBlockRendererRegistry;
use App\Services\Newsletter\Services\BlockDataFactory;

/**
 * Renders an email template (NewsletterLayout with type='email_template') to a
 * complete HTML document.
 *
 * This class is a thin adapter. All per-block rendering is handled by the
 * shared newsletter pipeline:
 *   BlockDataFactory → EmailBlockRendererRegistry → LayoutBlockVariableResolver
 *
 * The only logic that lives here is:
 *   - Building the NewsletterRenderContext from the flat email-template model
 *   - Dispatching native structural blocks (spacer, order_summary, ad_slot)
 *   - Translating the two aliased type names (button→cta, product_card→product)
 *   - Assembling the email chrome (header, footer, doctype)
 *
 * EmailTemplateBlockRegistry has been deleted — its tiny normalisation map is
 * expressed in normaliseType() / normaliseData() below.
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
     * Render a saved email template layout.
     */
    public function render(
        NewsletterLayout $layout,
        array            $runtimeData = [],
        ?NewsletterBrandingConfiguration $themeOverride = null,
    ): string
    {
        $theme = $themeOverride ?? ($layout->theme_id ? null : null); // resolved by service
        $context = $this->buildRenderContext($layout->site_id, $layout, $runtimeData);

        $this->adResolver->warmCache($layout->site_id, $context->member);

        $bodyHtml = $this->renderBlocks(
            $layout->getVisibleBlocks(),
            $runtimeData,
            $layout->site_id,
            $theme,
            $context,
        );

        return $this->wrapInChrome($bodyHtml, $theme, $layout->name);
    }

    /**
     * Render from raw (unsaved) editor blocks for live preview.
     */
    public function renderPreview(
        array       $blocks,
        array       $runtimeData,
        int         $siteId,
        ?NewsletterBrandingConfiguration $theme = null,
    ): string
    {
        // Build a minimal layout shell so we can populate a render context.
        $shell = new NewsletterLayout();
        $shell->id = 0;
        $shell->name = 'Preview';
        $shell->slug = 'preview';
        $shell->site_id = $siteId;

        $context = $this->buildRenderContext($siteId, $shell, $runtimeData);
        $this->adResolver->warmCache($siteId, $context->member);

        $visible = array_values(array_filter($blocks, fn($b) => ($b['visible'] ?? true) === true));
        $bodyHtml = $this->renderBlocks($visible, $runtimeData, $siteId, $theme, $context);

        return $this->wrapInChrome($bodyHtml, $theme, 'Preview');
    }

    /**
     * Extract all {{ variable }} tokens from a block list.
     * Used by the frontend to show unresolved token counts.
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

    // ── Rendering ─────────────────────────────────────────────────────────────

    private function buildRenderContext(
        int              $siteId,
        NewsletterLayout $layout,
        array            $runtimeData,
    ): NewsletterRenderContext
    {
        // The newsletter pipeline expects a Newsletter model for context.
        // We construct a minimal one from the layout so variable resolution
        // (newsletter.title etc.) works correctly in block content.
        $newsletter = new Newsletter();
        $newsletter->id = $layout->id;
        $newsletter->title = $layout->name;
        $newsletter->slug = $layout->slug;
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
        ?NewsletterBrandingConfiguration $theme,
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
            $data = $this->variableResolver->resolveBlock($block['data'] ?? [], $variables);
            $html = $this->renderSingleBlock($type, $data, $siteId, $theme, $context);

            if ($html !== null && $html !== '') {
                $parts[] = $html;
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Dispatch a single block.
     *
     * Native structural blocks are handled inline. All other blocks go through
     * the shared newsletter pipeline (BlockDataFactory → EmailBlockRendererRegistry)
     * exactly as SlotRenderer does — ensuring zero duplication of render logic.
     *
     * Returns null to signal "omit this block entirely".
     */
    private function renderSingleBlock(
        string                  $type,
        array                   $data,
        int                     $siteId,
        ?NewsletterBrandingConfiguration $theme,
        NewsletterRenderContext $context,
    ): ?string
    {
        // Native structural blocks
        if ($this->isNative($type)) {
            return match ($type) {
                'spacer' => $this->renderSpacer($data),
                'single_column',
                'two_column' => null,
                'order_summary' => $this->renderOrderSummary($data),
                'ad_slot' => $this->renderAdSlot($data, $siteId, $context),
                default => null,
            };
        }

        // Resolve any type alias (button → cta, product_card → product)
        $pipelineType = $this->normaliseType($type);
        $pipelineData = $this->normaliseData($type, $data);

        if (!$this->rendererRegistry->has($pipelineType)) {
            $this->logger->warning('EmailTemplateRenderer: unknown block type, skipped', ['type' => $type]);
            return null;
        }

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

    // ── Type normalisation ────────────────────────────────────────────────────

    private function isNative(string $type): bool
    {
        return in_array($type, [
            'spacer', 'single_column', 'two_column', 'order_summary', 'ad_slot',
        ], true);
    }

    /**
     * Resolve email-template type aliases to canonical newsletter pipeline types.
     * For any type that needs no translation the same value is returned, so the
     * caller can always use the result directly.
     */
    private function normaliseType(string $type): string
    {
        return match ($type) {
            'button' => 'cta',
            'product_card' => 'product',
            default => $type,
        };
    }

    /**
     * Translate data shape for aliased types only.
     * All canonical types pass their data through unchanged because their
     * fromArray() implementations already accept the full field set.
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
            $html .= '<tr><td style="color:#333;padding:4px 0;">{{ order.items }}</td>'
                . '<td style="text-align:right;color:#333;padding:4px 0;">{{ order.subtotal }}</td></tr>';
            $html .= '</table>';
        }

        if ($showShip) {
            $html .= '<table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;">';
            $html .= '<tr><td style="color:#666;padding:2px 0;">Shipping</td>'
                . '<td style="text-align:right;color:#666;padding:2px 0;">{{ order.shipping_cost }}</td></tr>';
            $html .= '</table>';
        }

        if ($showTotals) {
            $html .= '<table width="100%" cellpadding="0" cellspacing="0" style="border-top:2px solid #e9ecef;margin-top:10px;padding-top:10px;font-size:14px;">';
            $html .= '<tr><td style="font-weight:700;color:#333;padding:4px 0;">Total</td>'
                . '<td style="text-align:right;font-weight:700;color:#333;padding:4px 0;">{{ order.total }}</td></tr>';
            $html .= '</table>';
        }

        $html .= '</td></tr></table>';

        return $html;
    }

    private function renderAdSlot(
        array                   $data,
        int                     $siteId,
        NewsletterRenderContext $context,
    ): ?string
    {
        $placement = $data['placement'] ?: 'top';
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
                $this->logger->error('EmailTemplateRenderer: ad block render failed', [
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        }

        // No ad available
        if ($fallback === 'placeholder') {
            return '<div style="border:2px dashed #e9ecef;padding:16px;text-align:center;'
                . 'color:#aaa;font-size:12px;margin:16px 0;">Ad slot — '
                . htmlspecialchars($placement) . '</div>';
        }

        return null; // 'hide' — no spacing residue
    }

    // ── Email chrome ──────────────────────────────────────────────────────────

    private function wrapInChrome(string $bodyHtml, ?EmailTheme $theme, string $subject): string
    {
        $appName = Config::get('app.name', 'Application');
        $appUrl = Config::get('app.url', 'http://localhost');
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
            $logoHtml = '<img src="' . htmlspecialchars($logo['url'], ENT_QUOTES)
                . '" alt="' . $alt . '"' . $w . $h
                . ' style="max-height:50px;display:block;margin:0 auto 10px;">';
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