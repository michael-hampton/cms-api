<?php

namespace App\Actions\PublicContent;

use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Models\Page;
use App\Models\Territory;
use App\Repositories\PublicContent\PublicNavigationRepository;
use App\Services\Cms\MenuRenderer;

class RenderPublicContentPageAction
{
    public function __construct(
        private readonly PublicNavigationRepository $navigation,
        private readonly MenuRenderer $menuRenderer,
    ) {
    }

    public function execute(
        Page $page,
        bool $preview = false,
        ?Territory $territory = null,
        ?string $apiUrl = null,
    ): Response {
        $siteId = SiteContext::getId();
        $siteSlug = SiteContext::slug();
        $territoryId = $territory ? (int) $territory->id : null;

        $apiUrl ??= $territory
            ? sprintf(
                '/api/v1/%s/regions/%s/content/%s',
                rawurlencode($siteSlug),
                rawurlencode((string) $territory->slug),
                rawurlencode((string) $page->slug),
            )
            : sprintf(
                '/api/v1/%s/content/%s',
                rawurlencode($siteSlug),
                rawurlencode((string) $page->slug),
            );

        return Response::view('public-content-v2/page', [
            'preview' => $preview,
            'site' => SiteContext::get(),
            'siteSlug' => $siteSlug,
            'contentSlug' => (string) $page->slug,
            'pageTitle' => (string) $page->title,
            'pageDescription' => $page->meta_description ?? '',
            'seo' => $this->seoData($page, $siteSlug, $territory, $preview),
            'territory' => $territory,
            'menu' => $this->navigation->findActiveMenu($siteId, 'header', $territoryId),
            'menuRenderer' => $this->menuRenderer,
            'footerMenu' => $this->navigation->findActiveMenu($siteId, 'footer', $territoryId),
            'apiUrl' => $apiUrl,
        ]);
    }

    private function seoData(
        Page $page,
        string $siteSlug,
        ?Territory $territory,
        bool $preview,
    ): array {
        $pageSeo = $page->seo;
        $title = trim((string) ($pageSeo?->meta_title ?: $page->meta_title ?: $page->title));
        $description = trim((string) (
            $pageSeo?->meta_description
            ?: $page->meta_description
            ?: $page->listing_synopsis
            ?: $page->description
            ?: ''
        ));

        $canonicalPath = $territory
            ? $this->regionalCanonicalPath($siteSlug, $territory, $page)
            : sprintf('/%s/%s', rawurlencode($siteSlug), rawurlencode((string) $page->slug));

        $canonical = $this->absoluteUrl(
            trim((string) ($pageSeo?->canonical_url ?: $canonicalPath)),
        );
        $image = $this->absoluteUrl(trim((string) ($pageSeo?->og_image ?: '')));
        $robots = $preview
            ? 'noindex,nofollow'
            : sprintf(
                '%s,%s',
                $pageSeo?->shouldNoIndex() ? 'noindex' : 'index',
                $pageSeo?->shouldNoFollow() ? 'nofollow' : 'follow',
            );

        $schema = $pageSeo?->schema_markup;
        if (empty($schema) && in_array((string) $page->page_type, ['article', 'content'], true)) {
            $schema = array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $title,
                'description' => $description ?: null,
                'url' => $canonical,
                'image' => $image ?: null,
                'datePublished' => $this->isoDate($page->published_at),
                'dateModified' => $this->isoDate($page->updated_at),
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => SiteContext::name(),
                ],
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
        }

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => trim((string) ($pageSeo?->meta_keywords ?? '')),
            'canonical' => $canonical,
            'robots' => $robots,
            'og_type' => in_array((string) $page->page_type, ['article', 'content'], true) ? 'article' : 'website',
            'og_title' => trim((string) ($pageSeo?->og_title ?: $title)),
            'og_description' => trim((string) ($pageSeo?->og_description ?: $description)),
            'og_image' => $image,
            'twitter_card' => trim((string) ($pageSeo?->twitter_card ?: ($image ? 'summary_large_image' : 'summary'))),
            'schema' => $schema,
        ];
    }

    private function regionalCanonicalPath(string $siteSlug, Territory $territory, Page $page): string
    {
        if ((string) $page->slug === (string) $territory->slug) {
            return sprintf(
                '/%s/%s',
                rawurlencode($siteSlug),
                rawurlencode((string) $territory->slug),
            );
        }

        return sprintf(
            '/%s/%s/%s',
            rawurlencode($siteSlug),
            rawurlencode((string) $territory->slug),
            rawurlencode((string) $page->slug),
        );
    }

    private function absoluteUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');

        return $host !== ''
            ? $scheme . '://' . $host . '/' . ltrim($url, '/')
            : '/' . ltrim($url, '/');
    }

    private function isoDate(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? null : date(DATE_ATOM, $timestamp);
    }
}
