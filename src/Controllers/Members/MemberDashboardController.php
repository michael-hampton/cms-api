<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\SiteContext;
use App\Models\Address;
use App\Models\Member;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Members\CommentRepository;
use App\Repositories\Members\MemberActivityRepository;
use App\Repositories\Members\MemberRepository;
use App\Repositories\Members\PageLikeRepository;
use App\Repositories\Members\PageViewRepository;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Subscriptions\SubscriberRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Members\BadgeService;
use App\Services\Recommendations\ContentRecommendationService;
use App\Services\Recommendations\ProductRecommendationService;
use App\Services\Rewards\RewardsService;
use App\Services\Subscriptions\SubscriptionListingService;

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
        private readonly MemberActivityRepository     $activityRepository,
        private readonly ContentRecommendationService $contentRecommendationService,
        private readonly RewardsService               $rewardService,
        private readonly ProductRecommendationService $productRecommendationService,
        private readonly SubscriptionListingService   $subscriptionListingService
    ) {
        parent::__construct();
    }

    public function index()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/' . SiteContext::slug() . '/member/login');
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        $memberObj = Member::with(['badges', 'points'])->find($member->id);

        $progress = $this->badgeService->getMemberProgress($memberObj);

        $recentActivities = $this->activityRepository->getMemberActivities($member->id, 20);

        $activityTrends = $this->badgeService->getActivityTrends($memberObj, 30);

        $recommendedPages = [];
        $trendingPages = [];
        $trendingConversations = [];
        $recommendedProducts = [];

        if ($member->isEmailVerified()) {
            try {
                $recommendedPages = $this->contentRecommendationService
                    ->getRecommendedForMember($member, $siteId, 6);

                $trendingPages = $this->contentRecommendationService
                    ->getTrendingContent($siteId, 3);

                $trendingConversations = $this->contentRecommendationService
                    ->getTrendingConversations($siteId, 3);

                // Get product recommendations
                $recommendedProducts = $this->productRecommendationService
                    ->getFormattedRecommendations($member, $siteId, 6);
            } catch (\Exception $e) {
                // Log error but don't break dashboard
                \App\Framework\Support\Logger::error('Failed to load recommendations', [
                    'member_id' => $member->id,
                    'error' => $e->getMessage()
                ]);
            }
        }


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

        $this->rewardService->checkAndAwardRewards($member, $siteId);

        $unclaimedRewards = $this->rewardService->getUnclaimedRewards($member, $siteId);

        $giftedArticles = [];
        if ($member->isEmailVerified()) {
            try {
                $giftingService = new \App\Services\Members\ArticleGiftingService(
                    new \App\Repositories\Members\GiftedArticleRepository()
                );

                $gifts = $giftingService->getGiftedArticlesForMember($member, $siteId);
                $giftedArticles = [
                    'received' => $gifts['received']->take(5), // Show latest 5
                    'given' => $gifts['given']->take(5),
                    'received_count' => $gifts['received']->count(),
                    'given_count' => $gifts['given']->count(),
                ];
            } catch (\Exception $e) {
                \App\Framework\Support\Logger::error('Failed to load gifted articles', [
                    'member_id' => $member->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $groupedSubscriptions = [];
        if ($member->isEmailVerified()) {
            try {
                $groupedSubscriptions = $this->subscriptionListingService->getGroupedSubscriptions(
                    $member->id,
                    $siteId
                );
            } catch (\Exception $e) {
                \App\Framework\Support\Logger::error('Failed to load subscriptions', [
                    'member_id' => $member->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $this->view('member/dashboard', [
            'member' => $memberObj,
            'site' => SiteContext::get(),
            'stats' => $stats,
            'recommendedPages' => $recommendedPages,
            'recommendedProducts' => $recommendedProducts,
            'progress' => $progress,
            'activity_trends' => $activityTrends,
            'recent_activities' => $recentActivities,
            'badges' => $memberObj->badges ?? collect(),
            'trendingPages' => $trendingPages,
            'trendingConversations' => $trendingConversations,
            'unclaimedRewards' => $unclaimedRewards,
            'giftedArticles' => $giftedArticles,
            'groupedSubscriptions' => $groupedSubscriptions,
        ]);
    }

    private function getNewsletterCount(string $email, int $siteId): int
    {
        return $this->subscriberRepository->getNewslettersForMember($email, $siteId)->count();
    }
}