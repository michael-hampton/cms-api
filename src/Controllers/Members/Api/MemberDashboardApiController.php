<?php

namespace App\Controllers\Members\Api;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Models\Member;
use App\Models\Order;
use App\Models\Subscription;
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
use App\Services\Members\ArticleGiftingService;
use App\Services\Members\BadgeService;
use App\Services\Recommendations\ContentRecommendationService;
use App\Services\Recommendations\ProductRecommendationService;
use App\Services\Rewards\RewardsService;
use App\Services\Subscriptions\SubscriptionListingService;

class MemberDashboardApiController extends Controller
{
    public function __construct(
        private readonly OrderRepository              $orderRepository,
        private readonly SubscriptionRepository       $subscriptionRepository,
        private readonly SubscriberRepository         $subscriberRepository,
        private readonly CommentRepository            $commentRepository,
        private readonly MemberRepository             $memberRepository,
        private readonly PageRepository               $pageRepository,
        private readonly NewsletterRepository         $newsletterRepository,
        private readonly PageViewRepository           $pageViewRepository,
        private readonly PageLikeRepository           $pageLikeRepository,
        private readonly BadgeService                 $badgeService,
        private readonly MemberActivityRepository     $activityRepository,
        private readonly ContentRecommendationService $contentRecommendationService,
        private readonly RewardsService               $rewardService,
        private readonly ProductRecommendationService $productRecommendationService,
        private readonly SubscriptionListingService   $subscriptionListingService,
        private readonly ArticleGiftingService        $articleGiftingService
    )
    {
        parent::__construct();
    }

    public function overview(): JsonResponse
    {
        $member = $this->getAuthenticatedMember();
        if ($member instanceof JsonResponse) {
            return $member;
        }

        $siteId = SiteContext::getId();
        $memberObj = Member::with(['badges', 'points'])->find($member->id);

        $recentOrders = Order::where('user_id', $member->id)
            ->where('site_id', $siteId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($order) {
                return array_merge(
                    $order->toArray(),
                    [
                        'created_at' => $order->created_at?->format('Y-m-d H:i:s'),
                    ]
                );
            });

        $allSubscriptions = Subscription::where('member_id', $member->id)
            ->where('site_id', $siteId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return $this->resourceResponse([
            'success' => true,
            'data' => [
                'member' => $memberObj->toArray(),
                'recent_orders' => $recentOrders,
                'all_subscriptions' => $allSubscriptions,
            ],
        ]);
    }

    public function activity(): JsonResponse
    {
        $member = $this->getAuthenticatedMember();
        if ($member instanceof JsonResponse) {
            return $member;
        }

        $siteId = SiteContext::getId();
        $memberObj = Member::with(['badges', 'points'])->find($member->id);

        $progress = $this->badgeService->getMemberProgress($memberObj);
        $recentActivities = $this->activityRepository->getMemberActivities($member->id, 20)?->map(function ($recentActivity) {
            return array_merge(
                $recentActivity->toArray(),
                [
                    'activity_date' => $recentActivity->activity_date?->format('Y-m-d H:i:s'),
                ]
            );
        });

        $activityTrends = $this->badgeService->getActivityTrends($memberObj, 30);

        $this->rewardService->checkAndAwardRewards($member, $siteId);
        $unclaimedRewards = $this->rewardService->getUnclaimedRewards($member, $siteId);

        return $this->resourceResponse([
            'success' => true,
            'data' => [
                'progress' => $progress,
                'activity_trends' => $activityTrends,
                'recent_activities' => $recentActivities,
                'badges' => $memberObj->badges ?? collect(),
                'unclaimed_rewards' => $unclaimedRewards,
            ],
        ]);
    }

    public function discovery(): JsonResponse
    {
        $member = $this->getAuthenticatedMember();
        if ($member instanceof JsonResponse) {
            return $member;
        }

        $siteId = SiteContext::getId();
        $recommendedPages = [];
        $trendingPages = [];
        $trendingConversations = [];
        $recommendedProducts = [];
        $giftedArticles = [];
        $groupedSubscriptions = [];

        if ($member->isEmailVerified()) {
            try {
                $recommendedPages = $this->contentRecommendationService
                    ->getRecommendedForMember($member, $siteId, 6);

                $trendingPages = $this->contentRecommendationService
                    ->getTrendingContent($siteId, 3);

                $trendingConversations = $this->contentRecommendationService
                    ->getTrendingConversations($siteId, 3);

                $recommendedProducts = $this->productRecommendationService
                    ->getFormattedRecommendations($member, $siteId, 6);
            } catch (\Exception $e) {
                \App\Framework\Support\Logger::error('Failed to load recommendations', [
                    'member_id' => $member->id,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $gifts = $this->articleGiftingService->getGiftedArticlesForMember($member, $siteId);
                $giftedArticles = [
                    'received' => $gifts['received']->take(5)->map(function ($gift) {
                        return $this->formatGift($gift);
                    }),
                    'given' => $gifts['given']->take(5)->map(function ($gift) {
                        return $this->formatGift($gift);
                    }),
                    'received_count' => $gifts['received']->count(),
                    'given_count' => $gifts['given']->count(),
                ];
            } catch (\Exception $e) {
                \App\Framework\Support\Logger::error('Failed to load gifted articles', [
                    'member_id' => $member->id,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $groupedSubscriptions = $this->subscriptionListingService->getGroupedSubscriptions(
                    $member->id,
                    $siteId
                );
            } catch (\Exception $e) {
                \App\Framework\Support\Logger::error('Failed to load subscriptions', [
                    'member_id' => $member->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->resourceResponse([
            'success' => true,
            'data' => [
                'recommended_pages' => $recommendedPages,
                'recommended_products' => $recommendedProducts,
                'trending_pages' => $trendingPages,
                'trending_conversations' => $trendingConversations,
                'gifted_articles' => $giftedArticles,
                'grouped_subscriptions' => $groupedSubscriptions,
            ],
        ]);
    }

    private function formatGift($gift): array
    {
        $data = $gift->toArray();

        $data['gifted_at'] = $gift->gifted_at
            ? $gift->gifted_at->format('Y-m-d H:i:s')
            : null;

        return $data;
    }

    public function newsletters(): JsonResponse
    {
        $member = $this->getAuthenticatedMember();
        if ($member instanceof JsonResponse) {
            return $member;
        }

        $siteId = SiteContext::getId();

        $newsletters = $this->subscriberRepository->getAllNewslettersForMember($member->email, $siteId);
        $activeSubscription = $this->subscriptionRepository->getActiveSubscriptionForMember($member->id, $siteId);

        $items = $newsletters->map(function ($subscription) use ($activeSubscription) {
            $newsletter = \App\Models\Newsletter::find($subscription->newsletter_id);
            $canToggle = true;
            $lockReason = '';

            if ($newsletter && $newsletter->isPremium()) {
                if (!$activeSubscription) {
                    $canToggle = false;
                    $lockReason = 'Requires active subscription';
                } elseif (!$activeSubscription->hasPremiumAccess('newsletter', $newsletter->slug)) {
                    $canToggle = false;
                    $lockReason = 'Not included in your plan';
                }
            }

            return [
                'subscription_id' => $subscription->id,
                'newsletter_id' => $newsletter?->id,
                'title' => $newsletter?->title ?? 'Newsletter',
                'interval' => $newsletter?->interval ?? 'periodic',
                'is_active' => $subscription->isActive(),
                'can_toggle' => $canToggle,
                'lock_reason' => $lockReason,
            ];
        });

        return $this->resourceResponse([
            'success' => true,
            'data' => ['newsletters' => $items],
        ]);
    }

    public function rewards(): JsonResponse
    {
        $member = $this->getAuthenticatedMember();
        if ($member instanceof JsonResponse) {
            return $member;
        }

        $siteId = SiteContext::getId();

        $this->rewardService->checkAndAwardRewards($member, $siteId);
        $unclaimedRewards = $this->rewardService->getUnclaimedRewards($member, $siteId);

        $items = $unclaimedRewards->map(fn($r) => [
            'id' => $r->id,
            'name' => $r->rewardDefinition->name,
            'type' => $r->rewardDefinition->reward_type,
            'description' => $r->rewardDefinition->description,
            'reward_data' => $r->reward_data,
        ]);

        return $this->resourceResponse([
            'success' => true,
            'data' => ['unclaimed_rewards' => $items],
        ]);
    }

    public function subscriptions(): JsonResponse
    {
        $member = $this->getAuthenticatedMember();
        if ($member instanceof JsonResponse) {
            return $member;
        }

        $siteId = SiteContext::getId();

        try {
            $groupedSubscriptions = $this->subscriptionListingService->getGroupedSubscriptions(
                $member->id,
                $siteId
            );

            // Dates are Carbon objects — serialize them for JSON
            $serialize = function (array $subs) {
                return array_map(function ($s) {
                    return array_merge($s, [
                        'start_date' => $s['start_date']?->format('Y-m-d'),
                        'end_date' => $s['end_date']?->format('Y-m-d'),
                        'next_billing_date' => $s['next_billing_date']?->format('Y-m-d'),
                    ]);
                }, $subs);
            };

            $grouped = [
                'active' => [
                    'print' => $serialize($groupedSubscriptions['active']['print'] ?? []),
                    'digital' => $serialize($groupedSubscriptions['active']['digital'] ?? []),
                ],
                'expired' => [
                    'print' => $serialize($groupedSubscriptions['expired']['print'] ?? []),
                    'digital' => $serialize($groupedSubscriptions['expired']['digital'] ?? []),
                ],
            ];
        } catch (\Exception $e) {
            \App\Framework\Support\Logger::error('Failed to load subscriptions', [
                'member_id' => $member->id,
                'error' => $e->getMessage(),
            ]);
            $grouped = ['active' => ['print' => [], 'digital' => []], 'expired' => ['print' => [], 'digital' => []]];
        }

        return $this->resourceResponse([
            'success' => true,
            'data' => ['grouped_subscriptions' => $grouped],
        ]);
    }

    private function getAuthenticatedMember(): Member|JsonResponse
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::getMember();

        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        return $member;
    }
}
