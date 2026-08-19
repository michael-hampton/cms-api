<?php

namespace App\Services\OpenCollab;

use App\Framework\Database\Database;
use App\Repositories\OpenCollab\RbacRepository;

class RbacManagementService
{
    public function __construct(
        private readonly RbacBootstrapper $bootstrapper,
        private readonly SitePermissionResolver $permissionResolver,
        private readonly RbacAuditLogger $auditLogger,
        private readonly RbacRepository $rbacRepository,
        private readonly Database $database,
    ) {
    }

    public function summaryForSite(int $siteId): array
    {
        $this->bootstrapper->ensureSeeded($siteId);

        return [
            'permissions' => $this->permissionsForSite($siteId),
            'roles' => $this->rolesForSite($siteId),
            'members' => $this->membersForSite($siteId),
            'overrides' => $this->overridesForSite($siteId),
            'audit' => $this->auditForSite($siteId),
        ];
    }

    public function permissionsForSite(int $siteId): array
    {
        $this->bootstrapper->ensureSeeded($siteId);

        return array_map(fn($permission) => [
            'id' => (int) $permission['id'],
            'name' => $permission['name'],
            'slug' => $permission['slug'],
            'group' => $permission['group'],
        ], $this->rbacRepository->permissions());
    }

    public function rolesForSite(int $siteId): array
    {
        $this->bootstrapper->ensureSeeded($siteId);

        $rolePermissionMap = $this->rbacRepository->rolePermissionMap();

        return array_map(fn($role) => [
            'id' => (int) $role['id'],
            'name' => $role['name'],
            'slug' => $role['slug'],
            'is_system' => (bool) $role['is_system'],
            'permission_ids' => array_values(array_unique($rolePermissionMap[(int) $role['id']] ?? [])),
        ], $this->rbacRepository->roles());
    }

    public function membersForSite(int $siteId): array
    {
        $this->bootstrapper->ensureSeeded($siteId);

        $userRoleMap = $this->rbacRepository->userRoleMapForSite($siteId);

        return array_map(fn($user) => [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'legacy_role' => $user['role'],
            'role_ids' => array_values(array_unique($userRoleMap[(int) $user['id']] ?? [])),
        ], $this->rbacRepository->usersForSite($siteId));
    }

    public function overridesForSite(int $siteId): array
    {
        $this->bootstrapper->ensureSeeded($siteId);

        return array_map(function ($override) {
            $permissionSlug = $this->rbacRepository->permissionSlugForId((int) $override['permission_id']);

            return array_merge($override, ['permission_slug' => $permissionSlug]);
        }, $this->rbacRepository->overridesForSite($siteId));
    }

    public function auditForSite(int $siteId, int $limit = 50): array
    {
        $this->bootstrapper->ensureSeeded($siteId);

        return array_map(function ($entry) {
            return [
                'id' => (int) $entry['id'],
                'action' => $entry['action'],
                'actor_user_id' => !empty($entry['actor_user_id']) ? (int) $entry['actor_user_id'] : null,
                'target_user_id' => !empty($entry['target_user_id']) ? (int) $entry['target_user_id'] : null,
                'payload' => is_array($entry['payload'] ?? null) ? $entry['payload'] : (json_decode((string) ($entry['payload'] ?? ''), true) ?: []),
                'created_at' => $entry['created_at'] ?? null,
            ];
        }, $this->rbacRepository->auditForSite($siteId, $limit));
    }

    public function syncRolePermissions(int $siteId, int $roleId, array $permissionSlugs, ?int $actorUserId = null): void
    {
        $this->bootstrapper->ensureSeeded($siteId);

        $permissionIds = array_map(
            'intval',
            array_column(array_filter($this->rbacRepository->permissions(), fn($permission) => in_array($permission['slug'], $permissionSlugs, true)), 'id')
        );

        $this->database->transaction(function () use ($siteId, $roleId, $permissionSlugs, $permissionIds, $actorUserId): void {
            $this->rbacRepository->replaceRolePermissions($roleId, $permissionIds);

            $this->auditLogger->log(
                action: 'role_permissions_synced',
                siteId: $siteId,
                actorUserId: $actorUserId,
                payload: ['role_id' => $roleId, 'permission_slugs' => array_values($permissionSlugs)]
            );
        });

        // Cache invalidation happens after commit — it's not a DB write and
        // doesn't need to roll back with the transaction.
        $this->permissionResolver->invalidateMany($this->rbacRepository->siteMembershipUserIds($siteId), $siteId);
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

    public function deleteUserOverride(int $siteId, int $userId, string $permissionSlug, ?int $actorUserId = null): void
    {
        $permission = $this->rbacRepository->findPermissionBySlug($permissionSlug);
        if (!$permission) {
            throw new \InvalidArgumentException('Permission not found.');
        }

        if (!$this->rbacRepository->deleteUserOverride($siteId, $userId, (int) $permission->id)) {
            throw new \InvalidArgumentException('Permission override not found.');
        }

        $this->permissionResolver->invalidate($userId, $siteId);

        $this->auditLogger->log(
            action: 'user_permission_override_deleted',
            siteId: $siteId,
            actorUserId: $actorUserId,
            targetUserId: $userId,
            payload: ['permission_slug' => $permissionSlug]
        );
    }

    public function createRole(int $siteId, string $name, ?string $slug = null, array $permissionSlugs = [], ?int $actorUserId = null): array
    {
        $this->bootstrapper->ensureSeeded($siteId);

        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Role name is required.');
        }

        $slug = $slug !== null && trim($slug) !== ''
            ? trim($slug)
            : strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $name), '_'));

        if ($slug === '') {
            throw new \InvalidArgumentException('Role slug is required.');
        }

        if ($this->rbacRepository->findRoleBySlug($slug)) {
            throw new \InvalidArgumentException('A role with that slug already exists.');
        }

        $role = $this->database->transaction(function () use ($siteId, $name, $slug, $permissionSlugs, $actorUserId) {
            $role = $this->rbacRepository->createRole([
                'name' => $name,
                'slug' => $slug,
                'is_system' => false,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $this->rbacRepository->ensureSiteRole($siteId, (int) $role->id, $role->name, true);

            if ($permissionSlugs !== []) {
                $this->syncRolePermissions($siteId, (int) $role->id, $permissionSlugs, $actorUserId);
            }

            $this->auditLogger->log(
                action: 'role_created',
                siteId: $siteId,
                actorUserId: $actorUserId,
                payload: ['role_id' => (int) $role->id, 'slug' => $slug, 'name' => $name]
            );

            return $role;
        });

        return [
            'id' => (int) $role->id,
            'name' => $role->name,
            'slug' => $role->slug,
            'is_system' => (bool) $role->is_system,
        ];
    }

    public function deleteRole(int $siteId, int $roleId, ?int $actorUserId = null): void
    {
        $role = $this->rbacRepository->findRoleById($roleId);
        if (!$role) {
            throw new \InvalidArgumentException('Role not found.');
        }

        if ((bool) $role->is_system) {
            throw new \InvalidArgumentException('System roles cannot be deleted.');
        }

        $affectedUserIds = [];
        foreach ($this->rbacRepository->userRoleMapForSite($siteId) as $userId => $roleIds) {
            if (in_array($roleId, $roleIds, true)) {
                $affectedUserIds[] = (int) $userId;
            }
        }

        $this->database->transaction(function () use ($siteId, $roleId, $role, $actorUserId): void {
            $this->rbacRepository->deleteUserRolesForRole($siteId, $roleId);
            $this->rbacRepository->deleteSiteRole($siteId, $roleId);

            if ($this->rbacRepository->siteRoleCountForRole($roleId) === 0) {
                $this->rbacRepository->deleteRolePermissions($roleId);
                $this->rbacRepository->deleteRole($roleId);
            }

            $this->auditLogger->log(
                action: 'role_deleted',
                siteId: $siteId,
                actorUserId: $actorUserId,
                payload: ['role_id' => $roleId, 'slug' => $role->slug, 'name' => $role->name]
            );
        });

        // Cache invalidation happens after commit.
        $this->permissionResolver->invalidateMany($affectedUserIds, $siteId);
    }
}
