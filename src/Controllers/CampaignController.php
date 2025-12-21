<?php
// src/Controllers/CampaignController.php

namespace App\Controllers;

use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\Logger;
use App\Repositories\CampaignRepository;
use App\Services\CampaignService;

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
                    'start_date' => $campaign->start_date->format('Y-m-d H:i:s'),
                    'end_date' => $campaign->end_date->format('Y-m-d H:i:s'),
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

    public function create(Request $request): JsonResponse
    {
        try {
            $siteId = $request->getSiteId();

            // Validate required fields
            $name = $request->input('name');
            $slug = $request->input('slug');

            if (!$name || !$slug) {
                return $this->errorResponse('Name and slug are required', 400);
            }

            // Check for duplicate slug
            $existing = $this->campaignRepository->findBySlug($slug, $siteId);
            if ($existing) {
                return $this->errorResponse('Campaign with this slug already exists', 400);
            }

            $data = [
                'site_id' => $siteId,
                'name' => $name,
                'slug' => $slug,
                'description' => $request->input('description'),
                'newsletter_id' => $request->input('newsletter_id'),
                'is_active' => $request->input('is_active', true),
                'gates_premium_content' => $request->input('gates_premium_content', false),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'tracking_params' => $request->input('tracking_params', []),
            ];

            $campaign = $this->campaignRepository->create($data);

            return $this->jsonResponse(['campaign' => $campaign->toArray()], 201);
        } catch (\Exception $e) {
            Logger::error('Failed to create campaign', ['error' => $e->getMessage()]);
            return $this->errorResponse('Failed to create campaign: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $siteId = $request->getSiteId();
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

            $data = array_filter([
                'name' => $request->input('name'),
                'slug' => $newSlug,
                'description' => $request->input('description'),
                'newsletter_id' => $request->input('newsletter_id'),
                'is_active' => $request->input('is_active'),
                'gates_premium_content' => $request->input('gates_premium_content'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'tracking_params' => $request->input('tracking_params'),
            ], fn($value) => $value !== null);

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
}