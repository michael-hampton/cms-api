<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Site;
use App\Services\OpenCollab\ContributorRequestService;

/**
 * Self-service contributor registration and admin review queue.
 *
 * Routes:
 *   POST /api/{site}/open-collab/contributor-requests              — public: submit request
 *   GET  /api/{site}/open-collab/admin/contributor-requests        — admin: list pending
 *   POST /api/{site}/open-collab/admin/contributor-requests/{id}/approve — admin: approve
 *   POST /api/{site}/open-collab/admin/contributor-requests/{id}/reject  — admin: reject
 */
class ContributorRequestController extends Controller
{
    public function __construct(
        private readonly ContributorRequestService $requestService,
    )
    {
        parent::__construct();
    }

    /**
     * POST /api/{site}/open-collab/contributor-requests
     * Public — no auth required.
     */
    public function store(Request $request): JsonResponse
    {
        $body = $request->all();

        $email = trim($body['email'] ?? '');
        $name = trim($body['name'] ?? '');
        $bio = trim($body['bio'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->errorResponse('A valid email address is required.', 422);
        }
        if (mb_strlen($name) < 2) {
            return $this->errorResponse('Name must be at least 2 characters.', 422);
        }
        if (mb_strlen($bio) < 20) {
            return $this->errorResponse('Bio must be at least 20 characters.', 422);
        }

        $siteId = SiteContext::getId();
        $site = Site::find($siteId);

        if (!$site) {
            return $this->errorResponse('Site not found.', 404);
        }

        $requiresApproval = (bool)($site->require_invite_approval ?? true);

        try {
            $result = $this->requestService->submit(
                email: $email,
                name: $name,
                bio: $bio,
                siteId: $siteId,
                requiresApproval: $requiresApproval,
            );

            return $this->jsonResponse([
                'requires_approval' => $result['requires_approval'],
                'message' => $result['requires_approval']
                    ? 'Your request has been received and is pending review.'
                    : 'Invitation sent — check your inbox.',
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * GET /api/{site}/open-collab/admin/contributor-requests
     */
    public function index(): JsonResponse
    {
        $requests = $this->requestService->pendingForSite(SiteContext::getId());

        return $this->jsonResponse(
            $requests->map(fn($r) => $this->formatRequest($r))->toArray()
        );
    }

    private function formatRequest(\App\Models\ContributorRequest $r): array
    {
        return [
            'id' => $r->id,
            'email' => $r->email,
            'name' => $r->name,
            'bio' => $r->bio,
            'status' => $r->status,
            'created_at' => $r->created_at,
        ];
    }

    /**
     * POST /api/{site}/open-collab/admin/contributor-requests/{id}/approve
     */
    public function approve(int $id): JsonResponse
    {
        try {
            $invitation = $this->requestService->approve($id, Auth::id());

            return $this->jsonResponse([
                'message' => 'Request approved — invitation dispatched.',
                'invitation' => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'expires_at' => $invitation->expires_at,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/{site}/open-collab/admin/contributor-requests/{id}/reject
     */
    public function reject(int $id): JsonResponse
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $reason = trim($body['reason'] ?? '');

        try {
            $this->requestService->reject($id, Auth::id(), $reason ?: null);

            return $this->successResponse('Request rejected.');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}