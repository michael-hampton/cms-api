<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Actions\OpenCollab\ChangeContributorRoleAction;
use App\Actions\OpenCollab\DeactivateContributorAction;
use App\Actions\OpenCollab\ReactivateContributorAction;
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
 *   GET    /api/{site}/open-collab/admin/contributors                    — list / search
 *   GET    /api/{site}/open-collab/admin/contributors/{id}               — show profile
 *   POST   /api/{site}/open-collab/admin/contributors/{id}/deactivate    — deactivate (reason required)
 *   POST   /api/{site}/open-collab/admin/contributors/{id}/reactivate    — reactivate (reason required)
 *   POST   /api/{site}/open-collab/admin/contributors/{id}/close         — full closure
 *   POST   /api/{site}/open-collab/admin/contributors/{id}/grant-access  — grant site access
 *   POST   /api/{site}/open-collab/admin/contributors/{id}/revoke-access — revoke site access
 *   POST   /api/{site}/open-collab/admin/contributors/{id}/role          — change role (reason required)
 *
 *   GET    /api/{site}/open-collab/admin/invitations                     — list all invitations
 *   POST   /api/{site}/open-collab/admin/invitations/{id}/resend         — resend (create new)
 *   DELETE /api/{site}/open-collab/admin/invitations/{id}                — revoke
 */
class AdminContributorController extends Controller
{
    public function __construct(
        private readonly AdminContributorRepository    $contributorRepository,
        private readonly ContributorTerminationService $terminationService,
        private readonly SiteAccessService             $siteAccessService,
        private readonly InvitationService             $invitationService,
        private readonly InvitationRepository          $invitationRepository,
        private readonly DeactivateContributorAction $deactivateAction,
        private readonly ReactivateContributorAction $reactivateAction,
        private readonly ChangeContributorRoleAction $changeRoleAction,
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

        return $this->jsonResponse($this->formatPaginatedUsers($results));
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
     *
     * Body: { "reason": "..." }
     */
    public function deactivate(Request $request, int $id): JsonResponse
    {
        try {
            $this->deactivateAction->execute(
                userId: $id,
                siteId: SiteContext::getId(),
                adminId: Auth::id(),
                reason: (string)$request->get('reason', ''),
            );

            return $this->successResponse('Contributor deactivated.');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/{site}/open-collab/admin/contributors/{id}/reactivate
     *
     * Body: { "reason": "..." }
     */
    public function reactivate(Request $request, int $id): JsonResponse
    {
        try {
            $this->reactivateAction->execute(
                userId: $id,
                siteId: SiteContext::getId(),
                adminId: Auth::id(),
                reason: (string)$request->get('reason', ''),
            );

            return $this->successResponse('Contributor reactivated.');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/{site}/open-collab/admin/contributors/{id}/close
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

    // ── Site access ───────────────────────────────────────────────────────────

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

    // ── Role ──────────────────────────────────────────────────────────────────

    /**
     * POST /api/{site}/open-collab/admin/contributors/{id}/role
     *
     * Body: { "role": "editor", "reason": "..." }
     */
    public function updateRole(Request $request, int $id): JsonResponse
    {
        try {
            $this->changeRoleAction->execute(
                userId: $id,
                siteId: SiteContext::getId(),
                adminId: Auth::id(),
                newRole: (string)$request->get('role', ''),
                reason: (string)$request->get('reason', ''),
            );

            return $this->successResponse('Role updated.');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    // ── Invitations ───────────────────────────────────────────────────────────

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

    /**
     * POST /api/{site}/open-collab/admin/invitations/{id}/resend
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

    // ── Formatting ────────────────────────────────────────────────────────────

    private function formatPaginatedUsers(array $result): array
    {
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
}