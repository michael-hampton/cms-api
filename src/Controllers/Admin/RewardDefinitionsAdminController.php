<?php

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Framework\Support\SiteContext;
use App\Models\RewardDefinition;
use App\Repositories\Rewards\RewardDefinitionRepository;
use App\Repositories\Rewards\RewardsRepository;
use App\Requests\CreateRewardRequest;
use App\Requests\UpdateRewardRequest;
use App\Resources\RewardDefinitionResource;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteriaParser;
use App\Search\SearchEngine;

class RewardDefinitionsAdminController extends Controller
{
    public function __construct(
        private readonly RewardsRepository          $rewardsRepository,
        private readonly RewardDefinitionRepository $rewardDefinitionRepository
    )
    {
        parent::__construct();
    }

    public function index()
    {
        if (!Auth::check()) {
            return $this->redirect('/admin/login');
        }

        return $this->view('admin/reward-definitions/index', [
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
        $configuration = SearchConfigurationFactory::create('reward_definition');
        $engine = new SearchEngine($configuration);

        $queryBuilder = RewardDefinition::query();
        $result = $engine->search($queryBuilder, $criteria);

        $collection = new PaginatedResourceCollection($result, RewardDefinitionResource::class);

        $stats = $this->rewardDefinitionRepository->getRewardDefinitionStats($siteId);

        return $this->resourceResponse([
            'success' => true,
            'definitions' => $collection->toArray(),
            'stats' => $stats
        ]);
    }

    public function show(Request $request, int $definitionId)
    {
        if (!Auth::check()) {
            return $this->resourceResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $definition = $this->rewardDefinitionRepository->findRewardDefinitionById($definitionId);

        if (!$definition) {
            return $this->resourceResponse(['success' => false, 'message' => 'Reward definition not found'], 404);
        }

        return $this->resourceResponse([
            'success' => true,
            'definition' => $definition->toArray()
        ]);
    }

    public function create(CreateRewardRequest $request)
    {
        try {
            if (!Auth::check()) {
                return $this->resourceResponse(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $data = $request->validated();

            $data['site_id'] = SiteContext::getId();

            $definition = $this->rewardDefinitionRepository->create($data);

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Reward definition created successfully',
                'definition' => $definition->toArray()
            ], 201);

        } catch (ValidationException $validationException) {
            return $this->errorResponse(
                'Validation failed',
                422,
                $validationException->getErrors()
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(UpdateRewardRequest $request, int $definitionId)
    {
        if (!Auth::check()) {
            return $this->resourceResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $definition = $this->rewardDefinitionRepository->findRewardDefinitionById($definitionId);

        if (!$definition) {
            return $this->resourceResponse(['success' => false, 'message' => 'Reward definition not found'], 404);
        }

        $data = $request->validated();

        $updated = $this->rewardDefinitionRepository->update($definitionId, $data);

        if (!$updated) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Failed to update reward definition'
            ], 400);
        }

        $definition = $this->rewardDefinitionRepository->findRewardDefinitionById($definitionId);

        return $this->resourceResponse([
            'success' => true,
            'message' => 'Reward definition updated successfully',
            'definition' => $definition->toArray()
        ]);
    }

    public function delete(Request $request, int $definitionId)
    {
        if (!Auth::check()) {
            return $this->resourceResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $definition = $this->rewardDefinitionRepository->findRewardDefinitionById($definitionId);

        if (!$definition) {
            return $this->resourceResponse(['success' => false, 'message' => 'Reward definition not found'], 404);
        }

        // Check if there are any member rewards using this definition
        $rewardCount = $this->rewardsRepository->where('reward_definition_id', $definitionId)->count();

        if ($rewardCount > 0) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Cannot delete reward definition with existing member rewards'
            ], 400);
        }

        $deleted = $this->rewardDefinitionRepository->delete($definitionId);

        if (!$deleted) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Failed to delete reward definition'
            ], 400);
        }

        return $this->resourceResponse([
            'success' => true,
            'message' => 'Reward definition deleted successfully'
        ]);
    }
}