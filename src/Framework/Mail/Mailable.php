<?php

namespace App\Framework\Mail;

use App\Framework\Container;
use App\Framework\Support\Config;
use App\Framework\Support\SiteContext;
use App\Framework\Support\View;
use App\Models\NewsletterBrandingConfiguration;
use App\Repositories\Newsletters\NewsletterBrandingRepository;
use App\Services\Newsletter\EmailTemplateService;

abstract class Mailable
{
    public string $subject = '';
    public string $from;
    public string $fromName;
    public array $to = [];
    public array $cc = [];
    public array $bcc = [];
    public array $replyTo = [];
    public array $attachments = [];
    public array $viewData = [];
    public ?string $view = null;
    public ?string $markdown = null;
    public ?string $textView = null;

    /** Resolved branding configuration — populated lazily by loadTheme(). */
    public ?NewsletterBrandingConfiguration $theme = null;

    public ?string $themeSlug = null;
    public ?int $themeId = null;
    public ?int $templateId = null;
    public ?string $templateSlug = null;

    protected ?int $siteId = null;

    public function __construct()
    {
        $config = Config::get('mail');
        $this->from = $config['from']['address'] ?? '';
        $this->fromName = $config['from']['name'] ?? '';
        $this->siteId = SiteContext::getId();
    }

    abstract public function build(): self;

    // =========================================================================
    // Fluent builder methods
    // =========================================================================

    public function theme(string $slug): self
    {
        $this->themeSlug = $slug;
        $this->theme = null; // Force reload on next render
        return $this;
    }

    public function subject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function from(string $address, ?string $name = null): self
    {
        $this->from = $address;
        if ($name) {
            $this->fromName = $name;
        }
        return $this;
    }

    public function to(string|array $address, ?string $name = null): self
    {
        if (is_array($address)) {
            $this->to = array_merge($this->to, $address);
        } else {
            $this->to[] = ['address' => $address, 'name' => $name];
        }
        return $this;
    }

    public function cc(string|array $address, ?string $name = null): self
    {
        if (is_array($address)) {
            $this->cc = array_merge($this->cc, $address);
        } else {
            $this->cc[] = ['address' => $address, 'name' => $name];
        }
        return $this;
    }

    public function bcc(string|array $address, ?string $name = null): self
    {
        if (is_array($address)) {
            $this->bcc = array_merge($this->bcc, $address);
        } else {
            $this->bcc[] = ['address' => $address, 'name' => $name];
        }
        return $this;
    }

    public function replyTo(string $address, ?string $name = null): self
    {
        $this->replyTo[] = ['address' => $address, 'name' => $name];
        return $this;
    }

    public function attach(string $path, array $options = []): self
    {
        $this->attachments[] = array_merge(['path' => $path], $options);
        return $this;
    }

    public function attachData(string $data, string $name, array $options = []): self
    {
        $this->attachments[] = array_merge(['data' => $data, 'name' => $name], $options);
        return $this;
    }

    public function view(string $view, array $data = []): self
    {
        $this->view = $view;
        $this->viewData = array_merge($this->viewData, $data);
        return $this;
    }

    public function markdown(string $view, array $data = []): self
    {
        $this->markdown = $view;
        $this->viewData = array_merge($this->viewData, $data);
        return $this;
    }

    public function text(string $textView): self
    {
        $this->textView = $textView;
        return $this;
    }

    public function with(string|array $key, mixed $value = null): self
    {
        if (is_array($key)) {
            $this->viewData = array_merge($this->viewData, $key);
        } else {
            $this->viewData[$key] = $value;
        }
        return $this;
    }

    public function template(int|string $template, array $data = []): self
    {
        if (is_int($template) || ctype_digit((string)$template)) {
            $this->templateId = (int)$template;
            $this->templateSlug = null;
        } else {
            $this->templateSlug = (string)$template;
            $this->templateId = null;
        }

        $this->viewData = array_merge($this->viewData, $data);

        return $this;
    }

    // =========================================================================
    // Rendering
    // =========================================================================

    public function render(): string
    {
        $this->loadTheme();

        if ($this->templateId !== null || $this->templateSlug !== null) {
            return $this->renderTemplate();
        }

        if ($this->markdown) {
            return $this->renderMarkdown();
        }

        if ($this->view) {
            return View::render($this->view, $this->viewData);
        }

        return '';
    }

    // =========================================================================
    // Theme loading
    // =========================================================================

    /**
     * Resolve the branding configuration to use for this mail.
     *
     * Priority:
     *   1. Already loaded ($this->theme is set)
     *   2. Explicit themeId
     *   3. Explicit themeSlug
     *   4. Site default (email_template type, active, is_default)
     *
     * All lookups are scoped to email_template type records so newsletter-only
     * branding configs are never accidentally used for transactional mail.
     */
    protected function loadTheme(): void
    {
        if ($this->theme !== null) {
            return;
        }

        /** @var NewsletterBrandingRepository $repo */
        $repo = Container::getInstance()->make(NewsletterBrandingRepository::class);

        if (!empty($this->themeId)) {
            $candidate = NewsletterBrandingConfiguration::emailTemplates()
                ->bySite($this->siteId)
                ->active()
                ->where('id', $this->themeId)
                ->first();

            if ($candidate) {
                $this->theme = $candidate;
                return;
            }
        }

        if ($this->themeSlug !== null) {
            $candidate = $repo->findBySlug($this->themeSlug, $this->siteId);

            if ($candidate && $candidate->is_active) {
                $this->theme = $candidate;
                return;
            }
        }

        // Fall back to the site default
        $this->theme = $repo->getDefaultForSite($this->siteId);
    }

    // =========================================================================
    // Template rendering
    // =========================================================================

    protected function renderTemplate(): string
    {
        /** @var EmailTemplateService $service */
        $service = Container::getInstance()->make(EmailTemplateService::class);

        $template = $this->templateId !== null
            ? $service->getById($this->templateId)
            : $service->getBySlug((string)$this->templateSlug, (int)$this->siteId);

        if ($template === null) {
            return '';
        }

        // If the template specifies its own theme, load it before rendering
        if (!empty($template->theme_id)) {
            $this->themeId = $template->theme_id;
            $this->theme = null;
            $this->loadTheme();
        }

        return $service->render($template->id, $this->viewData, $this->theme);
    }

    // =========================================================================
    // Markdown rendering
    // =========================================================================

    protected function renderMarkdown(): string
    {
        $markdownContent = $this->isViewPath($this->markdown)
            ? View::render($this->markdown, $this->viewData)
            : $this->processRawMarkdown($this->markdown);

        return $this->convertMarkdownToHtml($markdownContent);
    }

    protected function isViewPath(string $markdown): bool
    {
        if (
            (str_contains($markdown, '/') || str_contains($markdown, '.'))
            && View::exists($markdown)
        ) {
            return true;
        }

        return false;
    }

    protected function processRawMarkdown(string $markdown): string
    {
        return preg_replace_callback(
            '/\{\{\s*(.+?)\s*\}\}/',
            function (array $matches): string {
                try {
                    return $this->evaluateExpression(trim($matches[1]), $this->viewData);
                } catch (\Throwable) {
                    return $matches[0];
                }
            },
            $markdown
        );
    }

    protected function evaluateExpression(string $expression, array $data): string
    {
        extract($data, EXTR_SKIP);
        try {
            return (string)eval("return {$expression};");
        } catch (\Throwable) {
            return '';
        }
    }

    // =========================================================================
    // Markdown → HTML conversion
    // =========================================================================

    /**
     * Converts the custom markdown dialect used by this framework into HTML.
     * Theme colours and settings are read from the resolved
     * NewsletterBrandingConfiguration (theme_json).
     */
    protected function convertMarkdownToHtml(string $markdown): string
    {
        $primary = $this->themeColor('primary', '#667eea');
        $secondary = $this->themeColor('secondary', '#764ba2');
        $success = $this->themeColor('success', '#4CAF50');
        $warning = $this->themeColor('warning', '#ffc107');
        $text = $this->themeColor('text', '#2c3e50');
        $textLight = $this->themeColor('text_light', '#7f8c8d');
        $border = $this->themeColor('border', '#ddd');
        $link = $this->themeColor('link', '#3498db');
        $panelBg = $this->themeColor('card_background', '#f8f9fa');
        $borderRadius = (int)$this->themeSetting('border_radius', 6);

        $html = nl2br(htmlspecialchars($markdown, ENT_QUOTES, 'UTF-8', false));

        // ── Headings ──────────────────────────────────────────────────────────
        $html = preg_replace('/^# (.+)$/m', "<h1 style=\"color:{$text};font-size:24px;margin:20px 0 10px;font-weight:700;\">\$1</h1>", $html);
        $html = preg_replace('/^## (.+)$/m', "<h2 style=\"color:{$text};font-size:20px;margin:18px 0 8px;font-weight:600;\">\$1</h2>", $html);
        $html = preg_replace('/^### (.+)$/m', "<h3 style=\"color:{$textLight};font-size:16px;margin:16px 0 6px;\">\$1</h3>", $html);

        // ── Inline formatting ─────────────────────────────────────────────────
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/', '<em>$1</em>', $html);
        $html = preg_replace('/~~(.+?)~~/', '<del style="text-decoration:line-through;">$1</del>', $html);

        // ── Links ─────────────────────────────────────────────────────────────
        $html = preg_replace(
            '/\[(.+?)\]\((.+?)\)/',
            "<a href=\"\$2\" style=\"color:{$link};text-decoration:none;\">\$1</a>",
            $html
        );

        // ── Primary button ────────────────────────────────────────────────────
        $html = preg_replace(
            '/@button\(([^,]+),\s*([^)]+)\)/',
            "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"margin:20px 0;\"><tr><td align=\"center\">"
            . "<a href=\"\$2\" style=\"display:inline-block;padding:12px 30px;background:{$success};"
            . "color:white;text-decoration:none;border-radius:{$borderRadius}px;font-weight:bold;font-size:16px;\">\$1</a>"
            . "</td></tr></table>",
            $html
        );

        // ── Secondary button ──────────────────────────────────────────────────
        $html = preg_replace(
            '/@buttonSecondary\(([^,]+),\s*([^)]+)\)/',
            "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"margin:20px 0;\"><tr><td align=\"center\">"
            . "<a href=\"\$2\" style=\"display:inline-block;padding:10px 24px;background:{$secondary};"
            . "color:white;text-decoration:none;border-radius:{$borderRadius}px;font-size:14px;\">\$1</a>"
            . "</td></tr></table>",
            $html
        );

        // ── Panel ─────────────────────────────────────────────────────────────
        $html = preg_replace(
            '/@panel\((.+?)\)/s',
            "<div style=\"background:{$panelBg};border-left:4px solid {$primary};padding:15px;"
            . "margin:15px 0;border-radius:0 {$borderRadius}px {$borderRadius}px 0;\">\$1</div>",
            $html
        );

        // ── Promotion ─────────────────────────────────────────────────────────
        $html = preg_replace(
            '/@promotion\((.+?)\)/s',
            "<div style=\"background:#fff3cd;border:2px solid {$warning};padding:15px;margin:15px 0;"
            . "border-radius:{$borderRadius}px;text-align:center;\"><strong style=\"color:#856404;font-size:16px;\">\$1</strong></div>",
            $html
        );

        // ── Table ─────────────────────────────────────────────────────────────
        $html = preg_replace_callback(
            '/@table\(([^)]+)\)(.*?)@endtable/s',
            function (array $matches) use ($border, $panelBg): string {
                $headers = explode('|', $matches[1]);
                $tableHtml = "<table style=\"width:100%;border-collapse:collapse;margin:20px 0;border:1px solid {$border};\">";
                $tableHtml .= '<thead><tr>';
                foreach ($headers as $header) {
                    $tableHtml .= "<th style=\"border:1px solid {$border};padding:12px;"
                        . "background:{$panelBg};text-align:left;font-weight:bold;\">" . trim($header) . '</th>';
                }
                $tableHtml .= '</tr></thead><tbody>';
                preg_match_all('/@row\(([^)]+)\)/', $matches[2], $rowMatches);
                foreach ($rowMatches[1] as $row) {
                    $cells = explode('|', $row);
                    $tableHtml .= '<tr>';
                    foreach ($cells as $cell) {
                        $tableHtml .= "<td style=\"border:1px solid {$border};padding:12px;\">" . trim($cell) . '</td>';
                    }
                    $tableHtml .= '</tr>';
                }
                $tableHtml .= '</tbody></table>';
                return $tableHtml;
            },
            $html
        );

        // ── Divider ───────────────────────────────────────────────────────────
        $html = str_replace(
            '@divider',
            "<hr style=\"border:none;border-top:2px solid {$border};margin:25px 0;\">",
            $html
        );

        // ── Price ─────────────────────────────────────────────────────────────
        $html = preg_replace(
            '/@price\(([0-9.,]+)\)/',
            "<span style=\"font-size:24px;font-weight:bold;color:{$text};\">$$1</span>",
            $html
        );

        // ── Subcopy ───────────────────────────────────────────────────────────
        $html = preg_replace(
            '/@subcopy\((.+?)\)/s',
            "<p style=\"font-size:12px;color:{$textLight};margin-top:30px;padding-top:20px;"
            . "border-top:1px solid {$border};line-height:1.6;\">\$1</p>",
            $html
        );

        // ── Horizontal rule ───────────────────────────────────────────────────
        $html = preg_replace(
            '/^---$/m',
            "<hr style=\"border:none;border-top:1px solid {$border};margin:20px 0;\">",
            $html
        );

        // ── Line breaks ───────────────────────────────────────────────────────
        $html = preg_replace('/\n(?![^<]*>)/', '<br>', $html);

        return $this->wrapInEmailTemplate($html);
    }

    // =========================================================================
    // Email chrome wrapper
    // =========================================================================

    protected function wrapInEmailTemplate(string $content): string
    {
        $appName = Config::get('app.name', 'Application');
        $appUrl = Config::get('app.url', 'http://localhost');
        $year = date('Y');

        $primary = $this->themeColor('primary', '#667eea');
        $secondary = $this->themeColor('secondary', '#764ba2');
        $bgColor = $this->themeColor('background', '#f6f6f6');
        $cardBg = $this->themeColor('card_background', '#ffffff');
        $text = $this->themeColor('text', '#333333');
        $textLight = $this->themeColor('text_light', '#6c757d');
        $border = $this->themeColor('border', '#e9ecef');

        $bodyFont = $this->theme?->getFont('body');
        $fontFamily = $bodyFont['family'] ?? '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';

        $maxWidth = (int)$this->themeSetting('max_width', 600);
        $borderRadius = (int)$this->themeSetting('border_radius', 8);
        $headerGradient = $this->themeSetting(
            'header_gradient',
            "linear-gradient(135deg, {$primary} 0%, {$secondary} 100%)"
        );
        $showFooter = (bool)$this->themeSetting('show_footer', true);

        // ── Logo ──────────────────────────────────────────────────────────────
        $logo = $this->theme?->getAsset('logo');
        $logoHtml = '';
        if (!empty($logo['url'])) {
            $alt = htmlspecialchars($logo['alt'] ?? $appName, ENT_QUOTES);
            $logoHtml = '<img src="' . htmlspecialchars($logo['url'], ENT_QUOTES) . "\" alt=\"{$alt}\""
                . ' style="max-height:50px;margin-bottom:10px;">';
        }

        // ── Footer ────────────────────────────────────────────────────────────
        $footerHtml = '';
        if ($showFooter) {
            $fromName = htmlspecialchars($this->fromName, ENT_QUOTES);
            $footerHtml = <<<HTML
<div style="background-color:{$cardBg};padding:30px;text-align:center;border-top:1px solid {$border};">
    <p style="margin:5px 0;font-size:12px;color:{$textLight};"><strong>{$fromName}</strong></p>
    <p style="margin:5px 0;font-size:12px;color:{$textLight};">&copy; {$year} {$appName}. All rights reserved.</p>
    <p style="margin:5px 0;font-size:12px;"><a href="{$appUrl}" style="color:{$primary};text-decoration:none;">Visit our website</a></p>
</div>
HTML;
        }

        $subject = htmlspecialchars($this->subject, ENT_QUOTES);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{$subject}</title>
    <style>
        body { margin:0;padding:0;font-family:{$fontFamily};line-height:1.6;color:{$text};background-color:{$bgColor}; }
        .email-wrapper { width:100%;background-color:{$bgColor};padding:20px 0; }
        .email-container { max-width:{$maxWidth}px;margin:0 auto;background-color:{$cardBg};border-radius:{$borderRadius}px;overflow:hidden;box-shadow:0 2px 4px rgba(0,0,0,0.1); }
        .email-header { background:{$headerGradient};padding:30px 20px;text-align:center; }
        .email-header h1 { margin:0;color:#ffffff;font-size:24px;font-weight:600; }
        .email-body { padding:40px 30px; }
        p { margin:0 0 15px; }
        @media only screen and (max-width:600px) {
            .email-body { padding:20px 15px !important; }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-container">
            <div class="email-header">
                {$logoHtml}
                <h1>{$appName}</h1>
            </div>
            <div class="email-body">
                {$content}
            </div>
            {$footerHtml}
        </div>
    </div>
</body>
</html>
HTML;
    }

    // =========================================================================
    // Theme accessor helpers
    // =========================================================================

    /**
     * Read a colour from theme_json['colors'], falling back to $default.
     */
    private function themeColor(string $key, string $default): string
    {
        return $this->theme?->getColor($key, $default) ?? $default;
    }

    /**
     * Read a setting from theme_json['settings'], falling back to $default.
     */
    private function themeSetting(string $key, mixed $default): mixed
    {
        return $this->theme?->getSetting($key, $default) ?? $default;
    }
}