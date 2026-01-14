<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\Rewards\RewardsRepository;
use App\Services\Rewards\RewardsService;

class RewardsController extends Controller
{
    public function __construct(
        private readonly RewardsService    $rewardsService,
        private readonly RewardsRepository $rewardsRepository
    )
    {
        parent::__construct();
    }

    public function index()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        $rewards = $this->rewardsService->getMemberRewards($member, $siteId);
        $unclaimed = $this->rewardsService->getUnclaimedRewards($member, $siteId);

        // Get top rewards (not yet available to member)
        $topRewards = $this->rewardsService->getTopRewards($member, $siteId);

        // Get reward stats for summary
        $rewardStats = $this->rewardsService->getRewardStats($member, $siteId);

        return $this->view('member/rewards/index', [
            'member' => $member,
            'site' => SiteContext::get(),
            'rewards' => $rewards,
            'unclaimedRewards' => $unclaimed,
            'topRewards' => $topRewards,
            'rewardStats' => $rewardStats
        ]);
    }

    public function claim(Request $request, int $rewardId)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Please login'
            ], 401);
        }

        $member = MemberAuth::getMember();
        $result = $this->rewardsService->claimReward($rewardId, $member);

        if ($request->wantsJson()) {
            return $this->jsonResponse($result);
        }

        if ($result['success']) {

            return $this->redirect('/member/rewards')
                ->with('message', $result['message']);
        }

        return $this->redirect('/member/rewards')
            ->withErrors(['message' => $result['message']]);
    }

    public function trackClick(Request $request, int $rewardId, string $action)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false], 401);
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        $reward = $this->rewardsService->getMemberRewards($member, $siteId)
            ->where('id', $rewardId)->first();

        if (!$reward) {
            return $this->jsonResponse(['success' => false], 404);
        }

        $validActions = ['view', 'copy_code'];
        if (!in_array($action, $validActions)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
        }

        $this->rewardsRepository->trackClick(
            $rewardId,
            $member->id,
            $siteId,
            $action,
            $request->ip(),
            $request->userAgent()
        );

        return $this->jsonResponse(['success' => true]);
    }
}