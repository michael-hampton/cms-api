<?php

namespace App\Services\PublicContent\Slugs;

use App\DTO\PublicContent\ContentRegion;
use App\DTO\PublicContent\PublicContentComponent;
use App\Models\Page;
use App\Repositories\PublicContent\PublicContentPageRepository;

/**
 * Single post-render pass that localises internal links on finished HTML.
 *
 * On a regional page, site-internal hrefs are rewritten to the region-prefixed
 * destination (matching PageGridRenderer::buildPageUrl). Flat slug paths are
 * also canonicalised when a published page is found.
 */
final class PublicContentLinkRewriter
{
    /** @var list<string> */
    private const array RESERVED_SEGMENTS = ['shop', 'login', 'register', 'account'];

    /** @var array<string, array<string, ?Page>> */
    private array $pagesBySlug = [];

    public function __construct(
        private readonly PublicContentPathResolver $paths,
        private readonly PublicContentPageRepository $pages,
    ) {
    }

    /**
     * @param list<ContentRegion> $regions
     * @return list<ContentRegion>
     */
    public function rewriteContentRegions(
        array $regions,
        int $siteId,
        string $siteSlug,
        ?string $territorySlug = null,
    ): array {
        foreach ($regions as $index => $region) {
            if (!$region instanceof ContentRegion) {
                continue;
            }

            $html = $this->rewriteHtml($region->renderedHtml, $siteId, $siteSlug, $territorySlug);
            if ($html === $region->renderedHtml) {
                continue;
            }

            $regions[$index] = new ContentRegion(
                name: $region->name,
                blocks: $region->blocks,
                renderedHtml: $html,
            );
        }

        return $regions;
    }

    /**
     * @param array<string, list<PublicContentComponent>> $regions
     * @return array<string, list<PublicContentComponent>>
     */
    public function rewriteComponentLinks(
        array $regions,
        int $siteId,
        string $siteSlug,
        ?string $territorySlug = null,
    ): array {
        foreach ($regions as $region => $components) {
            foreach ($components as $index => $component) {
                $html = $this->rewriteHtml($component->html, $siteId, $siteSlug, $territorySlug);

                if ($html === $component->html) {
                    continue;
                }

                $regions[$region][$index] = new PublicContentComponent(
                    id: $component->id,
                    type: $component->type,
                    region: $component->region,
                    priority: $component->priority,
                    html: $html,
                    styles: $component->styles,
                    scripts: $component->scripts,
                    endpoints: $component->endpoints,
                    stateful: $component->stateful,
                    hydration: $component->hydration,
                );
            }
        }

        return $regions;
    }

    public function rewriteHtml(
        string $html,
        int $siteId,
        string $siteSlug,
        ?string $territorySlug = null,
    ): string {
        if ($html === '') {
            return $html;
        }

        $this->preloadPagesFromHtml($html, $siteId, $siteSlug, $territorySlug);

        return (string) preg_replace_callback(
            '/\b(href)=("|\')([^"\']+)(\2)/i',
            fn(array $matches): string => $matches[1]
                . '='
                . $matches[2]
                . htmlspecialchars(
                    $this->rewriteUrl($matches[3], $siteId, $siteSlug, $territorySlug),
                    ENT_QUOTES,
                    'UTF-8',
                )
                . $matches[4],
            $html,
        );
    }

    private function preloadPagesFromHtml(
        string $html,
        int $siteId,
        string $siteSlug,
        ?string $territorySlug,
    ): void {
        if (!preg_match_all('/\bhref=("|\')([^"\']+)(\1)/i', $html, $matches)) {
            return;
        }

        $needed = [];
        foreach ($matches[2] as $url) {
            $slug = $this->pageSlugFromUrl((string) $url, $siteSlug, $territorySlug);
            if ($slug === null) {
                continue;
            }

            if ($this->hasCachedPage($siteId, $slug)) {
                continue;
            }

            $needed[$slug] = true;
        }

        if ($needed === []) {
            return;
        }

        $loaded = $this->pages->findPublishedBySlugs($siteId, array_keys($needed), ['categories']);
        foreach (array_keys($needed) as $slug) {
            $this->pagesBySlug[$this->cacheKey($siteId)][$slug] = $loaded[$slug] ?? null;
        }
    }

    private function rewriteUrl(
        string $url,
        int $siteId,
        string $siteSlug,
        ?string $territorySlug,
    ): string {
        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }

        $path = $parts['path'] ?? '';
        if ($path === '') {
            return $url;
        }

        $segments = array_values(array_filter(
            explode('/', trim($path, '/')),
            static fn(string $segment): bool => $segment !== '',
        ));

        if ($segments === [] || rawurldecode($segments[0]) !== $siteSlug) {
            return $url;
        }

        $contentSegments = array_slice($segments, 1);
        $region = $territorySlug !== null && $territorySlug !== '' ? $territorySlug : null;

        if ($region !== null && $contentSegments !== [] && rawurldecode($contentSegments[0]) === $region) {
            $contentSegments = array_slice($contentSegments, 1);
        }

        if ($contentSegments === []) {
            return $url;
        }

        $pageSlug = rawurldecode((string) end($contentSegments));
        if ($pageSlug === '' || in_array($pageSlug, self::RESERVED_SEGMENTS, true)) {
            return $url;
        }

        if (in_array(rawurldecode($contentSegments[0]), self::RESERVED_SEGMENTS, true)) {
            return $url;
        }

        $page = $this->pageForSlug($siteId, $pageSlug);
        $pathBody = $page instanceof Page
            ? $this->paths->canonicalPathForPage($page)
            : implode('/', array_map(
                static fn(string $segment): string => rawurldecode($segment),
                $contentSegments,
            ));

        if ($pathBody === '') {
            return $url;
        }

        $rewrittenPath = $region !== null
            ? '/' . rawurlencode($siteSlug) . '/' . rawurlencode($region) . '/' . $this->encodePath($pathBody)
            : '/' . rawurlencode($siteSlug) . '/' . $this->encodePath($pathBody);

        if ($rewrittenPath === $path && !isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        if (!isset($parts['scheme'], $parts['host'])) {
            return $this->withQueryAndFragment($rewrittenPath, $parts);
        }

        $authority = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $authority .= ':' . $parts['port'];
        }

        return $authority . $this->withQueryAndFragment($rewrittenPath, $parts);
    }

    private function pageSlugFromUrl(string $url, string $siteSlug, ?string $territorySlug): ?string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return null;
        }

        $path = $parts['path'] ?? '';
        if ($path === '') {
            return null;
        }

        $segments = array_values(array_filter(
            explode('/', trim($path, '/')),
            static fn(string $segment): bool => $segment !== '',
        ));

        if ($segments === [] || rawurldecode($segments[0]) !== $siteSlug) {
            return null;
        }

        $contentSegments = array_slice($segments, 1);
        $region = $territorySlug !== null && $territorySlug !== '' ? $territorySlug : null;

        if ($region !== null && $contentSegments !== [] && rawurldecode($contentSegments[0]) === $region) {
            $contentSegments = array_slice($contentSegments, 1);
        }

        if ($contentSegments === []) {
            return null;
        }

        $pageSlug = rawurldecode((string) end($contentSegments));
        if ($pageSlug === '' || in_array($pageSlug, self::RESERVED_SEGMENTS, true)) {
            return null;
        }

        if (in_array(rawurldecode($contentSegments[0]), self::RESERVED_SEGMENTS, true)) {
            return null;
        }

        return $pageSlug;
    }

    private function pageForSlug(int $siteId, string $slug): ?Page
    {
        $key = $this->cacheKey($siteId);

        if (!$this->hasCachedPage($siteId, $slug)) {
            $loaded = $this->pages->findPublishedBySlugs($siteId, [$slug], ['categories']);
            $this->pagesBySlug[$key][$slug] = $loaded[$slug] ?? null;
        }

        return $this->pagesBySlug[$key][$slug] ?? null;
    }

    private function hasCachedPage(int $siteId, string $slug): bool
    {
        return array_key_exists($slug, $this->pagesBySlug[$this->cacheKey($siteId)] ?? []);
    }

    private function cacheKey(int $siteId): string
    {
        return (string) $siteId;
    }

    /** @param array<string, mixed> $parts */
    private function withQueryAndFragment(string $path, array $parts): string
    {
        if (isset($parts['query']) && $parts['query'] !== '') {
            $path .= '?' . $parts['query'];
        }

        if (isset($parts['fragment']) && $parts['fragment'] !== '') {
            $path .= '#' . $parts['fragment'];
        }

        return $path;
    }

    private function encodePath(string $path): string
    {
        $segments = array_filter(explode('/', trim($path, '/')), static fn(string $segment): bool => $segment !== '');

        return implode('/', array_map(rawurlencode(...), $segments));
    }
}
