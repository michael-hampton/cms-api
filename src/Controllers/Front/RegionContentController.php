<?php

namespace App\Controllers\Front;

use App\Actions\PublicContent\RenderPublicContentPageAction;
use App\Controllers\Controller;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Parsers\PageGridRenderer;
use App\Repositories\Cms\Pages\PageGridRepository;
use App\Repositories\Members\CommentRepository;
use App\Repositories\PublicContent\PublicContentPageRepository;
use App\Repositories\PublicContent\PublicNavigationRepository;
use App\Repositories\PublicContent\PublicTerritoryRepository;
use App\Services\Cms\Pages\BlockParserService;
use App\Services\PublicContent\PublicContentRollout;

class RegionContentController extends Controller
{
    public function __construct(
        private readonly BlockParserService $blockParserService,
        private readonly CommentRepository $commentRepository,
        private readonly PageGridRepository $pageGridRepository,
        private readonly PublicContentPageRepository $publicPages,
        private readonly PublicTerritoryRepository $territories,
        private readonly PublicNavigationRepository $navigation,
        private readonly PublicContentRollout $rollout,
        private readonly RenderPublicContentPageAction $renderPublicContent,
    ) {
        parent::__construct();
    }

    public function show(string $regionSlug, ?string $pageSlug = null): Response
    {
        $siteId = SiteContext::getId();
        $territory = $this->territories->findActiveBySlug($siteId, $regionSlug);

        if (!$territory) {
            return $this->notFound('Region not found.');
        }

        $page = $pageSlug === null || $pageSlug === ''
            ? $this->publicPages->findCompleteHomepageForTerritory(
                $siteId,
                (int) $territory->id,
                (string) $territory->slug,
            )
            : $this->publicPages->findCompletePublishedBySlugForTerritory(
                $siteId,
                $pageSlug,
                (int) $territory->id,
            );

        if (!$page) {
            return $this->notFound(
                $pageSlug === null || $pageSlug === ''
                    ? 'Regional homepage not found.'
                    : 'Regional content not found.',
            );
        }

        if ($this->rollout->enabledFor($page)) {
            return $this->renderPublicContent->execute($page, false, $territory);
        }

        $pageGrid = $this->pageGridRepository->getActiveGridForTerritory((int)$territory->id);
        $pageGridHtml = $pageGrid
            ? (new PageGridRenderer())->render($pageGrid, $territory)
            : null;

        $data = [
            'menu' => $this->navigation->findActiveMenu($siteId, 'header', (int)$territory->id),
            'pageGridHtml' => $pageGridHtml,
            'footerMenu' => $this->navigation->findActiveMenu($siteId, 'footer', (int)$territory->id),
            'page' => $page,
            'territory' => $territory,
            'allTerritories' => $this->territories->getActiveForSite($siteId),
            'regionArticles' => $this->publicPages->getRelatedForTerritory(
                $siteId,
                (int)$territory->id,
                (int)$page->id,
                6,
            ),
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
