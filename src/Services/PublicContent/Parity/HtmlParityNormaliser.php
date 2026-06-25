<?php

namespace App\Services\PublicContent\Parity;

use DOMAttr;
use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

final class HtmlParityNormaliser
{
    /**
     * The parser is deliberately treated as a compatibility surface for parity.
     * Changing this value means browser-style error recovery may change and the
     * parity baselines/tests must be reviewed as a breaking change.
     */
    public const string PARSER_VERSION = 'php-domdocument-libxml-html4-error-recovery-v1';

    /** @var list<HtmlParityPass> */
    private array $passes;

    /** @param list<string> $disabledPasses */
    public function __construct(private readonly array $disabledPasses = [])
    {
        $this->passes = [
            new StripPodsOnlyIslandMarkersPass(),
            new StripCommentsPass(),
            new WhitespaceNormalisationPass(),
            new SortAttributesPass(),
            new SortHreflangAlternatesPass(),
            new UrlNormalisationPass(),
        ];
    }

    public function normalise(string $html): HtmlNormalisationResult
    {
        $document = $this->parse($html);
        $reports = [
            'parse_to_dom' => [
                'name' => 'parse_to_dom',
                'enabled' => true,
                'parser_version' => self::PARSER_VERSION,
                'changed' => true,
            ],
        ];

        foreach ($this->passes as $pass) {
            if (in_array($pass->name(), $this->disabledPasses, true)) {
                $reports[$pass->name()] = [
                    'name' => $pass->name(),
                    'enabled' => false,
                    'changed' => false,
                    'change_count' => 0,
                ];
                continue;
            }

            $before = $this->innerHtml($document);
            $changeCount = $pass->apply($document);
            $after = $this->innerHtml($document);

            $reports[$pass->name()] = [
                'name' => $pass->name(),
                'enabled' => true,
                'changed' => $before !== $after,
                'change_count' => $changeCount,
            ];
        }

        return new HtmlNormalisationResult(
            html: $this->innerHtml($document),
            passReports: $reports,
        );
    }

    private function parse(string $html): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        try {
            $document->loadHTML(
                '<!DOCTYPE html><html><body><div data-parity-root="1">' . $html . '</div></body></html>',
                LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $document;
    }

    private function innerHtml(DOMDocument $document): string
    {
        $root = $this->root($document);
        $html = '';

        foreach ($root->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        return trim($html);
    }

    public static function root(DOMDocument $document): DOMElement
    {
        $xpath = new DOMXPath($document);
        $root = $xpath->query('//*[@data-parity-root="1"]')->item(0);

        if (!$root instanceof DOMElement) {
            throw new \RuntimeException('Parity DOM root could not be resolved.');
        }

        return $root;
    }
}

final readonly class HtmlNormalisationResult
{
    /** @param array<string, array<string, mixed>> $passReports */
    public function __construct(
        public string $html,
        public array $passReports,
    ) {
    }
}

final readonly class HtmlDiffResult
{
    /**
     * @param array<string, mixed> $difference
     * @param array<string, array<string, mixed>> $passReports
     */
    public function __construct(
        public array $difference,
        public array $passReports,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->difference + [
            'passes' => $this->passReports,
        ];
    }
}

interface HtmlParityPass
{
    public function name(): string;

    /** Returns the number of DOM/text changes made by the pass. */
    public function apply(DOMDocument $document): int;
}

final class StripCommentsPass implements HtmlParityPass
{
    public function name(): string
    {
        return 'strip_comments';
    }

    public function apply(DOMDocument $document): int
    {
        $xpath = new DOMXPath($document);
        $comments = [];

        foreach ($xpath->query('//comment()') as $comment) {
            if ($comment instanceof DOMComment) {
                $comments[] = $comment;
            }
        }

        foreach ($comments as $comment) {
            $comment->parentNode?->removeChild($comment);
        }

        return count($comments);
    }
}

final class StripPodsOnlyIslandMarkersPass implements HtmlParityPass
{
    public function name(): string
    {
        return 'strip_pods_only_island_markers';
    }

    public function apply(DOMDocument $document): int
    {
        $xpath = new DOMXPath($document);
        $changes = 0;

        foreach ($xpath->query('//*[@data-pods-only-island-marker or @data-pods-island-marker or @data-parity-pods-only-marker]') as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            foreach (['data-pods-only-island-marker', 'data-pods-island-marker', 'data-parity-pods-only-marker'] as $attribute) {
                if ($node->hasAttribute($attribute)) {
                    $node->removeAttribute($attribute);
                    $changes++;
                }
            }

            if ($node->childNodes->length === 0 && strtolower($node->tagName) === 'span') {
                $node->parentNode?->removeChild($node);
                $changes++;
            }
        }

        return $changes;
    }
}

final class WhitespaceNormalisationPass implements HtmlParityPass
{
    private const array PRESERVE_TAGS = ['pre', 'code', 'textarea'];

    public function name(): string
    {
        return 'normalise_whitespace';
    }

    public function apply(DOMDocument $document): int
    {
        return $this->normaliseNode(HtmlParityNormaliser::root($document), false);
    }

    private function normaliseNode(DOMNode $node, bool $preserveWhitespace): int
    {
        $changes = 0;
        $preserve = $preserveWhitespace
            || ($node instanceof DOMElement && in_array(strtolower($node->tagName), self::PRESERVE_TAGS, true));

        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child->nodeType === XML_TEXT_NODE && !$preserve) {
                $before = $child->nodeValue ?? '';
                $after = preg_replace('/\s+/u', ' ', $before) ?? $before;

                if (trim($after) === '' && $this->isElementBoundaryWhitespace($child)) {
                    $child->parentNode?->removeChild($child);
                    $changes++;
                    continue;
                }

                if ($before !== $after) {
                    $child->nodeValue = $after;
                    $changes++;
                }

                continue;
            }

            $changes += $this->normaliseNode($child, $preserve);
        }

        return $changes;
    }

    private function isElementBoundaryWhitespace(DOMNode $node): bool
    {
        return trim($node->nodeValue ?? '') === ''
            && (!$node->previousSibling || $node->previousSibling->nodeType === XML_ELEMENT_NODE)
            && (!$node->nextSibling || $node->nextSibling->nodeType === XML_ELEMENT_NODE);
    }
}

final class SortAttributesPass implements HtmlParityPass
{
    public function name(): string
    {
        return 'sort_attributes';
    }

    public function apply(DOMDocument $document): int
    {
        $changes = 0;

        foreach ($document->getElementsByTagName('*') as $element) {
            if (!$element instanceof DOMElement || $element->attributes->length < 2) {
                continue;
            }

            $attributes = [];
            foreach ($element->attributes as $attribute) {
                if ($attribute instanceof DOMAttr) {
                    $attributes[$attribute->name] = $attribute->value;
                }
            }

            $sorted = $attributes;
            ksort($sorted, SORT_STRING);

            if ($attributes === $sorted) {
                continue;
            }

            foreach (array_keys($attributes) as $name) {
                $element->removeAttribute($name);
            }

            foreach ($sorted as $name => $value) {
                $element->setAttribute($name, $value);
            }

            $changes++;
        }

        return $changes;
    }
}

final class SortHreflangAlternatesPass implements HtmlParityPass
{
    public function name(): string
    {
        return 'sort_hreflang_alternates';
    }

    public function apply(DOMDocument $document): int
    {
        $changes = 0;
        $parents = [];

        foreach ($document->getElementsByTagName('link') as $link) {
            if (!$link instanceof DOMElement || !$this->isHreflangAlternate($link) || !$link->parentNode) {
                continue;
            }

            $parents[spl_object_id($link->parentNode)] = $link->parentNode;
        }

        foreach ($parents as $parent) {
            $links = [];
            foreach ($parent->childNodes as $child) {
                if ($child instanceof DOMElement && strtolower($child->tagName) === 'link' && $this->isHreflangAlternate($child)) {
                    $links[] = $child;
                }
            }

            if (count($links) < 2) {
                continue;
            }

            $current = array_map(fn (DOMElement $link): string => $this->sortKey($link), $links);
            $sortedLinks = $links;
            usort($sortedLinks, fn (DOMElement $a, DOMElement $b): int => $this->sortKey($a) <=> $this->sortKey($b));
            $sorted = array_map(fn (DOMElement $link): string => $this->sortKey($link), $sortedLinks);

            if ($current === $sorted) {
                continue;
            }

            $anchor = $links[0];
            foreach ($sortedLinks as $link) {
                if ($link === $anchor) {
                    continue;
                }

                $parent->insertBefore($link, $anchor);
            }

            $changes++;
        }

        return $changes;
    }

    private function isHreflangAlternate(DOMElement $link): bool
    {
        return $link->hasAttribute('hreflang')
            && preg_match('/(^|\s)alternate(\s|$)/i', $link->getAttribute('rel')) === 1;
    }

    private function sortKey(DOMElement $link): string
    {
        return strtolower($link->getAttribute('hreflang')) . '|' . $link->getAttribute('href');
    }
}

final class UrlNormalisationPass implements HtmlParityPass
{
    private const array URL_ATTRIBUTES = ['href', 'src', 'action'];

    public function name(): string
    {
        return 'normalise_urls';
    }

    public function apply(DOMDocument $document): int
    {
        $changes = 0;

        foreach ($document->getElementsByTagName('*') as $element) {
            if (!$element instanceof DOMElement) {
                continue;
            }

            foreach (self::URL_ATTRIBUTES as $attribute) {
                if (!$element->hasAttribute($attribute)) {
                    continue;
                }

                $before = $element->getAttribute($attribute);
                $after = $this->normaliseUrl($before);

                if ($before !== $after) {
                    $element->setAttribute($attribute, $after);
                    $changes++;
                }
            }
        }

        return $changes;
    }

    private function normaliseUrl(string $url): string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($url === '' || str_starts_with($url, '#') || preg_match('/^(mailto|tel|javascript|data):/i', $url) === 1) {
            return $url;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }

        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : null;
        $host = isset($parts['host']) ? strtolower($parts['host']) : null;
        $path = $this->normalisePath($parts['path'] ?? '');
        $query = $this->normaliseQuery($parts['query'] ?? null);
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        $assetPath = $this->normalisePublicImagePath($path);
        if ($assetPath !== null) {
            return $assetPath;
        }

        if ($this->isLocalDevelopmentHost($host) && str_starts_with($path, '/uploads/')) {
            return $path;
        }

        $authority = '';
        if ($host !== null) {
            $authority = '//';
            if (isset($parts['user'])) {
                $authority .= $parts['user'];
                if (isset($parts['pass'])) {
                    $authority .= ':' . $parts['pass'];
                }
                $authority .= '@';
            }

            $authority .= $host;
            if (isset($parts['port']) && !$this->isDefaultPort($scheme, (int) $parts['port'])) {
                $authority .= ':' . $parts['port'];
            }
        }

        return ($scheme ? $scheme . ':' : '') . $authority . $path . $query . $fragment;
    }

    private function normalisePublicImagePath(string $path): ?string
    {
        if (str_starts_with($path, '/uploads/')) {
            return $path;
        }

        if (!str_starts_with($path, '/public/images/')) {
            return null;
        }

        $signed = substr($path, strlen('/public/images/'));
        $token = explode('.', $signed, 2)[0] ?? '';

        if ($token === '') {
            return null;
        }

        $decoded = base64_decode(strtr($token, '-_', '+/'), true);
        if (!is_string($decoded) || $decoded === '') {
            return null;
        }

        if (str_starts_with($decoded, 'v1:/uploads/')) {
            return substr($decoded, 3);
        }

        if (str_starts_with($decoded, '/uploads/')) {
            return $decoded;
        }

        return null;
    }

    private function isLocalDevelopmentHost(?string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1', 'host.docker.internal'], true);
    }

    private function normalisePath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        $isAbsolute = str_starts_with($path, '/');
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        $normalised = implode('/', $segments);

        return ($isAbsolute ? '/' : '') . $normalised;
    }

    private function normaliseQuery(?string $query): string
    {
        if ($query === null || $query === '') {
            return '';
        }

        parse_str($query, $params);
        ksort($params, SORT_STRING);
        $normalised = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        return $normalised === '' ? '' : '?' . $normalised;
    }

    private function isDefaultPort(?string $scheme, int $port): bool
    {
        return ($scheme === 'http' && $port === 80)
            || ($scheme === 'https' && $port === 443);
    }
}
