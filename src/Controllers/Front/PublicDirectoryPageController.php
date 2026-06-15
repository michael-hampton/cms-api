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
        return $this->render('author', null, false);
    }

    public function author(string $slug): Response
    {
        return $this->render('author', $slug, false);
    }

    public function categories(): Response
    {
        return $this->render('category', null, false);
    }

    public function category(string $slug): Response
    {
        return $this->render('category', $slug, false);
    }

    public function tags(): Response
    {
        return $this->render('tag', null, false);
    }

    public function tag(string $slug): Response
    {
        return $this->render('tag', $slug, false);
    }

    public function previewAuthors(): Response
    {
        return $this->render('author', null, true);
    }

    public function previewAuthor(string $slug): Response
    {
        return $this->render('author', $slug, true);
    }

    public function previewCategories(): Response
    {
        return $this->render('category', null, true);
    }

    public function previewCategory(string $slug): Response
    {
        return $this->render('category', $slug, true);
    }

    public function previewTags(): Response
    {
        return $this->render('tag', null, true);
    }

    public function previewTag(string $slug): Response
    {
        return $this->render('tag', $slug, true);
    }

    private function render(string $type, ?string $slug, bool $preview): Response
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
            'preview' => $preview,
            'menu' => $this->navigation->findActiveMenu($siteId, 'header'),
            'menuRenderer' => $this->menuRenderer,
            'footerHtml' => $footerMenu
                ? $this->footerRenderer->renderFooter($footerMenu)
                : '',
            'apiUrl' => $apiUrl,
        ]);
    }
}
