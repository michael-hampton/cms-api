<?php

namespace App\Controllers\Members\Api;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Repositories\MemberInsights\MemberActivityRepository;
use App\Repositories\Members\BadgeRepository;
use App\Services\Members\BadgeService;

class MemberActivityApiController extends Controller
{
    public function __construct(
        private readonly BadgeService             $badgeService,
        private readonly MemberActivityRepository $activityRepository,
        private readonly BadgeRepository          $badgeRepository
    )
    {
        parent::__construct();
    }

    /**
     * GET /api/member/activity
     * Returns activity dashboard data: progress, recent activities, trends.
     */
    public function index(): JsonResponse
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::getMember();
        $member->load(['badges', 'points']);

        $progress = $this->badgeService->getMemberProgress($member);
        $recentActivities = $this->activityRepository->getMemberActivities($member->id, 20);
        $activityTrends = $this->badgeService->getActivityTrends($member, 30);
        $badges = $member->badges();

        return $this->resourceResponse([
            'success' => true,
            'data' => [
                'member' => $member->toArray(),
                'progress' => $progress,
                'member_badges' => $badges,
                'recent_activities' => $recentActivities->map(function ($likedPage) {
                    return array_merge(
                        $likedPage->toArray(),
                        [
                            'created_at' => $likedPage->created_at?->format('Y-m-d H:i:s'),
                            'activity_date' => $likedPage->activity_date?->format('Y-m-d H:i:s'),
                        ]
                    );
                }),
                'activity_trends' => $activityTrends,
            ],
        ]);
    }

    /**
     * GET /api/member/badges
     * Returns all earned and unearned badges for the member.
     */
    public function badges(): JsonResponse
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::getMember();
        $member->load(['badges']);

        $siteId = SiteContext::getId();

        $allBadges = $this->badgeRepository->getActiveBadges($siteId);
        $earnedBadges = $member->badges;
        $unearnedBadges = $allBadges->filter(function ($badge) use ($earnedBadges) {
            return !$earnedBadges->contains('id', $badge->id);
        });

        $categories = $allBadges->pluck('category')
            ->unique()
            ->filter()
            ->values()
            ->all();

        return $this->resourceResponse([
            'success' => true,
            'data' => [
                'earned_badges' => $earnedBadges,
                'unearned_badges' => $unearnedBadges->values(),
                'categories' => $categories,
            ],
        ]);
    }
}