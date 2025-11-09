<?php

namespace App\Controllers;

use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\SiteContext;
use App\Models\Menu;
use App\Models\Page;
use App\Models\PageGrid;
use App\Models\PageLike;
use App\Models\PageView;
use App\Models\Site;
use App\Parsers\PageGridRenderer;
use App\Repositories\CommentRepository;
use App\Repositories\PageViewRepository;
use App\Services\BlockParserService;
use App\Services\Url\UrlResolutionResult;

class ContentController extends Controller
{
    public function __construct(
        private readonly BlockParserService $blockParserService,
        private readonly CommentRepository  $commentRepository,
        private readonly PageViewRepository $pageViewRepository
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

        $member = MemberAuth::member();
        $memberId = $member ? $member->id : null;

        $this->pageViewRepository->recordView(
            $page->id,
            $memberId,
            $siteId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $_SERVER['HTTP_REFERER'] ?? null
        );

        $data = [
            'menu' => $menu,
            'footerMenu' => $footerMenu,
            'page' => $page,
            'blockParserService' => $this->blockParserService,
            'site' => SiteContext::get(),
            'member' => $member
        ];

        $data['comments'] = $this->commentRepository->getCommentsForPage($page->id, true);

        // Add like information if member is logged in
        if ($member) {
            $data['isLiked'] = PageLike::isLikedBy($page->id, $member->id, $siteId);
        } else {
            $data['isLiked'] = false;
        }

        $data['likeCount'] = PageLike::getLikeCount($page->id);
        $data['viewCount'] = PageView::getTotalViewCount($page->id);

        // Use site-specific theme if available
        $theme = SiteContext::getTheme();
        $viewPath = "{$theme}/page";

        $pageGrid = PageGrid::where('is_active', true)
            ->whereHas('pages', function($query) use ($page) {
                $query->where('pages.id', $page->id);
            })
            ->where('site_id', $siteId)
            ->first();

        $pageGridHtml = null;
        if ($pageGrid) {
            // Pass the territory to the renderer
            $pageGridHtml = (new PageGridRenderer())->render($pageGrid);
        }

        $data['pageGridHtml'] = $pageGridHtml;

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