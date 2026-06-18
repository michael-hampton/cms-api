<?php

namespace App\Actions\PublicContent;

use App\DTO\PublicContent\ResolvedGeo;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Models\Page;
use App\Models\Territory;
use App\Repositories\PublicContent\PublicNavigationRepository;
use App\Services\Cms\MenuRenderer;
use App\Services\PublicContent\Seo\PublicContentSeoFactory;

class RenderPublicContentPageAction
{
    public function __construct(
        private readonly PublicNavigationRepository $navigation,
        private readonly MenuRenderer $menuRenderer,
        private readonly PublicContentSeoFactory $seoFactory,
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

        $response = Response::view('public-content-v2/page', [
            'preview' => $preview,
            'site' => SiteContext::get(),
            'siteSlug' => $siteSlug,
            'contentSlug' => (string) $page->slug,
            'pageTitle' => (string) $page->title,
            'pageDescription' => $page->meta_description ?? '',
            'seo' => $this->seoFactory->make(
                page: $page,
                siteSlug: $siteSlug,
                territory: $territory,
                preview: $preview,
            ),
            'territory' => $territory,
            'menu' => $this->navigation->findActiveMenu($siteId, 'header', $territoryId),
            'menuRenderer' => $this->menuRenderer,
            'footerMenu' => $this->navigation->findActiveMenu($siteId, 'footer', $territoryId),
            'apiUrl' => $apiUrl,
        ]);

        return $response
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
