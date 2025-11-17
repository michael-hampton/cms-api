<?php

namespace App\Framework\Mail;

use App\Framework\Support\Config;
use App\Framework\Support\View;

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

    public function __construct()
    {
        $config = Config::get('mail');
        $this->from = $config['from']['address'] ?? '';
        $this->fromName = $config['from']['name'] ?? '';
    }

    abstract public function build(): self;

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
        $this->attachments[] = array_merge([
            'data' => $data,
            'name' => $name
        ], $options);
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

    public function render(): string
    {
        if ($this->markdown) {
            return $this->renderMarkdown();
        }

        if ($this->view) {
            return View::render($this->view, $this->viewData);
        }

        return '';
    }

    protected function renderMarkdown(): string
    {
        // Check if markdown is a view path or raw content
        if ($this->isViewPath($this->markdown)) {
            // Render the view file to get markdown content
            $markdownContent = View::render($this->markdown, $this->viewData);
        } else {
            // Treat as raw markdown content (useful for testing)
            $markdownContent = $this->processRawMarkdown($this->markdown);
        }

        // Convert markdown to HTML
        $html = $this->convertMarkdownToHtml($markdownContent);

        return $html;
    }

    /**
     * Check if the markdown string is a view path or raw content
     */
    protected function isViewPath(string $markdown): bool
    {
        // If it contains path separators or dots (view notation), it's likely a path
        if (strpos($markdown, '/') !== false || strpos($markdown, '.') !== false) {
            // Check if the view actually exists
            if (View::exists($markdown)) {
                return true;
            }
        }

        // If it starts with markdown syntax, treat as raw content
        if (preg_match('/^(#|\*\*|\*|@|>)/', trim($markdown))) {
            return false;
        }

        return false;
    }

    /**
     * Process raw markdown content with view data
     */
    protected function processRawMarkdown(string $markdown): string
    {
        // Extract variables for use in evaluation
        extract($this->viewData, EXTR_SKIP);

        // Process simple {{ }} syntax for variables
        $markdown = preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/', function ($matches) use ($markdown) {
            $expression = trim($matches[1]);
            try {
                // Try to evaluate the expression with current scope
                $result = $this->evaluateExpression($expression, $this->viewData);
                return htmlspecialchars($result ?? '');
            } catch (\Exception $e) {
                return $matches[0]; // Return original if evaluation fails
            }
        }, $markdown);

        // Process {!! !!} syntax for raw output
        $markdown = preg_replace_callback('/\{\!\!\s*(.+?)\s*\!\!\}/', function ($matches) use ($markdown) {
            $expression = trim($matches[1]);
            try {
                $result = $this->evaluateExpression($expression, $this->viewData);
                return $result ?? '';
            } catch (\Exception $e) {
                return $matches[0];
            }
        }, $markdown);

        return $markdown;
    }

    /**
     * Safely evaluate a PHP expression with given data
     */
    protected function evaluateExpression(string $expression, array $data): mixed
    {
        extract($data, EXTR_SKIP);

        // Security: Only allow safe expressions
        // This is a simplified version - enhance as needed
        try {
            return eval('return ' . $expression . ';');
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function convertMarkdownToHtml(string $markdown): string
    {
        $html = $markdown;

        // Headers
        $html = preg_replace('/^# (.+)$/m', '<h1 style="color:#2c3e50;font-size:24px;margin:20px 0 10px;">$1</h1>', $html);
        $html = preg_replace('/^## (.+)$/m', '<h2 style="color:#34495e;font-size:20px;margin:18px 0 8px;">$1</h2>', $html);
        $html = preg_replace('/^### (.+)$/m', '<h3 style="color:#7f8c8d;font-size:16px;margin:16px 0 6px;">$1</h3>', $html);

        // Bold
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);

        // Italic (avoid matching ** for bold)
        $html = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/', '<em>$1</em>', $html);

        // Strikethrough
        $html = preg_replace('/~~(.+?)~~/', '<del style="text-decoration:line-through;">$1</del>', $html);

        // Links
        $html = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2" style="color:#3498db;text-decoration:none;">$1</a>', $html);

        // PRIMARY BUTTON - @button(text, url)
        $html = preg_replace('/@button\(([^,]+),\s*([^)]+)\)/',
            '<table width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;"><tr><td align="center"><a href="$2" style="display:inline-block;padding:12px 30px;background:#4CAF50;color:white;text-decoration:none;border-radius:5px;font-weight:bold;font-size:16px;">$1</a></td></tr></table>',
            $html);

        // SECONDARY BUTTON - @buttonSecondary(text, url)
        $html = preg_replace('/@buttonSecondary\(([^,]+),\s*([^)]+)\)/',
            '<table width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;"><tr><td align="center"><a href="$2" style="display:inline-block;padding:10px 24px;background:#6c757d;color:white;text-decoration:none;border-radius:5px;font-size:14px;">$1</a></td></tr></table>',
            $html);

        // PANEL/CALLOUT - @panel(content)
        $html = preg_replace('/@panel\((.+?)\)/',
            '<div style="background:#f8f9fa;border-left:4px solid #4CAF50;padding:15px;margin:15px 0;border-radius:4px;">$1</div>',
            $html);

        // PROMOTION BOX - @promotion(content)
        $html = preg_replace('/@promotion\((.+?)\)/',
            '<div style="background:#fff3cd;border:2px solid #ffc107;padding:15px;margin:15px 0;border-radius:6px;text-align:center;"><strong style="color:#856404;font-size:16px;">$1</strong></div>',
            $html);

        // TABLE - @table(header1|header2|header3) ... @row(cell1|cell2|cell3) ... @endtable
        $html = preg_replace_callback('/@table\(([^)]+)\)(.*?)@endtable/s', function ($matches) {
            $headers = explode('|', $matches[1]);
            $rows = $matches[2];

            $tableHtml = '<table style="width:100%;border-collapse:collapse;margin:20px 0;border:1px solid #ddd;">';
            $tableHtml .= '<thead><tr>';
            foreach ($headers as $header) {
                $tableHtml .= '<th style="border:1px solid #ddd;padding:12px;background:#f8f9fa;text-align:left;font-weight:bold;">' . trim($header) . '</th>';
            }
            $tableHtml .= '</tr></thead><tbody>';

            preg_match_all('/@row\(([^)]+)\)/', $rows, $rowMatches);
            foreach ($rowMatches[1] as $row) {
                $cells = explode('|', $row);
                $tableHtml .= '<tr>';
                foreach ($cells as $cell) {
                    $tableHtml .= '<td style="border:1px solid #ddd;padding:12px;">' . trim($cell) . '</td>';
                }
                $tableHtml .= '</tr>';
            }

            $tableHtml .= '</tbody></table>';
            return $tableHtml;
        }, $html);

        // DIVIDER - @divider
        $html = preg_replace('/@divider/',
            '<hr style="border:none;border-top:2px solid #e9ecef;margin:25px 0;">',
            $html);

        // PRICE - @price(amount)
        $html = preg_replace('/@price\(([0-9.]+)\)/',
            '<span style="font-size:24px;font-weight:bold;color:#2c3e50;">$$$1</span>',
            $html);

        // SUBCOPY - @subcopy(text)
        $html = preg_replace('/@subcopy\((.+?)\)/',
            '<p style="font-size:12px;color:#6c757d;margin-top:30px;padding-top:20px;border-top:1px solid #dee2e6;line-height:1.6;">$1</p>',
            $html);

        // Horizontal rule ---
        $html = preg_replace('/^---$/m', '<hr style="border:none;border-top:1px solid #dee2e6;margin:20px 0;">', $html);

        // Line breaks (but not inside HTML tags we just created)
        $html = preg_replace('/\n(?![^<]*>)/', '<br>', $html);

        // Wrap in email template
        return $this->wrapInEmailTemplate($html);
    }

    protected function wrapInEmailTemplate(string $content): string
    {
        $appName = Config::get('app.name', 'Application');
        $appUrl = Config::get('app.url', 'http://localhost');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{$this->subject}</title>
    <style>
        body { 
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f6f6f6;
        }
        .email-wrapper {
            width: 100%;
            background-color: #f6f6f6;
            padding: 20px 0;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px 20px;
            text-align: center;
        }
        .email-header h1 {
            margin: 0;
            color: #ffffff;
            font-size: 24px;
            font-weight: 600;
        }
        .email-body {
            padding: 40px 30px;
        }
        .email-footer {
            background-color: #f8f9fa;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }
        .email-footer p {
            margin: 5px 0;
            font-size: 12px;
            color: #6c757d;
        }
        .email-footer a {
            color: #667eea;
            text-decoration: none;
        }
        p {
            margin: 0 0 15px;
        }
        @media only screen and (max-width: 600px) {
            .email-body {
                padding: 20px 15px !important;
            }
            .email-footer {
                padding: 20px 15px !important;
            }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-container">
            <div class="email-header">
                <h1>{$appName}</h1>
            </div>
            <div class="email-body">
                {$content}
            </div>
            <div class="email-footer">
                <p><strong>{$this->fromName}</strong></p>
                <p>© {date('Y')} {$appName}. All rights reserved.</p>
                <p><a href="{$appUrl}">Visit our website</a></p>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }
}