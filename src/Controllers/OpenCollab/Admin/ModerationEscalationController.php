<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Controllers\OpenCollab\Concerns\AuthorizesSitePermissions;
use App\Enums\OpenCollab\EscalationCategory;
use App\Enums\OpenCollab\RiskSeverity;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\ModerationEscalationRepository;
use App\Requests\OpenCollab\EscalateContentRequest;
use App\Requests\OpenCollab\ResolveEscalationRequest;
use App\Resources\OpenCollab\EscalationResource;
use App\Services\OpenCollab\Moderation\EscalationService;
use App\Services\OpenCollab\OpenCollabAuthorizationService;

/**
 * Routes:
 *   POST /api/{site}/open-collab/admin/moderation/{queueEntryId}/escalate
 *   POST /api/{site}/open-collab/admin/escalations/{id}/assign
 *   POST /api/{site}/open-collab/admin/escalations/{id}/acknowledge
 *   POST /api/{site}/open-collab/admin/escalations/{id}/resolve
 *   GET  /api/{site}/open-collab/admin/escalations
 */
class ModerationEscalationController extends Controller
{
    use AuthorizesSitePermissions;

    public function __construct(
        private readonly EscalationService $escalationService,
        private readonly ModerationEscalationRepository $escalationRepository,
        private readonly OpenCollabAuthorizationService $authorization,
    ) {
        parent::__construct();
    }

    public function index(): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['pages.escalate', 'content.escalate', 'pages.view_high_risk', 'content.view_high_risk'])) {
            return $response;
        }

        $status = request()->query('status');
        $escalations = $this->escalationRepository->forSite(
            SiteContext::getId(),
            array_filter(['status' => $status]),
        );

        return $this->resourceResponse(
            $escalations->map(fn($e) => (new EscalationResource($e)))->toArray(),
        );
    }

    public function store(EscalateContentRequest $request, int $queueEntryId): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['pages.escalate', 'content.escalate'])) {
            return $response;
        }

        $data = $request->validated();

        try {
            $escalation = $this->escalationService->escalate(
                queueEntryId: $queueEntryId,
                category: EscalationCategory::from($data['category']),
                severity: RiskSeverity::from($data['severity']),
                createdByUserId: Auth::id(),
                riskMarkerId: $data['risk_marker_id'] ?? null,
                cmsImageId: $data['cms_image_id'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->resourceResponse((new EscalationResource($escalation)->toArray()), 201);
    }

    public function assign(int $id): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['pages.assign_review', 'content.assign_review'])) {
            return $response;
        }

        $userId = (int) request()->input('user_id');

        try {
            $escalation = $this->escalationService->assign($id, $userId, SiteContext::getId());
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }

        return $this->resourceResponse((new EscalationResource($escalation)->toArray()), 200);
    }

    public function acknowledge(int $id): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['pages.escalate', 'content.escalate'])) {
            return $response;
        }

        try {
            $escalation = $this->escalationService->acknowledge($id, Auth::id(), SiteContext::getId());
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }

        return $this->resourceResponse((new EscalationResource($escalation)->toArray()), 200);
    }

    public function resolve(ResolveEscalationRequest $request, int $id): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['pages.resolve_risk', 'content.resolve_risk'])) {
            return $response;
        }

        $data = $request->validated();

        try {
            $escalation = $this->escalationService->resolve(
                $id,
                Auth::id(),
                SiteContext::getId(),
                $data['resolution'],
                $data['notes'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }

        return $this->resourceResponse((new EscalationResource($escalation)->toArray()), 200);
    }
}
