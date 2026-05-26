<?php

namespace App\Services\OpenCollab;

use App\Framework\Support\Cache\Cache;
use App\Framework\Support\Config;
use App\Models\OpenCollabPermission;
use App\Models\OpenCollabRolePermission;
use App\Models\OpenCollabSiteUserPermission;
use App\Models\OpenCollabSiteUserRole;
use App\Models\Site;
use App\Models\User;
use App\Repositories\OpenCollab\UserSiteRepository;

class SitePermissionResolver
{
    public function __construct(
        private readonly UserSiteRepository $userSiteRepository,
        private readonly LegacyRoleToSiteRoleMapper $legacyRoleMapper,
        private readonly RbacBootstrapper $bootstrapper,
    ) {
    }

    public function forUser(int $userId, int $siteId): array
    {
        if (!Site::find($siteId)) {
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
        $roleIds = OpenCollabSiteUserRole::where('site_id', $siteId)
            ->where('user_id', $userId)
            ->get()
            ->pluck('role_id')
            ->toArray();

        $permissionIds = empty($roleIds)
            ? []
            : OpenCollabRolePermission::whereIn('role_id', $roleIds)->get()->pluck('permission_id')->toArray();

        $permissions = empty($permissionIds)
            ? []
            : OpenCollabPermission::whereIn('id', $permissionIds)->get()->pluck('slug')->toArray();

        $overrides = OpenCollabSiteUserPermission::where('site_id', $siteId)
            ->where('user_id', $userId)
            ->get();

        foreach ($overrides as $override) {
            $slug = OpenCollabPermission::find($override->permission_id)?->slug;
            if (!$slug) {
                continue;
            }

            if ((bool) $override->granted) {
                $permissions[] = $slug;
                continue;
            }

            $permissions = array_values(array_filter($permissions, fn(string $value) => $value !== $slug));
        }

        return $permissions;
    }

    private function legacyPermissions(int $userId): array
    {
        $user = User::find($userId);

        return $this->legacyRoleMapper->permissionsForRole($user?->role);
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
