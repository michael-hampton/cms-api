<?php

namespace App\Controllers;

use App\Framework\Support\SiteContext;
use App\Models\Menu;
use App\Models\Page;
use App\Models\PageGrid;
use App\Models\Territory;
use App\Parsers\PageGridRenderer;
use App\Repositories\CommentRepository;
use App\Repositories\PageGridRepository;
use App\Services\BlockParserService;

class RegionContentController extends Controller
{
    public function __construct(
        private readonly BlockParserService $blockParserService,
        private readonly CommentRepository  $commentRepository,
        private readonly PageGridRepository $pageGridRepository  // ADD THIS
    ) {
        parent::__construct();
    }

    public function show(string $regionSlug, string $pageSlug) {
        $siteId = SiteContext::getId();

        // Get territory
        $territory = Territory::where('slug', strtolower($regionSlug))
            ->where('site_id', $siteId)
            ->where('is_active', true)
            ->first();

        // Get page for this region
        $page = Page::where('slug', $pageSlug)
            ->where('site_id', $siteId)
            ->where('status', 'published')
            ->whereHas('territories', function($query) use ($territory) {
                $query->where('territories.id', $territory->id);
            })
            ->with(['blocks', 'categories', 'tags', 'metadata', 'seo', 'settings', 'social', 'customFields', 'authors'])
            ->first();

        // Get page grid for this territory
        $pageGrid = $this->pageGridRepository->getActiveGridForTerritory($territory->id);

        $pageGridHtml = null;
        if ($pageGrid) {
            // Pass the territory to the renderer
            $pageGridHtml = (new PageGridRenderer())->render($pageGrid, $territory);
        }

        // Get region-specific menu
        $menu = Menu::where('is_active', true)
            ->where('site_id', $siteId)
            ->where('menu_type', 'header')
            ->whereHas('territories', function($query) use ($territory) {
                $query->where('territories.id', $territory->id);
            })
            ->with(['items'])
            ->first();

        $footerMenu = Menu::where('is_active', true)
            ->where('site_id', $siteId)
            ->where('menu_type', 'footer')
            ->with(['items'])
            ->first();

        // Get all territories for region selector
        $allTerritories = Territory::where('site_id', $siteId)
            ->where('is_active', true)
            ->get();

        // Get articles for this region (for page grid)
        $regionArticles = Page::where('site_id', $siteId)
            ->where('status', 'published')
            ->where('id', '!=', $page->id)
            ->whereHas('territories', function($query) use ($territory) {
                $query->where('territories.id', $territory->id);
            })
            ->with(['customFields'])
            ->limit(6)
            ->get();

        $data = [
            'menu' => $menu,
            'pageGridHtml' => $pageGridHtml,
            'footerMenu' => $footerMenu,
            'page' => $page,
            'territory' => $territory,
            'allTerritories' => $allTerritories,
            'regionArticles' => $regionArticles,
            'blockParserService' => $this->blockParserService,
            'site' => SiteContext::get(),
            'comments' => $this->commentRepository->getCommentsForPage($page->id, true)
        ];

        $theme = SiteContext::getTheme();
        $viewPath = "{$theme}/region-page";

        if (!$this->viewExists($viewPath)) {
            $viewPath = "travel/region-page";
        }

        return $this->view($viewPath, $data);
    }
}