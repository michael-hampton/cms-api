<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\SiteContext;
use App\Repositories\MemberInsights\MemberActivityRepository;
use App\Repositories\Members\BadgeRepository;
use App\Repositories\Members\PageLikeRepository;
use App\Repositories\Quiz\LeaderboardRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Members\BadgeService;
use App\Services\Members\NotificationService;
use App\Services\Offers\DealsService;
use App\Services\Quiz\PollService;
use App\Services\Rewards\RewardsService;

class MemberHubApiController extends Controller
{
    public function __construct(
        private readonly PageLikeRepository       $pageLikeRepository,
        private readonly MemberActivityRepository $activityRepository,
        private readonly NotificationService      $notificationService,
        private readonly BadgeService             $badgeService,
        private readonly BadgeRepository          $badgeRepository,
        private readonly SubscriptionRepository   $subscriptionRepository,
        private readonly DealsService             $dealsService,
        private readonly PollService              $pollService,
        private readonly LeaderboardRepository    $leaderboardRepository,
        private readonly RewardsService           $rewardsService
    )
    {
        parent::__construct();
    }

    public function subscriptions()
    {
        if (!MemberAuth::check()) {
            return $this->resourceResponse(['success' => false], 401);
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        $active = $this->subscriptionRepository->getActiveSubscriptionForMember($member->id, $siteId);
        $history = $this->subscriptionRepository->getSubscriptionHistory($member->id, $siteId);

        return $this->resourceResponse([
            'success' => true,
            'data' => [
                'active' => $active ? [
                    'id' => $active->id,
                    'plan_name' => $active->plan_name,
                    'status' => $active->status,
                    'end_date' => $active->end_date?->format('M j, Y'),
                    'auto_renew' => (bool)$active->auto_renew,
                    'billing_period' => $active->plan->billing_period ?? null,
                    'price' => $active->price,
                    'currency' => $active->currency,
                ] : null,
                'history_count' => $history->count(),
            ],
        ]);
    }

    public function badges()
    {
        if (!MemberAuth::check()) {
            return $this->resourceResponse(['success' => false], 401);
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        $member->load(['badges', 'points']);

        $earned = $this->badgeRepository->getEarnedBadges($member);
        $recentActivity = $this->activityRepository->getMemberActivities($member->id, 10);
        $progress = $this->badgeService->getMemberProgress($member, $siteId);

        return $this->resourceResponse([
            'success' => true,
            'data' => [
                'total_points' => $progress['total_points'],
                'badges_earned' => $progress['badges_earned'],
                'earned' => $earned->map(fn($b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'description' => $b->description,
                    'icon' => $b->icon,
                    'points' => $b->points,
                    'category' => $b->category,
                ])->values()->toArray(),
                'next_badges' => array_map(fn($n) => [
                    'name' => $n['badge']->name,
                    'icon' => $n['badge']->icon,
                    'percentage' => round($n['progress']['percentage']),
                ], $progress['next_badges']),
                'recent_activity' => $recentActivity->map(fn($a) => [
                    'type' => $a->activity_type,
                    'points' => $a->points,
                    'date' => $a->activity_date->format('M j'),
                    'entity_type' => $a->entity_type,
                ])->values()->toArray(),
            ],
        ]);
    }

    public function notifications()
    {
        if (!MemberAuth::check()) {
            return $this->resourceResponse(['success' => false], 401);
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        $notifications = $this->notificationService->getNotifications($member, $siteId);

        return $this->resourceResponse([
            'success' => true,
            'data' => $notifications,
            'count' => count($notifications),
        ]);
    }

    public function markNotificationsRead()
    {
        if (!MemberAuth::check()) {
            return $this->resourceResponse(['success' => false], 401);
        }

        // Clear badge session flags
        unset($_SESSION['new_badges_earned'], $_SESSION['new_badge_earned'], $_SESSION['new_badge_data']);

        return $this->resourceResponse(['success' => true]);
    }

    public function feed()
    {
        if (!MemberAuth::check()) {
            return $this->resourceResponse(['success' => false], 401);
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        $notifications = $this->notificationService->getNotifications($member, $siteId);
        $recentActivity = $this->activityRepository->getMemberActivities($member->id, 15);

        $activityItems = $recentActivity->map(function ($a) {
            return [
                'id' => $a->id,
                'type' => $a->activity_type,
                'points' => (int)$a->points,
                'entity_type' => $a->entity_type,
                'entity_id' => $a->entity_id,
                'date' => $a->activity_date instanceof \DateTime
                    ? $a->activity_date->format('M j')
                    : date('M j', strtotime((string)$a->activity_date)),
                'date_full' => $a->activity_date instanceof \DateTime
                    ? $a->activity_date->format('Y-m-d H:i:s')
                    : (string)$a->activity_date,
            ];
        })->values()->toArray();

        return $this->resourceResponse([
            'success' => true,
            'notifications' => $notifications,
            'activity' => $activityItems,
        ]);
    }

    // ── Saved (liked pages) ───────────────────────────────────────────────────

    public function saved()
    {
        if (!MemberAuth::check()) {
            return $this->resourceResponse(['success' => false], 401);
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        $saves = $this->pageLikeRepository->getMemberSavedPages($member->id, $siteId, 30);

        $items = $saves->map(function ($save) {
            $page = $save->page ?? null;
            if (!$page) return null;

            $imageUrl = $page->resolved_images['listing-card']['image_url']
                ?? $page->image->url
                ?? null;

            return [
                'id' => $save->id,
                'page_id' => $page->id,
                'title' => $page->title,
                'url' => '/' . SiteContext::slug() . $page->getUrlAttribute(),
                'image_url' => $imageUrl,
                'category' => $page->categories->first()?->name ?? null,
                'saved_at_label' => $this->relativeDate($save->liked_at),
            ];
        })->filter()->values()->toArray();

        return $this->resourceResponse(['success' => true, 'data' => $items]);
    }

    private function relativeDate(mixed $date): string
    {
        if (!$date) return '';

        $ts = $date instanceof \DateTime ? $date->getTimestamp() : strtotime((string)$date);
        $diff = time() - $ts;

        if ($diff < 3600) return 'Just now';
        if ($diff < 86400) return 'Today';
        if ($diff < 172800) return 'Yesterday';
        if ($diff < 604800) return date('l', $ts);

        return date('M j', $ts);
    }

    public function toggleSave(\App\Framework\Http\Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->resourceResponse(['success' => false], 401);
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();
        $pageId = (int)$request->input('page_id');

        $result = $this->pageLikeRepository->toggleSave($pageId, $member->id, $siteId);

        return $this->resourceResponse([
            'success' => true,
            'saved' => $result['active'],
            'count' => $result['count'],
        ]);
    }

    // ── Deals ────────────────────────────────────────────────────────────────

    public function unsave(int $pageId)
    {
        if (!MemberAuth::check()) {
            return $this->resourceResponse(['success' => false], 401);
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        // toggleLike returns ['liked' => bool, 'count' => int]
        $result = $this->pageLikeRepository->toggleLike($pageId, $member->id, $siteId);

        return $this->resourceResponse([
            'success' => true,
            'liked' => $result['liked'],
            'count' => $result['count'],
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function deals()
    {
        if (!MemberAuth::check()) {
            return $this->resourceResponse(['success' => false], 401);
        }

        $siteId = SiteContext::getId();

        // Featured: today's top 3
        $featured = $this->dealsService->getTodaysDeals(3, $siteId);

        // Filtered browse: top 9 by discount, basic sorting
        $filtered = $this->dealsService->getFilteredDeals([
            'sort' => 'sale_price:asc',
            'on_sale' => '1',
            'per_page' => 9,
            'page' => 1,
        ], $siteId);

        return $this->resourceResponse([
            'success' => true,
            'featured' => $featured,
            'browse' => $filtered['data'] ?? [],
            'total' => $filtered['total'] ?? 0,
        ]);
    }

    public function community()
    {
        $siteId = SiteContext::getId();
        $member = MemberAuth::check() ? MemberAuth::getMember() : null;

        $polls = $this->pollService->getActivePollsForSite($siteId, $member);

        $pointsBoard = $this->leaderboardRepository
            ->getTopForWeek($siteId, 'points', 5)
            ->map(fn($e) => [
                'display_name' => $e->member->display_name
                    ?? trim(($e->member->first_name ?? '') . ' ' . ($e->member->last_name ?? ''))
                    ?: 'Member',
                'score' => $e->score,
                'rank' => $e->rank,
            ])->values()->toArray();

        $activityBoard = $this->leaderboardRepository
            ->getTopForWeek($siteId, 'activity', 5)
            ->map(fn($e) => [
                'display_name' => $e->member->display_name
                    ?? trim(($e->member->first_name ?? '') . ' ' . ($e->member->last_name ?? ''))
                    ?: 'Member',
                'score' => $e->score,
                'rank' => $e->rank,
            ])->values()->toArray();

        $memberRanks = null;
        $rewards = [];

        if ($member) {
            $ptRank = $this->leaderboardRepository->getMemberRank($member->id, $siteId, 'points');
            $actRank = $this->leaderboardRepository->getMemberRank($member->id, $siteId, 'activity');

            $memberRanks = [
                'points' => $ptRank?->rank,
                'activity' => $actRank?->rank,
            ];

            $unclaimed = $this->rewardsService->getUnclaimedRewards($member, $siteId);
            $rewards = $unclaimed->map(fn($r) => [
                'id' => $r->id,
                'title' => $r->title ?? $r->name ?? 'Reward',
                'description' => $r->description ?? null,
                'expires_at' => $r->expires_at?->format('M j') ?? null,
            ])->values()->toArray();
        }

        return $this->resourceResponse([
            'success' => true,
            'polls' => $polls,
            'leaderboard' => [
                'points' => $pointsBoard,
                'activity' => $activityBoard,
            ],
            'member_ranks' => $memberRanks,
            'rewards' => $rewards,
        ]);
    }

    public function castVote(\App\Framework\Http\Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->resourceResponse(['success' => false, 'message' => 'Login to vote'], 401);
        }

        $member = MemberAuth::getMember();
        $pollId = (int)$request->input('poll_id');
        $optionId = (int)$request->input('option_id');

        $result = $this->pollService->castVote($pollId, $optionId, $member);

        return $this->resourceResponse($result, $result['success'] ? 200 : 400);
    }
}