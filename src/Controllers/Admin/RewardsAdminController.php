<?php

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Framework\Support\SiteContext;
use App\Models\MemberReward;
use App\Repositories\Rewards\RewardsRepository;
use App\Resources\MemberRewardResource;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteriaParser;
use App\Search\SearchEngine;
use App\Services\Rewards\RewardsService;
use Exception;

class RewardsAdminController extends Controller
{
    public function __construct(
        private readonly RewardsRepository $rewardsRepository,
        private readonly RewardsService    $rewardStatisticsService,
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

    public function search(Request $request, string $siteName)
    {
        if (!Auth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $siteId = SiteContext::getId();

        $criteria = SearchCriteriaParser::fromRequest($request, $siteName);
        $configuration = SearchConfigurationFactory::create('reward');
        $engine = new SearchEngine($configuration);

        $queryBuilder = MemberReward::query();
        $result = $engine->search($queryBuilder, $criteria);

        $collection = new PaginatedResourceCollection($result, MemberRewardResource::class);

        $stats = $this->rewardsRepository->getRewardDefinitionStats($siteId);

        return $this->resourceResponse([
            'success' => true,
            'rewards' => $collection->toArray(),
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

    public function getRewardStatistics(int $definitionId): JsonResponse
    {
        try {
            $stats = $this->rewardStatisticsService->getRewardDefinitionStatistics($definitionId);

            return $this->resourceResponse([
                'success' => true,
                'statistics' => $stats
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}