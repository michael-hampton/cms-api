<?php

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Repositories\PublicContent\PublicNavigationRepository;
use App\Services\FooterRenderer;

final class PublicDirectoryPageController extends Controller
{
    public function __construct(
        private readonly PublicNavigationRepository $navigation,
        private readonly FooterRenderer $footerRenderer,
    ) {
        parent::__construct();
    }

    public function index(string $type): Response
    {
        return $this->render($type, null);
    }

    public function show(string $type, string $slug): Response
    {
        return $this->render($type, $slug);
    }

    private function render(string $type, ?string $slug): Response
    {
        $siteId = SiteContext::getId();
        $siteSlug = SiteContext::slug();
        $footerMenu = $this->navigation->findActiveMenu($siteId, 'footer');
        $apiUrl = '/api/v1/' . rawurlencode($siteSlug) . '/directory/' . rawurlencode($type);

        if ($slug !== null) {
            $apiUrl .= '/' . rawurlencode($slug);
        }

        return $this->view('public-directory/page', [
            'site' => SiteContext::get(),
            'siteSlug' => $siteSlug,
            'type' => $type,
            'slug' => $slug,
            'menu' => $this->navigation->findActiveMenu($siteId, 'header'),
            'footerHtml' => $footerMenu
                ? $this->footerRenderer->renderFooter($footerMenu)
                : '',
            'apiUrl' => $apiUrl,
        ]);
    }
}
