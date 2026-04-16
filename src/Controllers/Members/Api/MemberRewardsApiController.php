<?php

namespace App\Controllers\Members\Api;

use App\Controllers\Controller;
use App\Events\Members\RewardClaimedByMember;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\Rewards\RewardsRepository;
use App\Services\Rewards\RewardsService;

class MemberRewardsApiController extends Controller
{
    public function __construct(
        private readonly RewardsService    $rewardService,
        private readonly RewardsRepository $rewardsRepository
    )
    {
        parent::__construct();
    }

    public function rewards(): JsonResponse
    {
        $member = MemberAuth::getMember();

        $siteId = SiteContext::getId();

        $this->rewardService->checkAndAwardRewards($member, $siteId);

        $allRewards = $this->rewardService->getMemberRewards($member, $siteId);
        $unclaimedRewards = $this->rewardService->getUnclaimedRewards($member, $siteId);
        $topRewards = $this->rewardService->getTopRewards($member, $siteId);
        $rewardStats = $this->rewardService->getRewardStats($member, $siteId);

        $serializeReward = fn($r) => [
            'id' => $r->id,
            'status' => $r->status,
            'is_expired' => $r->isExpired(),
            'is_claimed' => $r->isClaimed(),
            'expires_at' => $r->expires_at?->format('Y-m-d'),
            'earned_at' => $r->earned_at?->format('Y-m-d'),
            'claimed_at' => $r->claimed_at?->format('Y-m-d'),
            'reward_data' => $r->reward_data,
            'definition' => [
                'name' => $r->rewardDefinition->name ?? 'Special Reward',
                'type' => $r->rewardDefinition->reward_type ?? 'reward',
                'description' => $r->rewardDefinition->description ?? '',
            ],
        ];

        return $this->resourceResponse([
            'success' => true,
            'data' => [
                'stats' => $rewardStats,
                'unclaimed_rewards' => $unclaimedRewards->map($serializeReward)->values(),
                'claimed_rewards' => $allRewards->filter(fn($r) => $r->isClaimed())->map($serializeReward)->values(),
                'top_rewards' => $topRewards->map(fn($d) => [
                    'name' => $d->name,
                    'type' => $d->reward_type,
                    'description' => $d->description,
                    'criteria' => is_array($d->criteria)
                        ? array_map(fn($c) => $d->formatCriterion($c), $d->criteria)
                        : [],
                ])->values(),
            ],
        ]);
    }

    public function claim(Request $request, int $rewardId)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Please login'], 401);
        }

        $member = MemberAuth::getMember();
        $result = $this->rewardService->claimReward($rewardId, $member);

        event(new RewardClaimedByMember($member->id, $rewardId, SiteContext::getId()));

        return $this->jsonResponse($result);

    }

    public function trackClick(Request $request, int $rewardId, string $action)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false], 401);
        }

        $validActions = ['view', 'copy_code'];
        if (!in_array($action, $validActions)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        $reward = $this->rewardService->getMemberRewards($member, $siteId)
            ->where('id', $rewardId)->first();

        if (!$reward) {
            return $this->jsonResponse(['success' => false], 404);
        }

        $this->rewardsRepository->trackClick(
            $rewardId, $member->id, $siteId, $action,
            $request->ip(), $request->userAgent()
        );

        return $this->jsonResponse(['success' => true]);
    }
}