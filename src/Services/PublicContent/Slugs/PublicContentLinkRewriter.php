<?php

namespace App\Services\PublicContent\Slugs;

use App\DTO\PublicContent\PublicContentComponent;
use App\Models\Page;

final class PublicContentLinkRewriter
{
    public function __construct(private readonly PublicContentPathResolver $paths)
    {
    }

    /**
     * @param array<string, list<PublicContentComponent>> $regions
     * @return array<string, list<PublicContentComponent>>
     */
    public function rewriteComponentLinks(array $regions, int $siteId, string $siteSlug): array
    {
        foreach ($regions as $region => $components) {
            foreach ($components as $index => $component) {
                $html = $this->rewriteHtml($component->html, $siteId, $siteSlug);

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

    public function rewriteHtml(string $html, int $siteId, string $siteSlug): string
    {
        if ($html === '') {
            return $html;
        }

        return (string) preg_replace_callback(
            '/\b(href)=("|\')([^"\']+)(\2)/i',
            fn(array $matches): string => $matches[1]
                . '='
                . $matches[2]
                . htmlspecialchars($this->rewriteUrl($matches[3], $siteId, $siteSlug), ENT_QUOTES, 'UTF-8')
                . $matches[4],
            $html,
        );
    }

    private function rewriteUrl(string $url, int $siteId, string $siteSlug): string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }

        $path = $parts['path'] ?? '';
        if ($path === '') {
            return $url;
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn(string $segment): bool => $segment !== ''));
        if (count($segments) !== 2 || rawurldecode($segments[0]) !== $siteSlug) {
            return $url;
        }

        $slug = rawurldecode($segments[1]);
        if ($slug === '' || in_array($slug, ['shop', 'login', 'register', 'account'], true)) {
            return $url;
        }

        $page = Page::with(['categories'])
            ->where('site_id', $siteId)
            ->where('slug', $slug)
            ->where('status', 'published')
            ->first();

        if (!$page instanceof Page) {
            return $url;
        }

        $canonicalPath = $this->paths->canonicalPathForPage($page);
        if ($canonicalPath === '' || $canonicalPath === $slug) {
            return $url;
        }

        $rewrittenPath = '/' . rawurlencode($siteSlug) . '/' . $this->encodePath($canonicalPath);

        if (!isset($parts['scheme'], $parts['host'])) {
            return $this->withQueryAndFragment($rewrittenPath, $parts);
        }

        $authority = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $authority .= ':' . $parts['port'];
        }

        return $authority . $this->withQueryAndFragment($rewrittenPath, $parts);
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
