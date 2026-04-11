<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\AdminContributorRepository;
use App\Repositories\OpenCollab\InvitationRepository;
use App\Requests\OpenCollab\CloseContributorAccountRequest;
use App\Services\OpenCollab\ContributorTerminationService;
use App\Services\OpenCollab\InvitationService;
use App\Services\OpenCollab\SiteAccessService;

/**
 * Admin contributor management area.
 *
 * Routes:
 *   GET  /api/{site}/open-collab/admin/contributors                    — list / search
 *   GET  /api/{site}/open-collab/admin/contributors/{id}               — show profile
 *   POST /api/{site}/open-collab/admin/contributors/{id}/deactivate    — deactivate
 *   POST /api/{site}/open-collab/admin/contributors/{id}/reactivate    — reactivate
 *   POST /api/{site}/open-collab/admin/contributors/{id}/close         — full closure
 *   POST /api/{site}/open-collab/admin/contributors/{id}/grant-access  — grant site access
 *   POST /api/{site}/open-collab/admin/contributors/{id}/revoke-access — revoke site access
 *
 *   GET  /api/{site}/open-collab/admin/invitations                     — list all invitations
 *   POST /api/{site}/open-collab/admin/invitations/{id}/resend         — resend (create new)
 *   DELETE /api/{site}/open-collab/admin/invitations/{id}              — revoke
 */
class AdminContributorController extends Controller
{
    public function __construct(
        private readonly AdminContributorRepository    $contributorRepository,
        private readonly ContributorTerminationService $terminationService,
        private readonly SiteAccessService             $siteAccessService,
        private readonly InvitationService             $invitationService,
        private readonly InvitationRepository          $invitationRepository,
    )
    {
        parent::__construct();
    }

    // ── Contributors ──────────────────────────────────────────────────────────

    /**
     * GET /api/{site}/open-collab/admin/contributors
     */
    public function index(Request $request): JsonResponse
    {
        $query = $request->get('q');
        $perPage = (int)$request->get('per_page', 25);

        $results = $this->contributorRepository->searchForSite(SiteContext::getId(), $query, $perPage);

        return $this->resourceResponse($this->formatPaginatedUsers($results));
    }

    private function formatPaginatedUsers(array $result): array
    {
        // Adapt to however the framework returns paginated results
        $items = $result['data'] ?? $result;
        if (is_object($items) && method_exists($items, 'toArray')) {
            $items = $items->toArray();
        }

        return array_map(fn($u) => $this->formatUser($u), (array)$items);
    }

    private function formatUser(\App\Models\User|array $user): array
    {
        $values = is_array($user) ? $user : $user->toArray();

        return [
            'id' => $values['id'] ?? null,
            'name' => $values['name'] ?? null,
            'email' => $values['email'] ?? null,
            'is_active' => (bool)($values['is_active'] ?? false),
            'is_contributor' => (bool)($values['is_contributor'] ?? false),
            'created_at' => $values['created_at'] ?? null,
        ];
    }

    /**
     * GET /api/{site}/open-collab/admin/contributors/{id}
     */
    public function show(int $id): JsonResponse
    {
        $contributor = $this->contributorRepository->findContributorForSite($id, SiteContext::getId());

        if (!$contributor) {
            return $this->errorResponse('Contributor not found.', 404);
        }

        return $this->jsonResponse(['contributor' => $this->formatUser($contributor)]);
    }

    /**
     * POST /api/{site}/open-collab/admin/contributors/{id}/deactivate
     * Soft deactivate — sets is_active = false. Account is preserved.
     */
    public function deactivate(int $id): JsonResponse
    {
        $contributor = $this->contributorRepository->findContributorForSite($id, SiteContext::getId());

        if (!$contributor) {
            return $this->errorResponse('Contributor not found.', 404);
        }

        $model = is_array($contributor) ? \App\Models\User::find($contributor['id']) : $contributor;
        $model?->update(['is_active' => false]);

        return $this->successResponse('Contributor deactivated.');
    }

    /**
     * POST /api/{site}/open-collab/admin/contributors/{id}/reactivate
     */
    public function reactivate(int $id): JsonResponse
    {
        $contributor = $this->contributorRepository->findContributorForSite($id, SiteContext::getId());

        if (!$contributor) {
            return $this->errorResponse('Contributor not found.', 404);
        }

        $model = is_array($contributor) ? \App\Models\User::find($contributor['id']) : $contributor;
        $model?->update(['is_active' => true]);

        return $this->successResponse('Contributor reactivated.');
    }

    /**
     * POST /api/{site}/open-collab/admin/contributors/{id}/close
     * Full account closure — irreversible through the normal UI.
     */
    public function close(CloseContributorAccountRequest $request, int $id): JsonResponse
    {
        try {
            $data = $request->validated();

            $this->terminationService->close(
                userId: $id,
                siteId: SiteContext::getId(),
                adminId: Auth::id(),
                reason: $data['reason'],
            );

            return $this->successResponse('Contributor account closed.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    // ── Invitations ───────────────────────────────────────────────────────────

    /**
     * POST /api/{site}/open-collab/admin/contributors/{id}/grant-access
     */
    public function grantAccess(int $id): JsonResponse
    {
        $this->siteAccessService->grantAccess($id, SiteContext::getId());

        return $this->successResponse('Site access granted.');
    }

    /**
     * POST /api/{site}/open-collab/admin/contributors/{id}/revoke-access
     */
    public function revokeAccess(int $id): JsonResponse
    {
        $this->siteAccessService->revokeAccess($id, SiteContext::getId());

        return $this->successResponse('Site access revoked.');
    }

    /**
     * GET /api/{site}/open-collab/admin/invitations
     */
    public function invitations(): JsonResponse
    {
        $invitations = $this->invitationRepository->getAllForSite(SiteContext::getId());

        return $this->resourceResponse(
            $invitations->map(fn($inv) => $this->formatInvitation($inv))->toArray()
        );
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function formatInvitation(\App\Models\Invitation $inv): array
    {
        return [
            'id' => $inv->id,
            'email' => $inv->email,
            'status' => $inv->resolveStatus()->value,
            'expires_at' => $inv->expires_at,
            'used_at' => $inv->used_at,
            'revoked_at' => $inv->revoked_at,
            'created_at' => $inv->created_at,
        ];
    }

    /**
     * POST /api/{site}/open-collab/admin/invitations/{id}/resend
     * Creates a new invitation for the same email (old one may be expired/used).
     */
    public function resendInvitation(int $id): JsonResponse
    {
        $existing = $this->invitationRepository->find($id);

        if (!$existing || $existing->site_id !== SiteContext::getId()) {
            return $this->errorResponse('Invitation not found.', 404);
        }

        try {
            $newInvitation = $this->invitationService->create(
                email: $existing->email,
                invitedBy: Auth::id(),
                siteId: SiteContext::getId(),
            );

            return $this->jsonResponse([
                'invitation' => $this->formatInvitation($newInvitation),
                'message' => 'New invitation created for ' . $existing->email,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * DELETE /api/{site}/open-collab/admin/invitations/{id}
     */
    public function revokeInvitation(int $id): JsonResponse
    {
        try {
            $this->invitationService->revoke($id, Auth::id());

            return $this->successResponse('Invitation revoked.');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}
