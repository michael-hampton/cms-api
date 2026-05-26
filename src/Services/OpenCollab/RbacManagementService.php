<?php

namespace App\Services\OpenCollab;

use App\Models\OpenCollabPermission;
use App\Models\OpenCollabRole;
use App\Models\OpenCollabRolePermission;
use App\Models\OpenCollabRbacAuditLog;
use App\Models\OpenCollabSiteUserPermission;
use App\Models\OpenCollabSiteUserRole;
use App\Models\User;
use App\Models\UserSite;

class RbacManagementService
{
    public function __construct(
        private readonly RbacBootstrapper $bootstrapper,
        private readonly SitePermissionResolver $permissionResolver,
        private readonly RbacAuditLogger $auditLogger,
    ) {
    }

    public function summaryForSite(int $siteId): array
    {
        $this->bootstrapper->ensureSeeded($siteId);

        $permissions = OpenCollabPermission::orderBy('group')
            ->orderBy('slug')
            ->get();

        $roles = OpenCollabRole::orderBy('name')->get();
        $rolePermissionMap = [];
        foreach (OpenCollabRolePermission::all() as $mapping) {
            $rolePermissionMap[(int) $mapping->role_id][] = (int) $mapping->permission_id;
        }

        $users = User::whereIn('id', UserSite::where('site_id', $siteId)->get()->pluck('user_id')->toArray())
            ->orderBy('name')
            ->get();

        $userRoleMap = [];
        foreach (OpenCollabSiteUserRole::where('site_id', $siteId)->get() as $assignment) {
            $userRoleMap[(int) $assignment->user_id][] = (int) $assignment->role_id;
        }

        $overrides = OpenCollabSiteUserPermission::where('site_id', $siteId)->get()->map(function ($override) {
            $permission = OpenCollabPermission::find($override->permission_id);

            return [
                'id' => (int) $override->id,
                'user_id' => (int) $override->user_id,
                'permission_id' => (int) $override->permission_id,
                'permission_slug' => $permission?->slug,
                'granted' => (bool) $override->granted,
            ];
        })->toArray();

        $audit = OpenCollabRbacAuditLog::where('site_id', $siteId)
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($entry) {
                return [
                    'id' => (int) $entry->id,
                    'action' => $entry->action,
                    'actor_user_id' => $entry->actor_user_id ? (int) $entry->actor_user_id : null,
                    'target_user_id' => $entry->target_user_id ? (int) $entry->target_user_id : null,
                    'payload' => is_array($entry->payload) ? $entry->payload : (json_decode((string) $entry->payload, true) ?: []),
                    'created_at' => $entry->created_at,
                ];
            })
            ->toArray();

        return [
            'permissions' => $permissions->map(fn($permission) => [
                'id' => (int) $permission->id,
                'name' => $permission->name,
                'slug' => $permission->slug,
                'group' => $permission->group,
            ])->toArray(),
            'roles' => $roles->map(fn($role) => [
                'id' => (int) $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'is_system' => (bool) $role->is_system,
                'permission_ids' => array_values(array_unique($rolePermissionMap[(int) $role->id] ?? [])),
            ])->toArray(),
            'members' => $users->map(fn($user) => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'legacy_role' => $user->role,
                'role_ids' => array_values(array_unique($userRoleMap[(int) $user->id] ?? [])),
            ])->toArray(),
            'overrides' => $overrides,
            'audit' => $audit,
        ];
    }

    public function syncRolePermissions(int $siteId, int $roleId, array $permissionSlugs, ?int $actorUserId = null): void
    {
        $this->bootstrapper->ensureSeeded($siteId);

        $permissionIds = OpenCollabPermission::whereIn('slug', $permissionSlugs)
            ->get()
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->toArray();

        OpenCollabRolePermission::where('role_id', $roleId)->delete();

        foreach ($permissionIds as $permissionId) {
            OpenCollabRolePermission::create([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }

        foreach (UserSite::where('site_id', $siteId)->get() as $membership) {
            $this->permissionResolver->invalidate((int) $membership->user_id, $siteId);
        }

        $this->auditLogger->log(
            action: 'role_permissions_synced',
            siteId: $siteId,
            actorUserId: $actorUserId,
            payload: ['role_id' => $roleId, 'permission_slugs' => array_values($permissionSlugs)]
        );
    }

    public function assignUserRoles(int $siteId, int $userId, array $roleIds, ?int $actorUserId = null): void
    {
        OpenCollabSiteUserRole::where('site_id', $siteId)
            ->where('user_id', $userId)
            ->delete();

        foreach (array_values(array_unique(array_map('intval', $roleIds))) as $roleId) {
            OpenCollabSiteUserRole::create([
                'site_id' => $siteId,
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);
        }

        $this->permissionResolver->invalidate($userId, $siteId);

        $this->auditLogger->log(
            action: 'user_roles_assigned',
            siteId: $siteId,
            actorUserId: $actorUserId,
            targetUserId: $userId,
            payload: ['role_ids' => array_values(array_unique(array_map('intval', $roleIds)))]
        );
    }

    public function setUserOverride(int $siteId, int $userId, string $permissionSlug, bool $granted, ?int $actorUserId = null): void
    {
        $permission = OpenCollabPermission::where('slug', $permissionSlug)->first();
        if (!$permission) {
            throw new \InvalidArgumentException('Permission not found.');
        }

        $override = OpenCollabSiteUserPermission::where('site_id', $siteId)
            ->where('user_id', $userId)
            ->where('permission_id', $permission->id)
            ->first();

        if ($override) {
            $override->update(['granted' => $granted]);
        } else {
            OpenCollabSiteUserPermission::create([
                'site_id' => $siteId,
                'user_id' => $userId,
                'permission_id' => $permission->id,
                'granted' => $granted,
            ]);
        }

        $this->permissionResolver->invalidate($userId, $siteId);

        $this->auditLogger->log(
            action: 'user_permission_override_set',
            siteId: $siteId,
            actorUserId: $actorUserId,
            targetUserId: $userId,
            payload: ['permission_slug' => $permissionSlug, 'granted' => $granted]
        );
    }
}
