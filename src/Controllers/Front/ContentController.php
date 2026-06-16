<?php

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\Events\ActivityTracking;
use App\Events\Members\PageViewedByMember;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Models\Badge;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Page;
use App\Models\PageLike;
use App\Models\PageView;
use App\Models\Site;
use App\Repositories\Cms\Pages\PageGridRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Members\BadgeRepository;
use App\Repositories\Members\CommentRepository;
use App\Repositories\Members\PageViewRepository;
use App\Services\Cms\MenuRenderer;
use App\Services\Cms\Pages\ArticleAccessService;
use App\Services\Cms\Pages\BlockParserService;
use App\Services\Cms\Pages\PageRenderService;
use App\Services\Members\ArticleGiftingService;
use App\Services\Offers\DealsService;
use App\Services\Subscriptions\SubscriptionModalService;

class ContentController extends Controller
{
    public function __construct(
        private readonly BlockParserService $blockParserService,
        private readonly CommentRepository $commentRepository,
        private readonly PageViewRepository $pageViewRepository,
        private readonly PageGridRepository $pageGridRepository,
        private readonly ActivityTracking $activityTracking,
        private readonly SubscriptionModalService $modalService,
        private readonly PageRenderService $pageRenderService,
        private readonly ArticleAccessService $articleAccessService,
        private readonly BadgeRepository $badgeRepository,
        private readonly PageRepository $pageRepository,
        private readonly DealsService $dealsService,
        private readonly ArticleGiftingService $articleGiftingService,
        private readonly MenuRenderer $menuRenderer,
    ) {
        parent::__construct();
    }

    public function show(Page $page)
    {
        $member = MemberAuth::getMember();
        $memberId = $member ? $member->id : null;

        $accessCheck = $this->articleAccessService->canView($page, $member);

        if (!$accessCheck['can_view']) {
            return $this->showPaywall($page, $accessCheck['reason']);
        }

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

        $page->load([
            'blocks', 'categories', 'tags', 'metadata',
            'seo', 'settings', 'social', 'customFields', 'authors', 'products', 'comments'
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

        if ($memberId) {
            event(new PageViewedByMember($memberId, $page->id, $siteId));
        }

        $this->activityTracking->trackPageView($page);

        $claimedGift = null;
        if (MemberAuth::check()) {
            $member = MemberAuth::getMember();
            $claimedGift = $this->articleGiftingService->checkAndClaimGiftForPage($member, $page);
        }

        $data = [
            'menu' => $menu,
            'footerMenu' => $footerMenu,
            'page' => $page,
            'blockParserService' => $this->blockParserService,
            'site' => SiteContext::get(),
            'member' => $member,
            'subscriptionModalData' => $modalData,
            'menuRenderer' => $this->menuRenderer,
            'claimedGift' => $claimedGift,
        ];

        $data['allCategories'] = Category::where('site_id', $siteId)
            ->where('is_active', 1)
            ->withCount('pages')
            ->whereHas('pages')
            ->orderBy('name')
            ->get();

        $data['comments'] = $this->commentRepository->getCommentsForPage($page->id, true);

        if ($member) {
            $badgeService = app(\App\Services\Members\BadgeService::class);
            $commentingBadges = Badge::where('site_id', $siteId)
                ->where('is_active', true)
                ->where('category', 'engagement')
                ->get()
                ->filter(function ($badge) {
                    return collect($badge->criteria)
                        ->contains(fn($criteria) => ($criteria['type'] ?? null) === 'comments_count');
                })
                ->sortBy(function ($badge) {
                    return collect($badge->criteria)
                        ->firstWhere('type', 'comments_count')['value'] ?? PHP_INT_MAX;
                })
                ->all();

            $nextCommentBadge = null;
            $badgeProgress = null;

            foreach ($commentingBadges as $badge) {
                if (!$this->badgeRepository->getEarnedBadges($member)->contains('id', $badge->id)) {
                    $nextCommentBadge = $badge;
                    $badgeProgress = $badgeService->calculateBadgeProgress($member, $badge);
                    break;
                }
            }

            $data['nextCommentBadge'] = $nextCommentBadge;
            $data['commentBadgeProgress'] = $badgeProgress;
        }

        $data['isLiked'] = $member
            ? PageLike::isLikedBy($page->id, $member->id, $siteId)
            : false;
        $data['likeCount'] = PageLike::getLikeCount($page->id);
        $data['viewCount'] = PageView::getTotalViewCount($page->id);

        $theme = SiteContext::getTheme();
        $viewPath = "{$theme}/page";
        $data['todaysDeals'] = $this->dealsService->getTodaysDeals(10);

        if (!$this->viewExists($viewPath)) {
            $viewPath = 'estate/page';
        }

        $data['html'] = $this->pageRenderService->renderPage($page, $siteId, MemberAuth::getMember());

        if ($page->page_type === 'landing-page' && !empty($data['allCategories'])) {
            $data['categoriesWithPages'] = $this->getCategoryPages($siteId, $data['allCategories']);
        }

        return $this->view($viewPath, $data);
    }

    public function sites()
    {
        $sites = Site::active()->get();

        return $this->view('estate/sites', ['sites' => $sites]);
    }

    private function getCategoryPages(int $siteId, $categories)
    {
        $categoriesWithPages = [];

        foreach ($categories as $category) {
            $categoryPages = $this->pageRepository->getPagesByCategory($category->id, 6, $siteId);

            if (!$categoryPages->count() || $categoryPages->count() < 3) {
                continue;
            }

            $categoriesWithPages[$category->id]['category'] = $category;
            $categoriesWithPages[$category->id]['pages'] = $categoryPages;
        }

        return $categoriesWithPages;
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
            'menuRenderer' => $this->menuRenderer,
            'subscriptionModalData' => empty($page->contributor_id)
                ? $this->modalService->getModalData($member, $siteId)
                : null,
        ]);
    }
}
