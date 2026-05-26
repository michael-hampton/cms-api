<?php

namespace App\Services\OpenCollab;

use App\Repositories\OpenCollab\RbacRepository;

class RbacManagementService
{
    public function __construct(
        private readonly RbacBootstrapper $bootstrapper,
        private readonly SitePermissionResolver $permissionResolver,
        private readonly RbacAuditLogger $auditLogger,
        private readonly RbacRepository $rbacRepository,
    ) {
    }

    public function summaryForSite(int $siteId): array
    {
        $this->bootstrapper->ensureSeeded($siteId);

        $permissions = $this->rbacRepository->permissions();
        $roles = $this->rbacRepository->roles();
        $rolePermissionMap = $this->rbacRepository->rolePermissionMap();
        $users = $this->rbacRepository->usersForSite($siteId);
        $userRoleMap = $this->rbacRepository->userRoleMapForSite($siteId);
        $overrides = array_map(function ($override) {
            $permissionSlug = $this->rbacRepository->permissionSlugForId((int) $override['permission_id']);
            return array_merge($override, ['permission_slug' => $permissionSlug]);
        }, $this->rbacRepository->overridesForSite($siteId));
        $audit = array_map(function ($entry) {
                return [
                    'id' => (int) $entry['id'],
                    'action' => $entry['action'],
                    'actor_user_id' => !empty($entry['actor_user_id']) ? (int) $entry['actor_user_id'] : null,
                    'target_user_id' => !empty($entry['target_user_id']) ? (int) $entry['target_user_id'] : null,
                    'payload' => is_array($entry['payload'] ?? null) ? $entry['payload'] : (json_decode((string) ($entry['payload'] ?? ''), true) ?: []),
                    'created_at' => $entry['created_at'] ?? null,
                ];
            }, $this->rbacRepository->auditForSite($siteId, 50));

        return [
            'permissions' => array_map(fn($permission) => [
                'id' => (int) $permission['id'],
                'name' => $permission['name'],
                'slug' => $permission['slug'],
                'group' => $permission['group'],
            ], $permissions),
            'roles' => array_map(fn($role) => [
                'id' => (int) $role['id'],
                'name' => $role['name'],
                'slug' => $role['slug'],
                'is_system' => (bool) $role['is_system'],
                'permission_ids' => array_values(array_unique($rolePermissionMap[(int) $role['id']] ?? [])),
            ], $roles),
            'members' => array_map(fn($user) => [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'legacy_role' => $user['role'],
                'role_ids' => array_values(array_unique($userRoleMap[(int) $user['id']] ?? [])),
            ], $users),
            'overrides' => $overrides,
            'audit' => $audit,
        ];
    }

    public function syncRolePermissions(int $siteId, int $roleId, array $permissionSlugs, ?int $actorUserId = null): void
    {
        $this->bootstrapper->ensureSeeded($siteId);

        $permissionIds = array_map(
            'intval',
            array_column(array_filter($this->rbacRepository->permissions(), fn($permission) => in_array($permission['slug'], $permissionSlugs, true)), 'id')
        );

        $this->rbacRepository->replaceRolePermissions($roleId, $permissionIds);

        foreach ($this->rbacRepository->siteMembershipUserIds($siteId) as $userId) {
            $this->permissionResolver->invalidate($userId, $siteId);
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
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        $this->rbacRepository->replaceUserRoles($siteId, $userId, $roleIds);

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
        $permission = $this->rbacRepository->findPermissionBySlug($permissionSlug);
        if (!$permission) {
            throw new \InvalidArgumentException('Permission not found.');
        }

        $this->rbacRepository->upsertUserOverride($siteId, $userId, (int) $permission->id, $granted);

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
