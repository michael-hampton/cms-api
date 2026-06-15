<?php

namespace App\Actions\PublicContent;

use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Models\Page;
use App\Repositories\PublicContent\PublicNavigationRepository;
use App\Services\Cms\MenuRenderer;

class RenderPublicContentPageAction
{
    public function __construct(
        private readonly PublicNavigationRepository $navigation,
        private readonly MenuRenderer $menuRenderer,
    ) {
    }

    public function execute(Page $page, bool $preview = false): Response
    {
        $siteId = SiteContext::getId();
        $siteSlug = SiteContext::slug();

        return Response::view('public-content-v2/page', [
            'preview' => $preview,
            'site' => SiteContext::get(),
            'siteSlug' => $siteSlug,
            'contentSlug' => (string)$page->slug,
            'pageTitle' => (string)$page->title,
            'pageDescription' => $page->meta_description ?? '',
            'menu' => $this->navigation->findActiveMenu($siteId, 'header'),
            'menuRenderer' => $this->menuRenderer,
            'footerMenu' => $this->navigation->findActiveMenu($siteId, 'footer'),
            'apiUrl' => sprintf(
                '/api/v1/%s/content/%s',
                rawurlencode($siteSlug),
                rawurlencode((string)$page->slug),
            ),
        ]);
    }
}
