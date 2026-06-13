<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Controllers\OpenCollab\Concerns\AuthorizesSitePermissions;
use App\Exceptions\OpenCollab\ModerationQueueClaimConflictException;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Resource\ResourceCollection;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\ModerationQueueRepository;
use App\Repositories\OpenCollab\ModerationEscalationRepository;
use App\Repositories\OpenCollab\RiskMarkerRepository;
use App\Requests\OpenCollab\ModerationQueueIndexRequest;
use App\Resources\OpenCollab\ModerationDetailResource;
use App\Resources\OpenCollab\ModerationQueueEntryResource;
use App\Services\OpenCollab\Moderation\Governance\ContentGovernanceGate;
use App\Services\OpenCollab\Moderation\ModerationQueueService;
use App\Services\OpenCollab\OpenCollabAuthorizationService;
use App\Repositories\Cms\SiteRepository;

/**
 * Routes:
 *   GET  /api/{site}/open-collab/admin/moderation
 *   GET  /api/{site}/open-collab/admin/moderation/{queueEntryId}
 *   POST /api/{site}/open-collab/admin/moderation/{queueEntryId}/claim
 *   POST /api/{site}/open-collab/admin/moderation/{queueEntryId}/release
 */
class ModerationQueueController extends Controller
{
    use AuthorizesSitePermissions;

    public function __construct(
        private readonly ModerationQueueRepository $queueRepository,
        private readonly ModerationQueueService $queueService,
        private readonly RiskMarkerRepository $riskMarkerRepository,
        private readonly ModerationEscalationRepository $escalationRepository,
        private readonly ContentGovernanceGate $governanceGate,
        private readonly OpenCollabAuthorizationService $authorization,
        private readonly SiteRepository $siteRepository,
    ) {
        parent::__construct();
    }

    public function index(ModerationQueueIndexRequest $request): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['pages.review', 'pages.assign_review', 'content.review', 'content.assign_review'])) {
            return $response;
        }

        $filters = $request->validated();
        $entries = $this->queueRepository->forSite(SiteContext::getId(), array_filter($filters));

        $site = $this->siteRepository->find(SiteContext::getId());

        $collection = new ResourceCollection($entries, ModerationQueueEntryResource::class);

        return $this->resourceResponse($collection->toArray());
    }

    public function show(int $queueEntryId): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['pages.review', 'content.review'])) {
            return $response;
        }

        $entry = $this->queueRepository->find($queueEntryId);

        if ($entry === null || (int)$entry->site_id !== SiteContext::getId()) {
            return $this->errorResponse('Queue entry not found.', 404);
        }

        return $this->resourceResponse(
            (new ModerationDetailResource(
                $entry
            ))->toArray()
        );
    }

    public function claim(int $queueEntryId): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['pages.assign_review', 'content.assign_review', 'pages.review', 'content.review'])) {
            return $response;
        }

        $entry = $this->queueRepository->find($queueEntryId);

        if ($entry === null || (int)$entry->site_id !== SiteContext::getId()) {
            return $this->errorResponse('Queue entry not found.', 404);
        }

        try {
            $entry = $this->queueService->claim($queueEntryId, Auth::id(), SiteContext::getId());
        } catch (ModerationQueueClaimConflictException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        }

        return $this->resourceResponse((new ModerationQueueEntryResource($entry))->toArray());
    }

    public function release(int $queueEntryId): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['pages.assign_review', 'content.assign_review', 'pages.review', 'content.review'])) {
            return $response;
        }

        $entry = $this->queueRepository->find($queueEntryId);

        if ($entry === null || (int)$entry->site_id !== SiteContext::getId()) {
            return $this->errorResponse('Queue entry not found.', 404);
        }

        try {
            $entry = $this->queueService->release($queueEntryId, Auth::id(), SiteContext::getId());
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->resourceResponse((new ModerationQueueEntryResource($entry))->toArray());
    }
}