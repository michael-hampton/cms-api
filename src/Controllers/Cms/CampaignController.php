<?php

namespace App\Controllers\Cms;

use App\Controllers\Controller;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Repositories\Cms\CampaignRepository;
use App\Requests\CreateCampaignRequest;
use App\Requests\UpdateBrandRequest;
use App\Resources\CampaignResource;
use App\Search\SearchCriteriaParser;
use App\Services\Cms\CampaignService;

class CampaignController extends Controller
{
    public function __construct(
        private readonly CampaignService $campaignService,
        private readonly CampaignRepository $campaignRepository,
        private readonly Logger          $logger,
    )
    {
        parent::__construct();
    }

    public function index(Request $request, string $site): JsonResponse
    {
        try {
            $siteId = $request->getSiteId();
            $criteria = SearchCriteriaParser::fromRequest($request, $site);
            $result = $this->campaignRepository->search($criteria);

            $collection = new PaginatedResourceCollection($result, CampaignResource::class);

            return $this->resourceResponse([
                ...$collection->toArray(),
                'stats' => $this->campaignRepository->getStatsBySite($siteId)
            ],
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch campaigns', ['error' => $e->getMessage()]);
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $result = $this->campaignService->getCampaignWithStats($id, $request->getSiteId());

            if (!$result) {
                return $this->errorResponse('Campaign not found', 404);
            }

            $campaign = $this->campaignRepository->find($id);

            return $this->resourceResponse([
                'campaign' => CampaignResource::make($campaign)->toArray(),
                'subscriber_count' => $result['subscriber_count'],
                'is_active' => $result['is_currently_active'],
                'has_ended' => $result['has_ended'],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function create(CreateCampaignRequest $request): JsonResponse
    {
        try {
            $campaign = $this->campaignService->create(
                $request->validated(), SiteContext::getId()
            );

            return $this->jsonResponse([
                'campaign' => CampaignResource::make($campaign)->toArray(),
            ], 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (\Exception $e) {
            $this->logger->error('Failed to create campaign', ['error' => $e->getMessage()]);
            return $this->errorResponse('Failed to create campaign: ' . $e->getMessage(), 500);
        }
    }

    public function update(UpdateBrandRequest $request, int $id): JsonResponse
    {
        try {
            $siteId = SiteContext::getId();

            $updated = $this->campaignService->update($id, $request->validated(), $siteId);

            return $this->successResponse('Campaign updated successfully', [
                'campaign' => CampaignResource::make($updated)->toArray(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to update campaign', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->errorResponse('Failed to update campaign: ' . $e->getMessage(), 500);
        }
    }

    public function delete(Request $request, int $id): JsonResponse
    {
        try {
            $check = $this->campaignService->canDeleteCampaign($id);

            if (!$check['can_delete']) {
                return $this->errorResponse($check['reason'], 400);
            }

            $this->campaignService->delete($id, SiteContext::getId());

            return $this->successResponse('Campaign deleted successfully');
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete campaign', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->errorResponse('Failed to delete campaign: ' . $e->getMessage(), 500);
        }
    }

    public function clone(Request $request, int $id): JsonResponse
    {
        try {
            $siteId = $request->getSiteId();
            $targetSiteId = $request->input('target_site_id', $siteId);
            $campaign = $this->campaignRepository->find($id);

            if (!$campaign || $campaign->site_id !== $siteId) {
                return $this->errorResponse('Campaign not found', 404);
            }

            $cloned = $this->campaignRepository->cloneForSite($id, $targetSiteId);

            if (!$cloned) {
                return $this->errorResponse('Failed to clone campaign', 500);
            }

            return $this->jsonResponse([
                'campaign' => $cloned->toArray(),
                'message' => 'Campaign cloned successfully',
            ], 201);
        } catch (\Exception $e) {
            $this->logger->error('Failed to clone campaign', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->errorResponse('Failed to clone campaign: ' . $e->getMessage(), 500);
        }
    }

    public function getActive(Request $request): JsonResponse
    {
        try {
            $campaigns = $this->campaignRepository->getActiveCampaigns($request->getSiteId());

            return $this->resourceResponse(
                CampaignResource::collection($campaigns)->toArray(),
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function pause(Request $request, int $id): JsonResponse
    {
        try {
            $result = $this->campaignService->pauseCampaign($id, $request->getSiteId());

            if (!$result['success']) {
                return $this->errorResponse($result['error'], $result['code'] ?? 422);
            }

            return $this->successResponse('Campaign paused', [
                'campaign' => CampaignResource::make($result['campaign'])->toArray(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to pause campaign', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->errorResponse('Failed to pause campaign: ' . $e->getMessage(), 500);
        }
    }

    public function resume(Request $request, int $id): JsonResponse
    {
        try {
            $result = $this->campaignService->resumeCampaign($id, $request->getSiteId());

            if (!$result['success']) {
                return $this->errorResponse($result['error'], $result['code'] ?? 422);
            }

            return $this->successResponse('Campaign resumed', [
                'campaign' => CampaignResource::make($result['campaign'])->toArray(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to resume campaign', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->errorResponse('Failed to resume campaign: ' . $e->getMessage(), 500);
        }
    }
}