<?php
// src/Controllers/CampaignController.php

namespace App\Controllers;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Repositories\Cms\CampaignRepository;
use App\Requests\CreateCampaignRequest;
use App\Requests\UpdateBrandRequest;
use App\Services\Cms\CampaignService;

class CampaignController extends Controller
{
    public function __construct(
        private readonly CampaignRepository $campaignRepository,
        private readonly CampaignService    $campaignService
    )
    {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $siteId = $request->getSiteId();
            $campaigns = $this->campaignRepository->getBySite($siteId);

            $campaignsData = $campaigns->map(function ($campaign) {
                return array_merge($campaign->toArray(), [
                    'subscriber_count' => $this->campaignRepository->getSubscriberCount($campaign->id),
                    'is_currently_active' => $campaign->isActive(),
                    'has_ended' => $campaign->hasEnded(),
                    'created_at' => $campaign->created_at->format('Y-m-d H:i:s'),
                    'start_date' => $campaign->start_date?->format('Y-m-d H:i:s'),
                    'end_date' => $campaign->end_date?->format('Y-m-d H:i:s'),
                ]);
            });

            return $this->resourceResponse(['campaigns' => $campaignsData->toArray()]);
        } catch (\Exception $e) {
            Logger::error('Failed to fetch campaigns', ['error' => $e->getMessage()]);
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $siteId = $request->getSiteId();
            $campaign = $this->campaignRepository->find($id);

            if (!$campaign || $campaign->site_id !== $siteId) {
                return $this->errorResponse('Campaign not found', 404);
            }

            $data = array_merge($campaign->toArray(), [
                'subscriber_count' => $this->campaignRepository->getSubscriberCount($campaign->id),
                'is_currently_active' => $campaign->isActive(),
                'has_ended' => $campaign->hasEnded(),
            ]);

            return $this->resourceResponse(['campaign' => $data]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function create(CreateCampaignRequest $request): JsonResponse
    {
        try {
            $siteId = SiteContext::getId();

            $data = $request->validated();
            $slug = $data['slug'];

            // Check for duplicate slug
            $existing = $this->campaignRepository->findBySlug($slug, $siteId);
            if ($existing) {
                return $this->errorResponse('Campaign with this slug already exists', 400);
            }

            $campaign = $this->campaignRepository->create($data);

            return $this->jsonResponse(['campaign' => $campaign->toArray()], 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (\Exception $e) {
            Logger::error('Failed to create campaign', ['error' => $e->getMessage()]);
            return $this->errorResponse('Failed to create campaign: ' . $e->getMessage(), 500);
        }
    }

    public function update(UpdateBrandRequest $request, int $id): JsonResponse
    {
        try {
            $siteId = SiteContext::getId();
            $campaign = $this->campaignRepository->find($id);

            if (!$campaign || $campaign->site_id !== $siteId) {
                return $this->errorResponse('Campaign not found', 404);
            }

            // Check slug uniqueness if changing
            $newSlug = $request->input('slug');
            if ($newSlug && $newSlug !== $campaign->slug) {
                $existing = $this->campaignRepository->findBySlug($newSlug, $siteId);
                if ($existing && $existing->id !== $id) {
                    return $this->errorResponse('Campaign with this slug already exists', 400);
                }
            }

            $data = $request->validated();

            $updated = $this->campaignRepository->update($id, $data);

            return $this->successResponse('Campaign updated successfully', [
                'campaign' => $updated->toArray()
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to update campaign', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->errorResponse('Failed to update campaign: ' . $e->getMessage(), 500);
        }
    }

    public function delete(Request $request, int $id): JsonResponse
    {
        try {
            $siteId = $request->getSiteId();
            $campaign = $this->campaignRepository->find($id);

            if (!$campaign || $campaign->site_id !== $siteId) {
                return $this->errorResponse('Campaign not found', 404);
            }

            // Check if campaign has subscribers
            $subscriberCount = $this->campaignRepository->getSubscriberCount($id);
            if ($subscriberCount > 0) {
                return $this->errorResponse(
                    "Cannot delete campaign with {$subscriberCount} subscribers. Deactivate instead.",
                    400
                );
            }

            $this->campaignRepository->delete($id);

            return $this->successResponse('Campaign deleted successfully');
        } catch (\Exception $e) {
            Logger::error('Failed to delete campaign', ['id' => $id, 'error' => $e->getMessage()]);
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
                'message' => 'Campaign cloned successfully'
            ], 201);
        } catch (\Exception $e) {
            Logger::error('Failed to clone campaign', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->errorResponse('Failed to clone campaign: ' . $e->getMessage(), 500);
        }
    }

    public function getActive(Request $request): JsonResponse
    {
        try {
            $siteId = $request->getSiteId();
            $campaigns = $this->campaignService->getActiveCampaignsForDisplay($siteId);

            return $this->resourceResponse(['campaigns' => $campaigns]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function pause(Request $request, int $id): JsonResponse
    {
        try {
            $siteId = $request->getSiteId();
            $result = $this->campaignService->pauseCampaign($id, $siteId);

            if (!$result['success']) {
                return $this->errorResponse($result['error'], $result['code']);
            }

            return $this->successResponse($result['message'], [
                'campaign' => $result['campaign']->toArray()
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to pause campaign', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->errorResponse('Failed to pause campaign: ' . $e->getMessage(), 500);
        }
    }

    public function resume(Request $request, int $id): JsonResponse
    {
        try {
            $siteId = $request->getSiteId();
            $result = $this->campaignService->resumeCampaign($id, $siteId);

            if (!$result['success']) {
                return $this->errorResponse($result['error'], $result['code']);
            }

            return $this->successResponse($result['message'], [
                'campaign' => $result['campaign']->toArray()
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to resume campaign', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->errorResponse('Failed to resume campaign: ' . $e->getMessage(), 500);
        }
    }
}