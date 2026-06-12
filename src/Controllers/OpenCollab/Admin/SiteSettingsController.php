<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Models\User;
use App\Repositories\Cms\UserRepository;
use App\Requests\OpenCollab\Admin\AssignSiteUserRequest;
use App\Requests\OpenCollab\Admin\SearchSiteUsersRequest;
use App\Requests\UpdateSiteSettingsRequest;
use App\Services\OpenCollab\SiteAccessService;
use App\Services\OpenCollab\SiteService;
use Exception;

class SiteSettingsController extends Controller
{
    public function __construct(
        private readonly SiteService       $siteService,
        private readonly SiteAccessService $siteAccessService,
        private readonly UserRepository    $userRepository,
    )
    {
        parent::__construct();
    }

    public function show(): mixed
    {
        $site = SiteContext::get();

        if (!$site) {
            return $this->errorResponse('Site not found', 404);
        }

        return $this->view('open-collab.admin.sites.settings', [
            'pageTitle' => 'Site Settings',
            'activeNav' => 'site_settings',
            'breadcrumbs' => [
                ['label' => 'Admin', 'url' => "/{$site->slug}/open-collab/admin"],
                ['label' => 'Site Settings'],
            ],
            'site' => $site->slug,
            'currentSite' => $site,
        ]);
    }

    /**
     * GET /api/{site}/open-collab/admin/sites/users
     *
     * Returns users assigned to the current site.
     *
     * Response: { "users": [{ "id", "name", "email" }] }
     */
    public function assignedUsers(): JsonResponse
    {
        $site = SiteContext::get();

        if (!$site) {
            return $this->errorResponse('Site not found', 404);
        }

        $assignedUserIds = $this->siteAccessService->getUserIdsForSite($site->id);

        if (empty($assignedUserIds)) {
            return $this->resourceResponse([
                'users' => [],
            ]);
        }

        $users = User::whereIn('id', $assignedUserIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->all();

        return $this->resourceResponse([
            'users' => array_map(
                fn($u) => [
                    'id' => $u['id'] ?? $u->id,
                    'name' => $u['name'] ?? $u->name,
                    'email' => $u['email'] ?? $u->email,
                ],
                $users,
            ),
        ]);
    }

    public function update(UpdateSiteSettingsRequest $request): mixed
    {
        $site = SiteContext::get();

        if (!$site) {
            return $this->errorResponse('Site not found', 404);
        }

        try {
            $data = $request->validated();

            $this->siteService->updateSiteSettings($site->id, $data);

            if ($request->wantsJson()) {
                return $this->jsonResponse(['message' => 'Site settings saved.']);
            }

            return $this->redirect(
                "/{$site->slug}/open-collab/admin/sites/settings",
                ['flash_success' => 'Site settings saved.']
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * GET /{site}/open-collab/admin/users/search?q=
     *
     * Returns active users matching the query, excluding those already
     * assigned to this site. Used to populate the user assignment dropdown.
     *
     * Response: { "users": [{ "id", "name", "email" }] }
     */
    public function searchUsers(SearchSiteUsersRequest $request): JsonResponse
    {
        $site = SiteContext::get();

        if (!$site) {
            return $this->errorResponse('Site not found', 404);
        }

        $query = $request->validated()['q'];
        $excludeUserIds = $this->siteAccessService->getUserIdsForSite($site->id);

        $users = $this->userRepository->searchForSiteAssignment($query, $excludeUserIds);

        return $this->resourceResponse([
            'users' => array_map(
                fn(array $u) => ['id' => $u['id'], 'name' => $u['name'], 'email' => $u['email']],
                $users,
            ),
        ]);
    }

    /**
     * POST /{site}/open-collab/admin/sites/users
     *
     * Assigns a user to this site. Expects JSON: { "user_id": int }
     */
    public function assignUser(AssignSiteUserRequest $request): JsonResponse
    {
        $site = SiteContext::get();

        if (!$site) {
            return $this->errorResponse('Site not found', 404);
        }

        $userId = (int)$request->validated()['user_id'];
        $user = User::find($userId);

        if (!$user) {
            return $this->errorResponse('User not found.', 404);
        }

        if ($this->siteAccessService->canAccessSite($userId, $site->id)) {
            return $this->resourceResponse([
                'message' => 'User already has access to this site.',
                'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            ]);
        }

        $this->siteAccessService->grantAccess($userId, $site->id);

        return $this->resourceResponse([
            'message' => "{$user->name} has been added to this site.",
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ], 201);
    }

    /**
     * DELETE /{site}/open-collab/admin/sites/users/{userId}
     *
     * Removes a user from this site.
     */
    public function removeUser(int $userId): JsonResponse
    {
        $site = SiteContext::get();

        if (!$site) {
            return $this->errorResponse('Site not found', 404);
        }

        if (!$this->siteAccessService->canAccessSite($userId, $site->id)) {
            return $this->errorResponse('User does not have access to this site.', 404);
        }

        $this->siteAccessService->revokeAccess($userId, $site->id);

        return $this->jsonResponse(['message' => 'User removed from site.']);
    }
}
