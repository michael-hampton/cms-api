<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Controllers\OpenCollab\Concerns\AuthorizesSitePermissions;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\RbacRepository;
use App\Services\OpenCollab\OpenCollabAuthorizationService;
use App\Services\OpenCollab\RbacManagementService;

class RbacAdminController extends Controller
{
    use AuthorizesSitePermissions;

    public function __construct(
        private readonly RbacManagementService $rbacManagementService,
        private readonly OpenCollabAuthorizationService $authorization,
        private readonly RbacRepository $rbacRepository,
    ) {
        parent::__construct();
    }

    public function summary(): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['site.roles.manage', 'site.permissions.manage', 'site.members', 'site.manage'])) {
            return $response;
        }

        return $this->resourceResponse($this->rbacManagementService->summaryForSite(SiteContext::getId()));
    }

    public function permissions(): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['site.roles.manage', 'site.permissions.manage', 'site.members', 'site.manage'])) {
            return $response;
        }

        return $this->resourceResponse(['permissions' => $this->rbacManagementService->permissionsForSite(SiteContext::getId())]);
    }

    public function roles(): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['site.roles.manage', 'site.permissions.manage', 'site.members', 'site.manage'])) {
            return $response;
        }

        return $this->resourceResponse(['roles' => $this->rbacManagementService->rolesForSite(SiteContext::getId())]);
    }

    public function members(): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['site.roles.manage', 'site.permissions.manage', 'site.members', 'site.manage'])) {
            return $response;
        }

        return $this->resourceResponse(['members' => $this->rbacManagementService->membersForSite(SiteContext::getId())]);
    }

    public function overrides(): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['site.roles.manage', 'site.permissions.manage', 'site.members', 'site.manage'])) {
            return $response;
        }

        return $this->resourceResponse(['overrides' => $this->rbacManagementService->overridesForSite(SiteContext::getId())]);
    }

    public function audit(): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['site.roles.manage', 'site.permissions.manage', 'site.members', 'site.manage'])) {
            return $response;
        }

        return $this->resourceResponse(['audit' => $this->rbacManagementService->auditForSite(SiteContext::getId())]);
    }

    public function syncRolePermissions(Request $request, int $roleId): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['site.roles.manage', 'site.permissions.manage'])) {
            return $response;
        }

        $permissionSlugs = $request->get('permission_slugs', []);
        if (!is_array($permissionSlugs)) {
            return $this->errorResponse('permission_slugs must be an array.', 422);
        }

        $this->rbacManagementService->syncRolePermissions(SiteContext::getId(), $roleId, array_values(array_map('strval', $permissionSlugs)), Auth::id());

        return $this->resourceResponse(['message' => 'Role permissions updated.']);
    }

    public function assignMemberRoles(Request $request, int $userId): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['creator.manage_roles', 'site.members', 'site.roles.manage'])) {
            return $response;
        }

        if (!$this->rbacRepository->userExists($userId)) {
            return $this->errorResponse('User not found.', 404);
        }

        $roleIds = $request->get('role_ids', []);
        if (!is_array($roleIds)) {
            return $this->errorResponse('role_ids must be an array.', 422);
        }

        $this->rbacManagementService->assignUserRoles(SiteContext::getId(), $userId, array_map('intval', $roleIds), Auth::id());

        return $this->resourceResponse(['message' => 'Member roles updated.']);
    }

    public function setOverride(Request $request, int $userId): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['site.permissions.manage', 'creator.manage_roles'])) {
            return $response;
        }

        if (!$this->rbacRepository->userExists($userId)) {
            return $this->errorResponse('User not found.', 404);
        }

        $permissionSlug = (string) $request->get('permission_slug', '');
        $granted = filter_var($request->get('granted', false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($permissionSlug === '' || $granted === null) {
            return $this->errorResponse('permission_slug and granted are required.', 422);
        }

        try {
            $this->rbacManagementService->setUserOverride(SiteContext::getId(), $userId, $permissionSlug, $granted, Auth::id());
        } catch (\InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        }

        return $this->resourceResponse(['message' => 'Permission override updated.']);
    }

    public function deleteOverride(int $userId, string $permissionSlug): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['site.permissions.manage', 'creator.manage_roles'])) {
            return $response;
        }

        if (!$this->rbacRepository->userExists($userId)) {
            return $this->errorResponse('User not found.', 404);
        }

        try {
            $this->rbacManagementService->deleteUserOverride(SiteContext::getId(), $userId, $permissionSlug, Auth::id());
        } catch (\InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        }

        return $this->resourceResponse(['message' => 'Permission override removed.']);
    }

    public function createRole(Request $request): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['site.roles.manage', 'site.permissions.manage'])) {
            return $response;
        }

        try {
            $role = $this->rbacManagementService->createRole(
                SiteContext::getId(),
                (string) $request->get('name', ''),
                ($request->get('slug') !== null ? (string) $request->get('slug') : null),
                is_array($request->get('permission_slugs', [])) ? array_values(array_map('strval', $request->get('permission_slugs', []))) : [],
                Auth::id(),
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        }

        return $this->resourceResponse([
            'message' => 'Role created.',
            'role' => $role,
        ], 201);
    }

    public function deleteRole(int $roleId): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['site.roles.manage', 'site.permissions.manage'])) {
            return $response;
        }

        try {
            $this->rbacManagementService->deleteRole(SiteContext::getId(), $roleId, Auth::id());
        } catch (\InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        }

        return $this->resourceResponse(['message' => 'Role deleted.']);
    }
}
