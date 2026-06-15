<?php

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Repositories\PublicContent\PublicContentPageRepository;
use App\Repositories\PublicContent\PublicNavigationRepository;
use App\Services\Cms\MenuRenderer;
use App\Services\PublicContent\PublicContentRollout;

final class PublicContentHomepagePreviewController extends Controller
{
    public function __construct(
        private readonly PublicContentRollout $rollout,
        private readonly PublicContentPageRepository $pages,
        private readonly PublicNavigationRepository $navigation,
        private readonly MenuRenderer $menuRenderer,
    ) {
        parent::__construct();
    }

    public function show(): Response
    {
        if (!$this->rollout->previewEnabled()) {
            return $this->notFound('Public content preview is disabled.');
        }

        $site = SiteContext::get();
        $page = $this->pages->findHomepage($site);

        if (!$page) {
            return $this->notFound('Homepage not found.');
        }

        $siteId = SiteContext::getId();
        $siteSlug = SiteContext::slug();

        return $this->view('public-content-v2/page', [
            'site' => $site,
            'siteSlug' => $siteSlug,
            'contentSlug' => (string)$page->slug,
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
