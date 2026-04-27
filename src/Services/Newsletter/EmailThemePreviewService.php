<?php

namespace App\Services\Newsletter;

use App\Framework\Support\Config;
use App\Models\NewsletterBrandingConfiguration;

/**
 * Renders a live HTML preview of an email theme without dispatching mail.
 *
 * All theme data is read from NewsletterBrandingConfiguration.theme_json:
 *   { colors, fonts, assets, settings }
 *
 * renderFromModel() accepts a NewsletterBrandingConfiguration (previously
 * accepted EmailTheme — signature is updated but the internal render path
 * is identical).
 *
 * renderFromData() accepts the raw editor payload and is unchanged.
 */
class EmailThemePreviewService
{
    private const SAMPLE_CONTENT = [
        'default' => [
            'subject' => 'Your order has been confirmed',
            'greeting' => 'Hello Sarah,',
            'body' => <<<MD
Thank you for your order! We're thrilled to have you as a customer.

Your order **#ORD-2024-8821** has been received and is being processed.

## Order Summary

@table(Item|Qty|Price)
@row(Premium Plan Subscription|1|$49.00)
@row(Add-on: Extra Storage (50GB)|1|$9.00)
@row(Discount: WELCOME20||−$11.60)
@endtable

@divider

@panel(Your subscription activates immediately. You can manage your plan from the [customer portal](https://example.com/portal) at any time.)

@button(View Your Order, https://example.com/orders/8821)
@buttonSecondary(Download Invoice, https://example.com/invoice/8821)

If you have any questions, simply reply to this email — we're always happy to help.

@subcopy(You're receiving this email because you made a purchase at Example Inc. If you believe this is a mistake, please [contact support](https://example.com/support).)
MD,
        ],
        'minimal' => [
            'subject' => 'Reset your password',
            'greeting' => 'Hi there,',
            'body' => <<<MD
We received a request to reset the password for your account.

@button(Reset Password, https://example.com/reset/token123)

This link will expire in **24 hours**. If you didn't request a password reset, you can safely ignore this email — your password will not be changed.

@subcopy(For security reasons, we never ask for your password via email. If you're concerned about your account security, please [contact us](https://example.com/support).)
MD,
        ],
        'promotion' => [
            'subject' => 'Exclusive offer just for you 🎉',
            'greeting' => 'Hey there,',
            'body' => <<<MD
We've got something special lined up just for you.

@promotion(🔥 LIMITED TIME: 30% off all plans — use code SAVE30 at checkout)

## What's included

- **Unlimited** projects and collaborators
- **50 GB** of secure cloud storage
- Priority support with a **4-hour** response guarantee
- Access to all **premium templates**

@button(Claim Your Discount, https://example.com/upgrade?code=SAVE30)

Offer expires midnight this Sunday. Don't miss out!

@divider

@price(34.30)
/month after discount (was $49.00)

@subcopy(This offer is exclusive to existing customers. Discount applies to first 12 months of an annual plan only.)
MD,
        ],
    ];

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Render a preview from a fully-formed NewsletterBrandingConfiguration model.
     * theme_json is the authoritative data source.
     */
    public function renderFromModel(NewsletterBrandingConfiguration $theme, string $sampleType = 'default'): string
    {
        $themeData = $this->extractThemeData($theme);
        return $this->render($themeData, $sampleType);
    }

    /**
     * Render a preview from raw (unsaved) editor data.
     * $data shape: { colors, fonts, assets, settings, name }
     */
    public function renderFromData(array $data, string $sampleType = 'default'): string
    {
        $themeData = $this->normaliseData($data);
        return $this->render($themeData, $sampleType);
    }

    // =========================================================================
    // Private — data extraction
    // =========================================================================

    /**
     * Flatten a NewsletterBrandingConfiguration into the plain array shape
     * expected by render().  All data comes from theme_json.
     */
    private function extractThemeData(NewsletterBrandingConfiguration $theme): array
    {
        return [
            'colors' => $theme->getColors(),
            'fonts' => $theme->getFonts(),
            'settings' => $theme->getSettings(),
            'assets' => $theme->getAssets(),
            'name' => $theme->name ?? Config::get('app.name', 'Application'),
        ];
    }

    private function normaliseData(array $data): array
    {
        return [
            'colors' => $data['colors'] ?? [],
            'fonts' => $data['fonts'] ?? [],
            'settings' => $data['settings'] ?? [],
            'assets' => $data['assets'] ?? [],
            'name' => $data['name'] ?? Config::get('app.name', 'Application'),
        ];
    }

    // =========================================================================
    // Private — rendering
    // =========================================================================

    private function render(array $theme, string $sampleType): string
    {
        $sample = self::SAMPLE_CONTENT[$sampleType] ?? self::SAMPLE_CONTENT['default'];
        $content = $this->convertMarkdown($sample['body'], $theme);
        return $this->wrapTemplate($content, $sample, $theme);
    }

    private function color(array $theme, string $key, string $default): string
    {
        return $theme['colors'][$key] ?? $default;
    }

    private function setting(array $theme, string $key, mixed $default): mixed
    {
        return $theme['settings'][$key] ?? $default;
    }

    private function font(array $theme, string $key): array
    {
        return $theme['fonts'][$key] ?? [];
    }

    private function convertMarkdown(string $markdown, array $theme): string
    {
        $primary = $this->color($theme, 'primary', '#667eea');
        $secondary = $this->color($theme, 'secondary', '#764ba2');
        $success = $this->color($theme, 'success', '#4CAF50');
        $warning = $this->color($theme, 'warning', '#ffc107');
        $text = $this->color($theme, 'text', '#333333');
        $textLight = $this->color($theme, 'text_light', '#6c757d');
        $border = $this->color($theme, 'border', '#e9ecef');
        $link = $this->color($theme, 'link', '#3498db');
        $panelBg = $this->color($theme, 'card_background', '#f8f9fa');
        $borderRadius = (int)$this->setting($theme, 'border_radius', 6);

        $html = nl2br(htmlspecialchars($markdown, ENT_QUOTES, 'UTF-8', false));

        // Headings
        $html = preg_replace('/^## (.+)$/m', "<h2 style=\"color:{$text};font-size:20px;margin:18px 0 8px;font-weight:600;line-height:1.3;\">$1</h2>", $html);
        $html = preg_replace('/^### (.+)$/m', "<h3 style=\"color:{$textLight};font-size:16px;margin:14px 0 6px;font-weight:600;\">$1</h3>", $html);

        // Inline formatting
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/', '<em>$1</em>', $html);
        $html = preg_replace('/~~(.+?)~~/', '<del style="text-decoration:line-through;">$1</del>', $html);

        // Links
        $html = preg_replace(
            '/\[(.+?)\]\((.+?)\)/',
            "<a href=\"\$2\" style=\"color:{$link};text-decoration:underline;\">\$1</a>",
            $html
        );

        // Buttons
        $html = preg_replace(
            '/@button\(([^,]+),\s*([^)]+)\)/',
            "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"margin:20px 0;\"><tr><td align=\"center\">"
            . "<a href=\"\$2\" style=\"display:inline-block;padding:13px 32px;background:{$success};color:#ffffff;"
            . "text-decoration:none;border-radius:{$borderRadius}px;font-weight:700;font-size:15px;\">\$1</a>"
            . "</td></tr></table>",
            $html
        );
        $html = preg_replace(
            '/@buttonSecondary\(([^,]+),\s*([^)]+)\)/',
            "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"margin:16px 0;\"><tr><td align=\"center\">"
            . "<a href=\"\$2\" style=\"display:inline-block;padding:10px 24px;background:{$secondary};color:#ffffff;"
            . "text-decoration:none;border-radius:{$borderRadius}px;font-size:14px;\">\$1</a>"
            . "</td></tr></table>",
            $html
        );

        // Panel
        $html = preg_replace(
            '/@panel\((.+?)\)/s',
            "<div style=\"background:{$panelBg};border-left:4px solid {$primary};padding:14px 18px;"
            . "margin:16px 0;border-radius:0 {$borderRadius}px {$borderRadius}px 0;\">$1</div>",
            $html
        );

        // Promotion
        $html = preg_replace(
            '/@promotion\((.+?)\)/s',
            "<div style=\"background:#fff8e1;border:2px solid {$warning};padding:16px;margin:16px 0;"
            . "border-radius:{$borderRadius}px;text-align:center;\"><strong style=\"color:#7a5c00;font-size:15px;\">$1</strong></div>",
            $html
        );

        // Table
        $html = preg_replace_callback(
            '/@table\(([^)]+)\)(.*?)@endtable/s',
            function (array $m) use ($border, $panelBg, $text): string {
                $headers = explode('|', $m[1]);
                $tableHtml = '<table style="width:100%;border-collapse:collapse;margin:18px 0;font-size:14px;">';
                $tableHtml .= '<thead><tr>';
                foreach ($headers as $h) {
                    $tableHtml .= "<th style=\"border-bottom:2px solid {$border};padding:10px 12px;text-align:left;"
                        . "font-weight:700;color:{$text};background:{$panelBg};\">" . trim($h) . '</th>';
                }
                $tableHtml .= '</tr></thead><tbody>';
                preg_match_all('/@row\(([^)]+)\)/', $m[2], $rows);
                foreach ($rows[1] as $row) {
                    $cells = explode('|', $row);
                    $tableHtml .= '<tr>';
                    foreach ($cells as $cell) {
                        $tableHtml .= "<td style=\"border-bottom:1px solid {$border};padding:10px 12px;"
                            . "color:{$text};\">" . trim($cell) . '</td>';
                    }
                    $tableHtml .= '</tr>';
                }
                $tableHtml .= '</tbody></table>';
                return $tableHtml;
            },
            $html
        );

        // Divider
        $html = str_replace(
            '@divider',
            "<hr style=\"border:none;border-top:1px solid {$border};margin:24px 0;\">",
            $html
        );

        // Price
        $html = preg_replace(
            '/@price\(([0-9.,]+)\)/',
            "<span style=\"font-size:26px;font-weight:800;color:{$text};\">$$1</span>",
            $html
        );

        // Subcopy
        $html = preg_replace(
            '/@subcopy\((.+?)\)/s',
            "<p style=\"font-size:12px;color:{$textLight};margin-top:28px;padding-top:16px;"
            . "border-top:1px solid {$border};line-height:1.7;\">$1</p>",
            $html
        );

        // Bullet lists
        $html = preg_replace(
            '/^- (.+)$/m',
            "<li style=\"margin:4px 0;color:{$text};\">$1</li>",
            $html
        );
        $html = preg_replace(
            '/(<li[^>]*>.*?<\/li>(\s*<br\s*\/?>)*)+/s',
            '<ul style="padding-left:20px;margin:12px 0;">$0</ul>',
            $html
        );

        // Horizontal rule
        $html = preg_replace(
            '/^---$/m',
            "<hr style=\"border:none;border-top:1px solid {$border};margin:20px 0;\">",
            $html
        );

        return $html;
    }

    private function wrapTemplate(string $content, array $sample, array $theme): string
    {
        $appName = Config::get('app.name', 'Application');
        $appUrl = Config::get('app.url', 'http://localhost');
        $year = date('Y');

        $primary = $this->color($theme, 'primary', '#667eea');
        $secondary = $this->color($theme, 'secondary', '#764ba2');
        $bgColor = $this->color($theme, 'background', '#f6f6f6');
        $cardBg = $this->color($theme, 'card_background', '#ffffff');
        $text = $this->color($theme, 'text', '#333333');
        $textLight = $this->color($theme, 'text_light', '#6c757d');
        $border = $this->color($theme, 'border', '#e9ecef');

        $maxWidth = (int)$this->setting($theme, 'max_width', 600);
        $padding = (int)$this->setting($theme, 'padding', 20);
        $borderRadius = (int)$this->setting($theme, 'border_radius', 8);
        $showFooter = (bool)$this->setting($theme, 'show_footer', true);
        $showSocial = (bool)$this->setting($theme, 'show_social_links', true);
        $headerGradient = $this->setting($theme, 'header_gradient', "linear-gradient(135deg, {$primary} 0%, {$secondary} 100%)");

        $bodyFont = $this->font($theme, 'body');
        $fontFamily = $bodyFont['family'] ?? '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';
        $fontSize = $bodyFont['size'] ?? '15px';

        $headingFont = $this->font($theme, 'heading');
        $headingFamily = $headingFont['family'] ?? $fontFamily;
        $headingWeight = $headingFont['weight'] ?? '700';

        // Logo
        $logo = $theme['assets']['logo'] ?? null;
        $logoHtml = '';
        if (!empty($logo['url'])) {
            $w = !empty($logo['width']) ? ' width="' . (int)$logo['width'] . '"' : '';
            $h = !empty($logo['height']) ? ' height="' . (int)$logo['height'] . '"' : '';
            $alt = htmlspecialchars($logo['alt'] ?? $appName, ENT_QUOTES);
            $logoHtml = '<img src="' . htmlspecialchars($logo['url'], ENT_QUOTES) . "\" alt=\"{$alt}\"{$w}{$h}"
                . ' style="max-height:50px;display:block;margin:0 auto 10px;">';
        }

        $footerHtml = '';
        if ($showFooter) {
            $socialHtml = '';
            if ($showSocial) {
                $socialHtml = <<<HTML
<p style="margin:10px 0 0;">
  <a href="{$appUrl}" style="color:{$primary};text-decoration:none;font-size:12px;margin:0 6px;">Website</a> &middot;
  <a href="{$appUrl}/privacy" style="color:{$primary};text-decoration:none;font-size:12px;margin:0 6px;">Privacy</a> &middot;
  <a href="{$appUrl}/unsubscribe" style="color:{$primary};text-decoration:none;font-size:12px;margin:0 6px;">Unsubscribe</a>
</p>
HTML;
            }
            $footerHtml = <<<HTML
<div style="background:{$cardBg};padding:{$padding}px 30px;text-align:center;border-top:1px solid {$border};">
  <p style="margin:0 0 4px;font-size:12px;color:{$textLight};">&copy; {$year} {$appName}. All rights reserved.</p>
  <p style="margin:0;font-size:12px;color:{$textLight};"><a href="{$appUrl}" style="color:{$primary};text-decoration:none;">{$appUrl}</a></p>
  {$socialHtml}
</div>
HTML;
        }

        $greeting = htmlspecialchars($sample['greeting'] ?? 'Hello,', ENT_QUOTES);
        $subject = htmlspecialchars($sample['subject'] ?? '', ENT_QUOTES);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>{$subject}</title>
</head>
<body style="margin:0;padding:0;background-color:{$bgColor};font-family:{$fontFamily};color:{$text};font-size:{$fontSize};line-height:1.6;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:{$bgColor};padding:24px 0;">
  <tr>
    <td align="center">
      <table width="{$maxWidth}" cellpadding="0" cellspacing="0"
             style="max-width:{$maxWidth}px;width:100%;background-color:{$cardBg};border-radius:{$borderRadius}px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
        <!-- Header -->
        <tr>
          <td style="background:{$headerGradient};padding:32px 30px;text-align:center;">
            {$logoHtml}
            <h1 style="margin:0;color:#ffffff;font-family:{$headingFamily};font-size:24px;font-weight:{$headingWeight};letter-spacing:-0.3px;">{$appName}</h1>
          </td>
        </tr>
        <!-- Body -->
        <tr>
          <td style="padding:36px 40px 28px;">
            <p style="margin:0 0 20px;font-size:16px;color:{$text};">{$greeting}</p>
            {$content}
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td>{$footerHtml}</td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML;
    }
}