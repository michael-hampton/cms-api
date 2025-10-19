<?php

namespace App\Controllers;

use App\Framework\Support\SiteContext;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Site;
use App\Repositories\CommentRepository;
use App\Services\BlockParserService;
use App\Services\EstateWebsiteService;
use App\Services\MenuRenderer;
use App\Services\Url\UrlResolutionResult;

class ContentController extends Controller
{
    public function __construct(
        private readonly BlockParserService $blockParserService,
        private readonly CommentRepository  $commentRepository
    ) {
        parent::__construct();
    }

    public function show(Page $page, UrlResolutionResult $urlResolutionResult)
    {
        // Get site-specific menu
        $siteId = SiteContext::getId();

        $menu = Menu::where('is_active', true)
            ->where('site_id', $siteId)
            ->where('menu_type', 'header')
            ->with(['items'])
            ->first();

        $footerMenu = Menu::where('is_active', true)
            ->where('site_id', $siteId)
            ->where('menu_type', 'footer')
            ->with(['items'])
            ->first();

        // Load page relationships
        $page->load([
            'blocks', 'categories', 'tags', 'metadata',
            'seo', 'settings', 'social', 'customFields', 'authors'
        ]);

        $data = [
            'menu' => $menu,
            'footerMenu' => $footerMenu,
            'page' => $page,
            'blockParserService' => $this->blockParserService,
            'site' => SiteContext::get()
        ];

        $data['comments'] = $this->commentRepository->getCommentsForPage($page->id, true);

        // Use site-specific theme if available
        $theme = SiteContext::getTheme();
        $viewPath = "{$theme}/page";

        // Fallback to default theme if theme view doesn't exist
        if (!$this->viewExists($viewPath)) {
            $viewPath = "estate/page";
        }

        return $this->view($viewPath, $data);
    }

    public function sites() {
        $sites = Site::active()->get();

        return $this->view('estate/sites', [
            'sites' => $sites
        ]);
    }
}