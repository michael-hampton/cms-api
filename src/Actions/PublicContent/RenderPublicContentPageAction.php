<?php

namespace App\Actions\PublicContent;

use App\DTO\PublicContent\ResolvedGeo;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Models\Page;
use App\Models\Territory;
use App\Repositories\PublicContent\PublicTerritoryRepository;
use App\Services\Cms\MenuRenderer;
use App\Services\PublicContent\InitialPublicContentHeroResolver;
use App\Services\PublicContent\Locale\PublicContentLocaleResolver;
use App\Services\PublicContent\Navigation\MenuTreeSourceInterface;
use App\Services\PublicContent\Seo\PublicContentSeoFactory;
use App\Services\PublicContent\Theming\PublicContentDesignTokenProvider;

class RenderPublicContentPageAction
{
    public function __construct(
        private readonly MenuTreeSourceInterface $menus,
        private readonly MenuRenderer $menuRenderer,
        private readonly PublicContentSeoFactory $seoFactory,
        private readonly InitialPublicContentHeroResolver $initialHeroResolver,
        private readonly PublicContentDesignTokenProvider $designTokens,
        private readonly PublicContentLocaleResolver $localeResolver,
        private readonly PublicTerritoryRepository $territories,
    ) {
    }

    public function execute(
        Page $page,
        bool $preview = false,
        ?Territory $territory = null,
        ?string $apiUrl = null,
        ?ResolvedGeo $geo = null,
    ): Response {
        $siteId = SiteContext::getId();
        $siteSlug = SiteContext::slug();
        $territoryId = $territory ? (int) $territory->id : null;
        $initialHero = $this->initialHeroResolver->resolve($page);
        $localeContext = $this->localeResolver->fromTerritory($territory);
        $headerMenu = $this->menus->findTree($siteId, 'header', $territoryId);
        $footerMenu = $this->menus->findTree($siteId, 'footer', $territoryId);
        $menuLayout = is_object($headerMenu) ? ($headerMenu->layout_config ?? []) : [];
        $headerLayout = (string) ($menuLayout['header_style'] ?? $menuLayout['header_layout'] ?? 'default');

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

        if ($geo !== null) {
            $query = array_filter($geo->toArray(), static fn(mixed $value): bool => $value !== null && $value !== '');
            $apiUrl .= (str_contains($apiUrl, '?') ? '&' : '?') . http_build_query($query);
        }

        $seo = $this->seoFactory->make(
            page: $page,
            siteSlug: $siteSlug,
            territory: $territory,
            preview: $preview,
            alternateTerritories: $this->territories->getActiveForSite($siteId),
            localeContext: $localeContext,
        );

        $response = Response::view('public-content-v2/page', [
            'preview' => $preview,
            'site' => SiteContext::get(),
            'siteSlug' => $siteSlug,
            'contentSlug' => (string) $page->slug,
            'pageType' => (string) $page->page_type,
            'pageTitle' => (string) $page->title,
            'pageDescription' => $page->meta_description ?? '',
            'seo' => $seo,
            'locale' => $seo->locale ?? $localeContext->localeTag(),
            'headerLayout' => $headerLayout,
            'territory' => $territory,
            'menu' => $headerMenu,
            'menuRenderer' => $this->menuRenderer,
            'footerMenu' => $footerMenu,
            'apiUrl' => $apiUrl,
            'initialHero' => $initialHero,
            'heroPreloadUrl' => $initialHero?->preloadUrl,
            'designTokenVariables' => $this->designTokens->cssVariablesForSite($siteId),
        ]);

        return $response
            ->setHeader('Cache-Control', sprintf(
                'public, max-age=%d, must-revalidate',
                max(1, min(
                    (int) config('public_content.cache.public_ttl_seconds', 300),
                    (int) config('public_content.cache.kill_switch_cache_clear_seconds', 60),
                )),
            ))
            ->setHeader('X-Public-Content-Renderer', 'v2')
            ->setHeader(
                'X-Public-Content-Cache-Clear-Bound',
                (string) config('public_content.cache.kill_switch_cache_clear_seconds', 60),
            )
            ->setHeader('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' https:",
                "style-src 'self' 'unsafe-inline' https:",
                "img-src 'self' data: https:",
                "font-src 'self' data: https:",
                "connect-src 'self' https:",
                "frame-src https:",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'none'",
            ]))
            ->setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
            ->setHeader('Permissions-Policy', 'geolocation=(), camera=(), microphone=()')
            ->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('X-Frame-Options', 'DENY');
    }
}
