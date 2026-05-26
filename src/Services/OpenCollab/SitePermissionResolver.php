<?php

namespace App\Services\OpenCollab;

use App\Framework\Support\Cache\Cache;
use App\Framework\Support\Config;
use App\Repositories\OpenCollab\RbacRepository;
use App\Repositories\OpenCollab\UserSiteRepository;

class SitePermissionResolver
{
    public function __construct(
        private readonly UserSiteRepository $userSiteRepository,
        private readonly RbacRepository $rbacRepository,
        private readonly LegacyRoleToSiteRoleMapper $legacyRoleMapper,
        private readonly RbacBootstrapper $bootstrapper,
    ) {
    }

    public function forUser(int $userId, int $siteId): array
    {
        if (!$this->rbacRepository->siteExists($siteId)) {
            return [];
        }

        $this->bootstrapper->ensureSeeded($siteId);

        return Cache::remember($this->cacheKey($userId, $siteId), 3600, function () use ($userId, $siteId) {
            if (!$this->userSiteRepository->hasAccess($userId, $siteId)) {
                return [];
            }

            $sitePermissions = $this->sitePermissions($userId, $siteId);

            if (!Config::get('rbac.site_enabled', config('rbac.site_enabled', false))) {
                return $this->normalizePermissions($this->legacyPermissions($userId));
            }

            return $this->normalizePermissions(array_merge(
                $sitePermissions,
                $this->legacyPermissions($userId)
            ));
        });
    }

    public function allows(int $userId, int $siteId, string $permission): bool
    {
        $permissions = $this->forUser($userId, $siteId);

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    public function invalidate(int $userId, int $siteId): void
    {
        Cache::forget($this->cacheKey($userId, $siteId));
    }

    private function sitePermissions(int $userId, int $siteId): array
    {
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

        return $permissions;
    }

    private function legacyPermissions(int $userId): array
    {
        return $this->legacyRoleMapper->permissionsForRole($this->rbacRepository->legacyRoleForUser($userId));
    }

    private function normalizePermissions(array $permissions): array
    {
        $permissions = array_values(array_unique($permissions));
        sort($permissions);

        return $permissions;
    }

    private function cacheKey(int $userId, int $siteId): string
    {
        return "permissions:{$siteId}:{$userId}";
    }
}
