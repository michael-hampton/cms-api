<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Resource\ResourceCollection;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\ActivityRepository;
use App\Resources\OpenCollab\ActivityEventResource;

/**
 * Routes:
 *   GET /api/{site}/open-collab/activity          — contributor's own feed
 *   GET /api/{site}/open-collab/activity/site     — site-wide feed (admin only)
 */
class ActivityFeedController extends Controller
{
    public function __construct(
        private readonly ActivityRepository $activityRepository,
    )
    {
        parent::__construct();
    }

    /**
     * GET /api/{site}/open-collab/activity
     * Returns the authenticated contributor's latest activity events.
     */
    public function index(): JsonResponse
    {
        $events = $this->activityRepository->forContributor(
            userId: Auth::id(),
            limit: 30,
        );

        $collection = new ResourceCollection($events, ActivityEventResource::class);

        return $this->resourceResponse($collection->toArray());
    }

    /**
     * GET /api/{site}/open-collab/activity/site
     * Site-wide feed. Intended for admin dashboards.
     */
    public function siteWide(): JsonResponse
    {
        $events = $this->activityRepository->forSite(
            siteId: SiteContext::getId(),
            limit: 100,
        );

        $collection = new ResourceCollection($events, ActivityEventResource::class);

        return $this->resourceResponse($collection->toArray());
    }
}