<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Controllers\OpenCollab\Concerns\AuthorizesSitePermissions;
use App\Enums\OpenCollab\RiskSeverity;
use App\Enums\OpenCollab\RiskSource;
use App\Enums\OpenCollab\RiskType;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\ModerationQueueRepository;
use App\Requests\OpenCollab\CreateRiskMarkerRequest;
use App\Requests\OpenCollab\DismissRiskMarkerRequest;
use App\Requests\OpenCollab\ResolveRiskMarkerRequest;
use App\Resources\OpenCollab\RiskMarkerResource;
use App\Services\OpenCollab\OpenCollabAuthorizationService;
use App\Services\OpenCollab\Risk\RiskMarkerService;

/**
 * Routes:
 *   POST /api/{site}/open-collab/admin/moderation/{queueEntryId}/risks
 *   POST /api/{site}/open-collab/admin/risks/{riskMarkerId}/resolve
 *   POST /api/{site}/open-collab/admin/risks/{riskMarkerId}/dismiss
 */
class ModerationRiskController extends Controller
{
    use AuthorizesSitePermissions;

    public function __construct(
        private readonly RiskMarkerService $riskMarkerService,
        private readonly ModerationQueueRepository $queueRepository,
        private readonly OpenCollabAuthorizationService $authorization,
    ) {
        parent::__construct();
    }

    public function store(CreateRiskMarkerRequest $request, int $queueEntryId): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['pages.review', 'content.review'])) {
            return $response;
        }

        $entry = $this->queueRepository->find($queueEntryId);

        if ($entry === null || (int)$entry->site_id !== SiteContext::getId()) {
            return $this->errorResponse('Queue entry not found.', 404);
        }

        $data = $request->validated();

        $marker = $this->riskMarkerService->create(
            siteId: $entry->site_id,
            pageId: $entry->page_id,
            pageVersionId: $entry->page_version_id,
            cmsImageId: $data['cms_image_id'] ?? null,
            riskType: RiskType::from($data['risk_type']),
            source: RiskSource::Moderator,
            severity: RiskSeverity::from($data['severity']),
            details: $data['details'] ?? null,
            createdByUserId: Auth::id(),
            queueEntryId: $entry->id,
        );

        return $this->resourceResponse((new RiskMarkerResource($marker)->toArray()), 201);
    }

    public function resolve(ResolveRiskMarkerRequest $request, int $riskMarkerId): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['pages.resolve_risk', 'content.resolve_risk'])) {
            return $response;
        }

        try {
            $marker = $this->riskMarkerService->resolve(
                $riskMarkerId,
                SiteContext::getId(),
                Auth::id(),
                $request->validated()['notes'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->resourceResponse((new RiskMarkerResource($marker)->toArray()));
    }

    public function dismiss(DismissRiskMarkerRequest $request, int $riskMarkerId): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['pages.resolve_risk', 'content.resolve_risk'])) {
            return $response;
        }

        try {
            $marker = $this->riskMarkerService->dismiss(
                $riskMarkerId,
                SiteContext::getId(),
                Auth::id(),
                $request->validated()['notes'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->resourceResponse((new RiskMarkerResource($marker)->toArray()));
    }
}