<?php

namespace App\Services\PublicContent\Seo;

use App\DTO\PublicContent\PublicContentLocaleContext;
use App\DTO\PublicContent\PublicContentSeo;
use App\Framework\Support\Collection;
use App\Framework\Support\SiteContext;
use App\Models\Page;
use App\Models\Territory;
use App\Services\PublicContent\Locale\PublicContentLocaleResolver;

final class PublicContentSeoFactory
{
    public function __construct(
        private readonly PublicContentLocaleResolver $localeResolver = new PublicContentLocaleResolver(),
    ) {
    }

    /**
     * @param Collection<int, Territory>|list<Territory>|null $alternateTerritories
     */
    public function make(
        Page $page,
        string $siteSlug,
        ?Territory $territory = null,
        bool $preview = false,
        Collection|array|null $alternateTerritories = null,
        ?PublicContentLocaleContext $localeContext = null,
    ): PublicContentSeo {
        $localeContext ??= $this->localeResolver->fromTerritory($territory);
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
        if (empty($schema) && in_array((string) $page->page_type, ['article', 'content', 'review', 'buying-guide'], true)) {
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

        return new PublicContentSeo(
            title: $title,
            description: $description,
            keywords: trim((string) ($pageSeo?->meta_keywords ?? '')),
            canonical: $canonical,
            robots: $robots,
            ogType: in_array((string) $page->page_type, ['article', 'content', 'review', 'buying-guide'], true)
                ? 'article'
                : 'website',
            ogTitle: trim((string) ($pageSeo?->og_title ?: $title)),
            ogDescription: trim((string) ($pageSeo?->og_description ?: $description)),
            ogImage: $image,
            twitterCard: trim((string) ($pageSeo?->twitter_card ?: ($image ? 'summary_large_image' : 'summary'))),
            schema: is_array($schema) ? $schema : null,
            hreflangAlternates: $this->hreflangAlternates(
                page: $page,
                siteSlug: $siteSlug,
                alternateTerritories: $alternateTerritories,
                currentCanonical: $canonical,
                currentLocale: $localeContext,
            ),
            locale: $localeContext->localeTag(),
            region: $localeContext->region,
        );
    }

    /**
     * @param Collection<int, Territory>|list<Territory>|null $alternateTerritories
     * @return list<array{hreflang: string, href: string}>
     */
    private function hreflangAlternates(
        Page $page,
        string $siteSlug,
        Collection|array|null $alternateTerritories,
        string $currentCanonical,
        PublicContentLocaleContext $currentLocale,
    ): array {
        $alternates = [];

        if ($currentLocale->localeTag() !== null && $currentCanonical !== '') {
            $alternates[] = [
                'hreflang' => $currentLocale->localeTag(),
                'href' => $currentCanonical,
            ];
        }

        if ($alternateTerritories === null) {
            return $this->uniqueAlternates($alternates);
        }

        foreach ($alternateTerritories as $territory) {
            if (!$territory instanceof Territory) {
                continue;
            }

            $locale = $this->localeResolver->fromTerritory($territory);
            $tag = $locale->localeTag() ?? strtolower((string) ($territory->code ?: $territory->slug));

            if ($tag === '') {
                continue;
            }

            $alternates[] = [
                'hreflang' => $tag,
                'href' => $this->absoluteUrl($this->regionalCanonicalPath($siteSlug, $territory, $page)),
            ];
        }

        return $this->uniqueAlternates($alternates);
    }

    /**
     * @param list<array{hreflang: string, href: string}> $alternates
     * @return list<array{hreflang: string, href: string}>
     */
    private function uniqueAlternates(array $alternates): array
    {
        $seen = [];
        $unique = [];

        foreach ($alternates as $alternate) {
            $key = $alternate['hreflang'] . '|' . $alternate['href'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $alternate;
        }

        return $unique;
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

    private function isoDate(?\DateTimeInterface $value): ?string
    {
        return $value?->format(DATE_ATOM);
    }
}
