<?php

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Repositories\PublicContent\PublicContentPageRepository;
use App\Repositories\PublicContent\PublicNavigationRepository;
use App\Services\FooterRenderer;
use App\Services\PublicContent\PublicContentRollout;

final class PublicContentHomepagePreviewController extends Controller
{
    public function __construct(
        private readonly PublicContentRollout $rollout,
        private readonly PublicContentPageRepository $pages,
        private readonly PublicNavigationRepository $navigation,
        private readonly FooterRenderer $footerRenderer,
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
        $footerMenu = $this->navigation->findActiveMenu($siteId, 'footer');

        return $this->view('public-content-v2/page', [
            'site' => $site,
            'siteSlug' => $siteSlug,
            'contentSlug' => (string)$page->slug,
            'menu' => $this->navigation->findActiveMenu($siteId, 'header'),
            'footerHtml' => $footerMenu
                ? $this->footerRenderer->renderFooter($footerMenu)
                : '',
            'apiUrl' => sprintf(
                '/api/v1/%s/content/%s',
                rawurlencode($siteSlug),
                rawurlencode((string)$page->slug),
            ),
        ]);
    }
}
