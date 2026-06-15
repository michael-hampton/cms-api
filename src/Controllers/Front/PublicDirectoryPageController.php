<?php

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Repositories\PublicContent\PublicNavigationRepository;
use App\Services\Cms\MenuRenderer;
use App\Services\FooterRenderer;

final class PublicDirectoryPageController extends Controller
{
    public function __construct(
        private readonly PublicNavigationRepository $navigation,
        private readonly FooterRenderer $footerRenderer,
        private readonly MenuRenderer $menuRenderer,
    ) {
        parent::__construct();
    }

    public function authors(): Response
    {
        return $this->render('author', null);
    }

    public function author(string $slug): Response
    {
        return $this->render('author', $slug);
    }

    public function categories(): Response
    {
        return $this->render('category', null);
    }

    public function category(string $slug): Response
    {
        return $this->render('category', $slug);
    }

    public function tags(): Response
    {
        return $this->render('tag', null);
    }

    public function tag(string $slug): Response
    {
        return $this->render('tag', $slug);
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
            'menuRenderer' => $this->menuRenderer,
            'footerHtml' => $footerMenu
                ? $this->footerRenderer->renderFooter($footerMenu)
                : '',
            'apiUrl' => $apiUrl,
        ]);
    }
}
