<?php

namespace App\Controllers;

use App\Events\ActivityTracking;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Page;
use App\Models\PageLike;
use App\Models\PageView;
use App\Models\Site;
use App\Repositories\CommentRepository;
use App\Repositories\PageGridRepository;
use App\Repositories\PageViewRepository;
use App\Services\ArticleAccessService;
use App\Services\BlockParserService;
use App\Services\MenuRenderer;
use App\Services\PageRenderService;
use App\Services\Subscriptions\SubscriptionModalService;
use App\Services\Url\UrlResolutionResult;

class ContentController extends Controller
{
    public function __construct(
        private readonly BlockParserService $blockParserService,
        private readonly CommentRepository  $commentRepository,
        private readonly PageViewRepository $pageViewRepository,
        private readonly PageGridRepository $pageGridRepository,
        private readonly ActivityTracking         $activityTracking,
        private readonly SubscriptionModalService $modalService,
        private readonly PageRenderService $pageRenderService,
        private readonly ArticleAccessService $articleAccessService,
    ) {
        parent::__construct();
    }

    public function show(Page $page, UrlResolutionResult $urlResolutionResult)
    {
        $member = MemberAuth::getMember();
        $memberId = $member ? $member->id : null;

        $accessCheck = $this->articleAccessService->canView($page, $member);

        if (!$accessCheck['can_view']) {
            // Redirect to subscription page or show paywall
            return $this->showPaywall($page, $accessCheck['reason']);
        }

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
            'seo', 'settings', 'social', 'customFields', 'authors', 'products'
        ]);

        $modalData = $this->modalService->getModalData($member, $siteId);

        $this->pageViewRepository->recordView(
            $page->id,
            $memberId,
            $siteId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $_SERVER['HTTP_REFERER'] ?? null
        );

        $this->activityTracking->trackPageView($page);

        $data = [
            'menu' => $menu,
            'footerMenu' => $footerMenu,
            'page' => $page,
            'blockParserService' => $this->blockParserService,
            'site' => SiteContext::get(),
            'member' => $member,
            'subscriptionModalData' => $modalData,
            'menuRenderer' => new Menurenderer()
        ];

        $data['allCategories'] = Category::where('site_id', $siteId)
            ->where('is_active', 1)
            ->withCount('pages')
            ->whereHas('pages')
            ->orderBy('name')
            ->get();

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

        $dealsService = new \App\Services\DealsService();
        $data['todaysDeals'] = $dealsService->getTodaysDeals(10);

        // Fallback to default theme if theme view doesn't exist
        if (!$this->viewExists($viewPath)) {
            $viewPath = "estate/page";
        }

        $html = $this->pageRenderService->renderPage($page, $siteId);
        $data['html'] = $html;

        return $this->view($viewPath, $data);
    }

    public function sites() {
        $sites = Site::active()->get();

        return $this->view('estate/sites', [
            'sites' => $sites
        ]);
    }

    private function showPaywall(Page $page, string $reason): Response
    {
        $siteId = SiteContext::getId();
        $member = MemberAuth::getMember();

        $menu = Menu::where('is_active', true)
            ->where('site_id', $siteId)
            ->where('menu_type', 'header')
            ->with(['items'])
            ->first();

        return $this->view('estate/paywall', [
            'page' => $page,
            'reason' => $reason,
            'menu' => $menu,
            'member' => $member,
            'menuRenderer' => new MenuRenderer()
        ]);
    }
}