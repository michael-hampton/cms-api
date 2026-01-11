<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Services\Rewards\RewardsService;

class RewardsController extends Controller
{
    public function __construct(
        private RewardsService $rewardsService
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

        return $this->view('member/rewards/index', [
            'member' => $member,
            'site' => SiteContext::get(),
            'rewards' => $rewards,
            'unclaimedRewards' => $unclaimed
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
}