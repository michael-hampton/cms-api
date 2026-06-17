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
            'territory' => $territory,
            'menu' => $this->navigation->findActiveMenu($siteId, 'header', $territoryId),
            'menuRenderer' => $this->menuRenderer,
            'footerMenu' => $this->navigation->findActiveMenu($siteId, 'footer', $territoryId),
            'apiUrl' => $apiUrl,
        ]);
    }
}
