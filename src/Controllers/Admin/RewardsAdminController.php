<?php

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\Rewards\RewardsRepository;

class RewardsAdminController extends Controller
{
    public function __construct(
        private RewardsRepository $rewardsRepository
    )
    {
        parent::__construct();
    }

    public function index()
    {
        if (!Auth::check()) {
            return $this->redirect('/admin/login');
        }

        return $this->view('admin/rewards/index', [
            'admin' => Auth::user(),
            'site' => SiteContext::get()
        ]);
    }

    public function search(Request $request)
    {
        if (!Auth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $siteId = SiteContext::getId();
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 50);

        $filters = [
            'status' => $request->input('status'),
            'member_id' => $request->input('member_id'),
            'reward_definition_id' => $request->input('reward_definition_id'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'search' => $request->input('search')
        ];

        $result = $this->rewardsRepository->searchRewards($siteId, $filters, $page, $perPage);
        $stats = $this->rewardsRepository->getRewardStats($siteId);

        return $this->resourceResponse([
            'success' => true,
            'rewards' => $result,
            'stats' => $stats
        ]);
    }

    public function show(Request $request, int $rewardId)
    {
        if (!Auth::check()) {
            return $this->resourceResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $reward = $this->rewardsRepository->findMemberRewardById($rewardId);

        if (!$reward) {
            return $this->resourceResponse(['success' => false, 'message' => 'Reward not found'], 404);
        }

        return $this->resourceResponse([
            'success' => true,
            'reward' => $reward->toArray()
        ]);
    }

    public function update(Request $request, int $rewardId)
    {
        if (!Auth::check()) {
            return $this->resourceResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $reward = $this->rewardsRepository->findMemberRewardById($rewardId);

        if (!$reward) {
            return $this->resourceResponse(['success' => false, 'message' => 'Reward not found'], 404);
        }

        $adminNotes = $request->input('admin_notes');
        if ($adminNotes !== null) {
            $reward->update(['admin_notes' => $adminNotes]);
        }

        return $this->resourceResponse([
            'success' => true,
            'message' => 'Reward updated successfully',
            'reward' => $reward->fresh()->toArray()
        ]);
    }

    public function decline(Request $request, int $rewardId)
    {
        if (!Auth::check()) {
            return $this->resourceResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $reward = $this->rewardsRepository->findMemberRewardById($rewardId);

        if (!$reward) {
            return $this->resourceResponse(['success' => false, 'message' => 'Reward not found'], 404);
        }

        if ($reward->isDeclined()) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Reward is already declined'
            ], 400);
        }

        $reason = $request->input('decline_reason');
        if (empty($reason)) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Decline reason is required'
            ], 400);
        }

        $admin = Auth::user();
        $notes = $request->input('admin_notes');

        $reward->decline($admin->id, $reason, $notes);

        return $this->resourceResponse([
            'success' => true,
            'message' => 'Reward declined successfully',
            'reward' => $reward->fresh()->toArray()
        ]);
    }
}