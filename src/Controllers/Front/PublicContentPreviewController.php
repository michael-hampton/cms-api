<?php

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Repositories\PublicContent\PublicNavigationRepository;
use App\Services\Cms\MenuRenderer;
use App\Services\PublicContent\PublicContentRollout;

final class PublicContentPreviewController extends Controller
{
    public function __construct(
        private readonly PublicContentRollout $rollout,
        private readonly PublicNavigationRepository $navigation,
        private readonly MenuRenderer $menuRenderer,
    ) {
        parent::__construct();
    }

    public function show(string $slug): Response
    {
        if (!$this->rollout->previewEnabled()) {
            return $this->notFound('Public content preview is disabled.');
        }

        $siteId = SiteContext::getId();
        $siteSlug = SiteContext::slug();

        return $this->view('public-content-v2/page', [
            'preview' => true,
            'site' => SiteContext::get(),
            'siteSlug' => $siteSlug,
            'contentSlug' => $slug,
            'menu' => $this->navigation->findActiveMenu($siteId, 'header'),
            'menuRenderer' => $this->menuRenderer,
            'footerMenu' => $this->navigation->findActiveMenu($siteId, 'footer'),
            'apiUrl' => sprintf(
                '/api/v1/%s/content/%s',
                rawurlencode($siteSlug),
                rawurlencode($slug),
            ),
        ]);
    }
}
