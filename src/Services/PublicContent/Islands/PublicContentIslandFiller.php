<?php

namespace App\Services\PublicContent\Islands;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Throwable;

/**
 * Fills reserved island markers in a static shell with rendered fragments.
 * One island failure never blanks the page: that slot gets a defined fallback
 * and every other island still fills.
 */
final class PublicContentIslandFiller
{
    public const string MISSING_FALLBACK = '<!-- island-missing -->';

    public const string FAILED_FALLBACK = '<!-- island-failed -->';

    /**
     * @param array<string, string|callable(): string> $fragments island id => HTML or lazy renderer
     */
    public function fill(string $shellHtml, array $fragments): string
    {
        if ($shellHtml === '' || !str_contains($shellHtml, PublicContentIslandMarker::ELEMENT)) {
            return $shellHtml;
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8"><div id="pc-island-root">' . $shellHtml . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query(PublicContentIslandMarker::xpathQuery());

        if ($nodes === false) {
            return $shellHtml;
        }

        /** @var list<DOMElement> $markers */
        $markers = [];
        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $markers[] = $node;
            }
        }

        foreach ($markers as $marker) {
            $islandId = $marker->getAttribute(PublicContentIslandMarker::ID_ATTRIBUTE);
            $replacementHtml = $this->resolveFragment($islandId, $fragments);
            $this->replaceMarker($document, $marker, $replacementHtml);
        }

        $root = $document->getElementById('pc-island-root');
        if (!$root) {
            return $shellHtml;
        }

        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        return $html;
    }

    /**
     * @param array<string, string|callable(): string> $fragments
     */
    private function resolveFragment(string $islandId, array $fragments): string
    {
        if ($islandId === '' || !array_key_exists($islandId, $fragments)) {
            return self::MISSING_FALLBACK;
        }

        try {
            $fragment = $fragments[$islandId];
            $html = is_callable($fragment) ? (string) $fragment() : (string) $fragment;
        } catch (Throwable) {
            return self::FAILED_FALLBACK;
        }

        return $html;
    }

    private function replaceMarker(DOMDocument $document, DOMElement $marker, string $html): void
    {
        $wrapper = $document->createElement('div');
        $fragmentDoc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $fragmentDoc->loadHTML(
            '<?xml encoding="utf-8"><div id="pc-frag">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $fragRoot = $fragmentDoc->getElementById('pc-frag');
        if ($fragRoot) {
            foreach ($fragRoot->childNodes as $child) {
                $wrapper->appendChild($document->importNode($child, true));
            }
        }

        $parent = $marker->parentNode;
        if ($parent === null) {
            return;
        }

        while ($wrapper->firstChild) {
            $parent->insertBefore($wrapper->firstChild, $marker);
        }
        $parent->removeChild($marker);
    }
}
