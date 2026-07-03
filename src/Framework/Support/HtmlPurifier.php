<?php

namespace App\Framework\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

class HtmlPurifier
{
    private array $allowedTags = [
        'p',
        'b',
        'strong',
        'i',
        'em',
        'u',
        'ul',
        'ol',
        'li',
        'br',
        'blockquote',
        'code',
        'pre',
        'a',
    ];

    private array $allowedAttributes = [
        'a' => ['href', 'title', 'target', 'rel'],
    ];

    private array $voidTags = [
        'script',
        'style',
        'iframe',
        'object',
        'embed',
        'svg',
        'math',
    ];

    public function purify(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        libxml_use_internal_errors(true);

        $dom = new DOMDocument('1.0', 'UTF-8');

        // UTF-8 safe loading (no mb_convert_encoding)
        $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        if ($dom->documentElement) {
            $this->cleanNode($dom->documentElement);
        }

        return $dom->saveHTML($dom->documentElement) ?? '';
    }

    private function cleanNode(DOMNode $node): void
    {
        if ($node instanceof DOMElement) {

            $tag = strtolower($node->tagName);

            // Hard remove dangerous tags completely
            if (in_array($tag, $this->voidTags, true)) {
                $this->removeNode($node);
                return;
            }

            // Remove unknown tags (unwrap content)
            if (!in_array($tag, $this->allowedTags, true)) {
                $this->unwrapNode($node);
                return;
            }

            // Clean attributes
            foreach (iterator_to_array($node->attributes ?? []) as $attribute) {

                $name = strtolower($attribute->nodeName);
                $value = $attribute->nodeValue;

                // Remove event handlers (onclick etc)
                if (str_starts_with($name, 'on')) {
                    $node->removeAttribute($name);
                    continue;
                }

                // Only allow whitelisted attributes
                if (
                    !isset($this->allowedAttributes[$tag]) ||
                    !in_array($name, $this->allowedAttributes[$tag], true)
                ) {
                    $node->removeAttribute($name);
                    continue;
                }

                // Block javascript: and data: URLs
                if ($name === 'href') {
                    if (preg_match('/^\s*(javascript:|data:)/i', $value)) {
                        $node->removeAttribute($name);
                        continue;
                    }

                    // Force safe link behaviour
                    $node->setAttribute('rel', 'noopener noreferrer');
                    if (!$node->hasAttribute('target')) {
                        $node->setAttribute('target', '_blank');
                    }
                }
            }
        }

        foreach (iterator_to_array($node->childNodes ?? []) as $child) {
            $this->cleanNode($child);
        }
    }

    private function unwrapNode(DOMNode $node): void
    {
        $parent = $node->parentNode;

        if (!$parent) {
            return;
        }

        while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
        }

        $parent->removeChild($node);
    }

    private function removeNode(DOMNode $node): void
    {
        $parent = $node->parentNode;

        if ($parent) {
            $parent->removeChild($node);
        }
    }
}