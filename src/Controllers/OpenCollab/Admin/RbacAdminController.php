<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\UnauthorizedException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\User;
use App\Services\OpenCollab\OpenCollabAuthorizationService;
use App\Services\OpenCollab\RbacManagementService;

class RbacAdminController extends Controller
{
    public function __construct(
        private readonly RbacManagementService $rbacManagementService,
        private readonly OpenCollabAuthorizationService $authorization,
    ) {
        parent::__construct();
    }

    public function summary(): JsonResponse
    {
        if ($response = $this->authorize(['site.roles.manage', 'site.permissions.manage', 'site.members', 'site.manage'])) {
            return $response;
        }

        return $this->resourceResponse($this->rbacManagementService->summaryForSite(SiteContext::getId()));
    }

    public function syncRolePermissions(Request $request, int $roleId): JsonResponse
    {
        if ($response = $this->authorize(['site.roles.manage', 'site.permissions.manage'])) {
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
        if ($response = $this->authorize(['creator.manage_roles', 'site.members', 'site.roles.manage'])) {
            return $response;
        }

        if (!User::find($userId)) {
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
        if ($response = $this->authorize(['site.permissions.manage', 'creator.manage_roles'])) {
            return $response;
        }

        if (!User::find($userId)) {
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

    private function authorize(array $permissions): ?JsonResponse
    {
        try {
            $this->authorization->assertAny(Auth::id(), SiteContext::getId(), $permissions);
            return null;
        } catch (UnauthorizedException $exception) {
            return $this->errorResponse($exception->getMessage(), 403);
        }
    }
}
