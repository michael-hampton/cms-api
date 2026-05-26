<?php

namespace App\Services\OpenCollab;

use App\Framework\Support\Config;
use App\Repositories\OpenCollab\RbacRepository;

final class RbacBootstrapper
{
    public function __construct(
        private readonly RbacRepository $rbacRepository,
    ) {
    }

    public function ensureSeeded(?int $siteId = null): void
    {
        $this->seedPermissions();
        $this->seedRoles();

        if ($siteId !== null) {
            $this->ensureSiteRolesForSite($siteId);
            return;
        }

        foreach ($this->rbacRepository->siteIds() as $resolvedSiteId) {
            $this->ensureSiteRolesForSite($resolvedSiteId);
        }
    }

    private function seedPermissions(): void
    {
        foreach (Config::get('rbac.permissions', config('rbac.permissions', [])) as $permission) {
            $this->rbacRepository->createPermissionIfMissing($permission);
        }
    }

    private function seedRoles(): void
    {
        foreach (Config::get('rbac.roles', config('rbac.roles', [])) as $slug => $definition) {
            $role = $this->rbacRepository->createOrUpdateRole(
                $slug,
                [
                    'name' => $definition['name'],
                    'is_system' => (bool) ($definition['is_system'] ?? false),
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );

            if (($definition['permissions'] ?? []) === ['*']) {
                foreach ($this->rbacRepository->permissionIds() as $permissionId) {
                    $this->rbacRepository->attachPermissionToRoleIfMissing((int) $role->id, $permissionId);
                }

                continue;
            }

            foreach ($definition['permissions'] as $permissionSlug) {
                $permissionId = $this->rbacRepository->findPermissionIdBySlug($permissionSlug);
                if (!$permissionId) {
                    continue;
                }

                $this->rbacRepository->attachPermissionToRoleIfMissing((int) $role->id, $permissionId);
            }
        }
    }

    public function ensureSiteRolesForSite(int $siteId): void
    {
        foreach ($this->rbacRepository->roles() as $role) {
            $this->rbacRepository->ensureSiteRole(
                $siteId,
                (int) $role['id'],
                $role['name'],
                true
            );
        }
    }
}
