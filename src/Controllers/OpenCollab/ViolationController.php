<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Enums\OpenCollab\ViolationAction;
use App\Enums\OpenCollab\ViolationSeverity;
use App\Enums\OpenCollab\ViolationType;
use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Requests\OpenCollab\RecordViolationRequest;
use App\Services\OpenCollab\ViolationService;

/**
 * Routes:
 *   GET  /api/{site}/open-collab/admin/violations                           — site-wide list
 *   GET  /api/{site}/open-collab/admin/contributors/{userId}/violations     — per-contributor list
 *   POST /api/{site}/open-collab/admin/contributors/{userId}/violations     — record
 *   POST /api/{site}/open-collab/admin/violations/{id}/resolve              — resolve
 */
class ViolationController extends Controller
{
    public function __construct(
        private readonly ViolationService $violationService,
        private readonly \App\Repositories\OpenCollab\ViolationRepository $violationRepository,
    )
    {
        parent::__construct();
    }

    /**
     * GET /api/{site}/open-collab/admin/violations
     * Site-wide violations list — all contributors on the current site.
     */
    public function siteIndex(): JsonResponse
    {
        $limit = min((int)($_GET['limit'] ?? 100), 200);
        $violations = $this->violationRepository->forSite(SiteContext::getId(), $limit);

        return $this->resourceResponse(
            ['data' => $violations['data']->map(fn($g) => $this->formatViolation($g))->toArray(), 'pagination' => $violations['pagination']]
        );
    }

    /**
     * GET /api/{site}/open-collab/admin/contributors/{userId}/violations
     */
    public function index(int $userId): JsonResponse
    {
        $violations = $this->violationRepository->forContributor($userId, SiteContext::getId());

        return $this->resourceResponse(
            $violations->map(fn($v) => $this->formatViolation($v))->toArray()
        );
    }

    private function formatViolation(\App\Models\ContributorViolation $v): array
    {
        return [
            'id' => $v->id,
            'user_id' => $v->user_id,
            'site_id' => $v->site_id,
            'type' => $v->type,
            'severity' => $v->severity,
            'reason' => $v->reason,
            'action_taken' => $v->action_taken,
            'page_id' => $v->page_id,
            'created_by' => $v->created_by,
            'resolved_at' => $v->resolved_at,
            'resolved_by' => $v->resolved_by,
            'resolution_notes' => $v->resolution_notes,
            'created_at' => $v->created_at,
        ];
    }

    /**
     * POST /api/{site}/open-collab/admin/contributors/{userId}/violations
     */
    public function store(RecordViolationRequest $request, int $userId): JsonResponse
    {
        try {
            $data = $request->validated();

            $actionOverride = isset($data['action_taken'])
                ? ViolationAction::from($data['action_taken'])
                : null;

            $violation = $this->violationService->record(
                userId: $userId,
                siteId: SiteContext::getId(),
                type: ViolationType::from($data['type']),
                severity: ViolationSeverity::from($data['severity']),
                reason: $data['reason'],
                adminId: Auth::id(),
                actionOverride: $actionOverride,
                pageId: isset($data['page_id']) ? (int)$data['page_id'] : null,
            );

            return $this->jsonResponse([
                'violation' => $this->formatViolation($violation),
                'message' => 'Violation recorded.',
            ], 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/{site}/open-collab/admin/violations/{id}/resolve
     */
    public function resolve(int $id): JsonResponse
    {
        $notes = $_POST['notes'] ?? (json_decode(file_get_contents('php://input'), true)['notes'] ?? null);

        try {
            $violation = $this->violationService->resolve($id, Auth::id(), $notes);

            return $this->jsonResponse([
                'violation' => $this->formatViolation($violation),
                'message' => 'Violation resolved.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}