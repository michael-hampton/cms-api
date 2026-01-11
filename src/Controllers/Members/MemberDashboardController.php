<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\SiteContext;
use App\Models\Address;
use App\Models\Member;
use App\Repositories\Cms\PageRepository;
use App\Repositories\Members\CommentRepository;
use App\Repositories\Members\MemberActivityRepository;
use App\Repositories\Members\MemberRepository;
use App\Repositories\Members\OrderRepository;
use App\Repositories\Members\PageLikeRepository;
use App\Repositories\Members\PageViewRepository;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Subscriptions\SubscriberRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Members\BadgeService;

class MemberDashboardController extends Controller
{
    public function __construct(
        private readonly OrderRepository          $orderRepository,
        private readonly SubscriptionRepository   $subscriptionRepository,
        private readonly SubscriberRepository     $subscriberRepository,
        private readonly CommentRepository        $commentRepository,
        private MemberRepository                  $memberRepository,
        private readonly PageRepository           $pageRepository,
        private NewsletterRepository              $newsletterRepository,
        private readonly PageViewRepository       $pageViewRepository,
        private readonly PageLikeRepository       $pageLikeRepository,
        private readonly BadgeService             $badgeService,
        private readonly MemberActivityRepository $activityRepository
    ) {
        parent::__construct();
    }

    public function index()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/' . SiteContext::slug() . '/member/login');
        }

        $member = MemberAuth::member();
        $siteId = SiteContext::getId();

        $memberObj = Member::with(['badges', 'points'])->find($member->id);

        $progress = $this->badgeService->getMemberProgress($memberObj);

        $recentActivities = $this->activityRepository->getMemberActivities($member->id, 20);

        $activityTrends = $this->badgeService->getActivityTrends($memberObj, 30);

        // Get counts for dashboard cards
        $stats = [
            'orders' => $this->orderRepository->getOrderCount(),
            'subscriptions' => $this->subscriptionRepository->countActiveSubscriptions($member->id, $siteId),
            'newsletters' => $this->getNewsletterCount($member->email, $siteId),
            'addresses' => Address::where('member_id', $member->id)->count(),
            'comments' => $this->commentRepository->countApprovedCommentsByEmail($member->email),
            'pages_read' => $this->pageViewRepository->getUniquePagesViewedByMember($member->id, $siteId),
            'likes' => $this->pageLikeRepository->getMemberLikeCount($member->id, $siteId),
        ];

        // Get recommended pages
        $recommendedPages = $this->pageRepository->getFeaturedPages(6, $siteId);

        return $this->view('member/dashboard', [
            'member' => $memberObj,
            'site' => SiteContext::get(),
            'stats' => $stats,
            'recommendedPages' => $recommendedPages,
            'progress' => $progress,
            'activity_trends' => $activityTrends,
            'recent_activities' => $recentActivities,
            'badges' => $memberObj->badges ?? collect()
        ]);
    }

    private function getNewsletterCount(string $email, int $siteId): int
    {
        return $this->subscriberRepository->getNewslettersForMember($email, $siteId)->count();
    }
}