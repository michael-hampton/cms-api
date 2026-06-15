<?php

namespace App\Controllers\Front;

use App\Actions\PublicContent\RenderPublicContentPageAction;
use App\Controllers\Controller;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Territory;
use App\Parsers\PageGridRenderer;
use App\Repositories\Cms\Pages\PageGridRepository;
use App\Repositories\Members\CommentRepository;
use App\Repositories\PublicContent\PublicContentPageRepository;
use App\Services\Cms\Pages\BlockParserService;
use App\Services\PublicContent\PublicContentRollout;

class RegionContentController extends Controller
{
    public function __construct(
        private readonly BlockParserService $blockParserService,
        private readonly CommentRepository $commentRepository,
        private readonly PageGridRepository $pageGridRepository,
        private readonly PublicContentPageRepository $publicPages,
        private readonly PublicContentRollout $rollout,
        private readonly RenderPublicContentPageAction $renderPublicContent,
    ) {
        parent::__construct();
    }

    public function show(string $regionSlug, string $pageSlug): Response
    {
        $siteId = SiteContext::getId();

        $territory = Territory::where('slug', strtolower($regionSlug))
            ->where('site_id', $siteId)
            ->where('is_active', true)
            ->first();

        if (!$territory instanceof Territory) {
            return $this->notFound('Region not found.');
        }

        $page = $this->publicPages->findCompletePublishedBySlugForTerritory(
            $siteId,
            $pageSlug,
            (int)$territory->id,
        );

        if (!$page instanceof Page) {
            return $this->notFound('Regional content not found.');
        }

        if ($this->rollout->enabledFor($page)) {
            return $this->renderPublicContent->execute(
                $page,
                false,
                $territory,
            );
        }

        $pageGrid = $this->pageGridRepository->getActiveGridForTerritory((int)$territory->id);
        $pageGridHtml = $pageGrid
            ? (new PageGridRenderer())->render($pageGrid, $territory)
            : null;

        $menu = Menu::where('is_active', true)
            ->where('site_id', $siteId)
            ->where('menu_type', 'header')
            ->whereHas('territories', function ($query) use ($territory): void {
                $query->where('territories.id', (int)$territory->id);
            })
            ->with(['items'])
            ->first();

        $footerMenu = Menu::where('is_active', true)
            ->where('site_id', $siteId)
            ->where('menu_type', 'footer')
            ->with(['items'])
            ->first();

        $allTerritories = Territory::where('site_id', $siteId)
            ->where('is_active', true)
            ->get();

        $regionArticles = Page::where('site_id', $siteId)
            ->where('status', 'published')
            ->where('id', '!=', (int)$page->id)
            ->whereHas('territories', function ($query) use ($territory): void {
                $query->where('territories.id', (int)$territory->id);
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
            'comments' => $this->commentRepository->getCommentsForPage((int)$page->id, true),
        ];

        $theme = SiteContext::getTheme();
        $viewPath = "{$theme}/region-page";

        if (!$this->viewExists($viewPath)) {
            $viewPath = 'travel/region-page';
        }

        return $this->view($viewPath, $data);
    }
}
