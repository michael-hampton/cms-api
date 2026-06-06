<?php

namespace App\Services\OpenCollab;

use App\Framework\Support\Config;
use App\Repositories\OpenCollab\RbacRepository;

class SitePermissionBundleBuilder
{
    public function __construct(
        private readonly RbacRepository $rbacRepository,
        private readonly LegacyRoleToSiteRoleMapper $legacyRoleMapper,
        private readonly RbacBootstrapper $bootstrapper,
    ) {
    }

    public function build(int $userId): array
    {
        $legacyRole = $this->rbacRepository->legacyRoleForUser($userId);
        $assignments = [];

        foreach ($this->rbacRepository->activeSiteAssignmentsForUser($userId) as $assignment) {
            $siteId = (int) $assignment['site_id'];

            $this->bootstrapper->ensureSeeded($siteId);

            $roleSlugs = $this->rbacRepository->roleSlugsForUser($siteId, $userId);
            $permissions = $this->permissionsForAssignment($userId, $siteId, $legacyRole);

            $assignments[] = [
                'site_id' => $siteId,
                'role' => $roleSlugs[0] ?? $this->legacyRoleMapper->mapRole($legacyRole),
                'roles' => $roleSlugs,
                'permissions' => $permissions,
            ];
        }

        return [
            'user_id' => $userId,
            'is_global_admin' => $this->isGlobalAdmin($legacyRole),
            'assignments' => $assignments,
        ];
    }

    private function permissionsForAssignment(int $userId, int $siteId, ?string $legacyRole): array
    {
        if (!Config::get('rbac.site_enabled', config('rbac.site_enabled', false))) {
            return $this->normalizePermissions($this->legacyRoleMapper->permissionsForRole($legacyRole));
        }

        $roleIds = $this->rbacRepository->roleIdsForUser($siteId, $userId);
        $permissionIds = $this->rbacRepository->permissionIdsForRoles($roleIds);
        $permissions = $this->rbacRepository->permissionSlugsForIds($permissionIds);

        foreach ($this->rbacRepository->overridesForUser($siteId, $userId) as $override) {
            $slug = $this->rbacRepository->permissionSlugForId((int) $override['permission_id']);

            if (!$slug) {
                continue;
            }

            if ((bool) $override['granted']) {
                $permissions[] = $slug;
                continue;
            }

            $permissions = array_values(array_filter($permissions, fn(string $value) => $value !== $slug));
        }

        return $this->normalizePermissions(array_merge(
            $permissions,
            $this->legacyRoleMapper->permissionsForRole($legacyRole)
        ));
    }

    private function normalizePermissions(array $permissions): array
    {
        $permissions = array_values(array_unique($permissions));
        sort($permissions);

        return $permissions;
    }

    private function isGlobalAdmin(?string $legacyRole): bool
    {
        return in_array($legacyRole, ['admin', 'super_admin'], true);
    }
}
