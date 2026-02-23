<?php

namespace App\Services\Newsletter\Branding;


use App\Enums\Newsletters\CssPropertyAllowList;

/**
 * Sanitizes custom CSS to only allow safe, allow-listed properties
 * and scopes all rules under a newsletter-specific wrapper ID.
 *
 * Blocked: animations, scripts, global selectors, position tricks.
 */
class CssSanitizer
{
    private const BLOCKED_PATTERNS = [
        '/animation\s*:/i',
        '/transition\s*:/i',
        '/javascript\s*:/i',
        '/expression\s*\(/i',
        '/behavior\s*:/i',
        '/-moz-binding\s*:/i',
        '/url\s*\(\s*["\']?\s*javascript/i',
        '/position\s*:\s*fixed/i',
        '/position\s*:\s*absolute/i',
        '/@import/i',
        '/@keyframes/i',
        '/\\\\/i',
    ];

    /**
     * Sanitize and scope CSS to the newsletter wrapper.
     *
     * @param string $css
     * @param int $newsletterId
     * @return string
     */
    public function sanitizeAndScope(string $css, int $newsletterId): string
    {
        $scopeSelector = "#newsletter-{$newsletterId}";
        $css = $this->stripDangerousContent($css);
        $rules = $this->parseRules($css);
        $sanitized = $this->filterDeclarations($rules);

        return $this->scopeRules($sanitized, $scopeSelector);
    }

    /**
     * Sanitize without scoping — used for branding preview where no ID context exists yet.
     */
    public function sanitize(string $css): string
    {
        $css = $this->stripDangerousContent($css);
        $rules = $this->parseRules($css);
        return $this->filterDeclarations($rules);
    }

    private function stripDangerousContent(string $css): string
    {
        // Strip HTML tags
        $css = strip_tags($css);

        // Strip comments (can hide malicious content)
        $css = preg_replace('/\/\*.*?\*\//s', '', $css);

        foreach (self::BLOCKED_PATTERNS as $pattern) {
            $css = preg_replace($pattern, '', $css);
        }

        return $css;
    }

    /**
     * Parse CSS text into an array of [selector => declarationBlock] pairs.
     *
     * @return array<array{selector: string, declarations: string}>
     */
    private function parseRules(string $css): array
    {
        $rules = [];

        // Match selector { declarations }
        preg_match_all('/([^{]+)\{([^}]*)\}/s', $css, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $selector = trim($match[1]);
            $declarations = trim($match[2]);

            // Block global element selectors (e.g. body, html, *)
            if ($this->isGlobalSelector($selector)) {
                continue;
            }

            $rules[] = [
                'selector' => $selector,
                'declarations' => $declarations,
            ];
        }

        return $rules;
    }

    private function isGlobalSelector(string $selector): bool
    {
        $blocked = ['body', 'html', '*', 'head', 'script', 'style', 'link'];

        $lowerSelector = strtolower(trim($selector));

        foreach ($blocked as $global) {
            if ($lowerSelector === $global) {
                return true;
            }
        }

        return false;
    }

    /**
     * Filter each rule's declarations to only allow-listed CSS properties.
     */
    private function filterDeclarations(array $rules): string
    {
        $output = '';

        foreach ($rules as $rule) {
            $filtered = $this->filterDeclarationBlock($rule['declarations']);

            if (!empty($filtered)) {
                $output .= "{$rule['selector']} {\n{$filtered}\n}\n";
            }
        }

        return $output;
    }

    private function filterDeclarationBlock(string $declarations): string
    {
        $lines = explode(';', $declarations);
        $filtered = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            [$property] = explode(':', $line, 2) + ['', ''];
            $property = strtolower(trim($property));

            if (CssPropertyAllowList::isAllowed($property)) {
                $filtered[] = "  {$line};";
            }
        }

        return implode("\n", $filtered);
    }

    private function scopeRules(string $css, string $scopeSelector): string
    {
        if (empty(trim($css))) {
            return '';
        }

        $scoped = '';
        preg_match_all('/([^{]+)\{([^}]*)\}/s', $css, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $selector = trim($match[1]);
            $declarations = trim($match[2]);

            $scoped .= "{$scopeSelector} {$selector} {\n{$declarations}\n}\n";
        }

        return $scoped;
    }
}