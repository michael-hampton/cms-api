<?php

namespace App\Services\OpenCollab;

use App\Framework\Support\Config;
use App\Models\OpenCollabPermission;
use App\Models\OpenCollabRole;
use App\Models\OpenCollabRolePermission;
use App\Models\OpenCollabSiteRole;
use App\Models\Site;

final class RbacBootstrapper
{
    public function ensureSeeded(?int $siteId = null): void
    {
        $this->seedPermissions();
        $this->seedRoles();

        if ($siteId !== null) {
            $this->ensureSiteRolesForSite($siteId);
            return;
        }

        foreach (Site::all(['id']) as $site) {
            $this->ensureSiteRolesForSite((int) $site->id);
        }
    }

    private function seedPermissions(): void
    {
        foreach (Config::get('rbac.permissions', config('rbac.permissions', [])) as $permission) {
            OpenCollabPermission::firstOrCreate(
                ['slug' => $permission['slug']],
                $permission
            );
        }
    }

    private function seedRoles(): void
    {
        foreach (Config::get('rbac.roles', config('rbac.roles', [])) as $slug => $definition) {
            $role = OpenCollabRole::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $definition['name'],
                    'slug' => $slug,
                    'is_system' => (bool) ($definition['is_system'] ?? false),
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );

            if (($definition['permissions'] ?? []) === ['*']) {
                foreach (OpenCollabPermission::all(['id']) as $permission) {
                    OpenCollabRolePermission::firstOrCreate([
                        'role_id' => $role->id,
                        'permission_id' => $permission->id,
                    ]);
                }

                continue;
            }

            foreach ($definition['permissions'] as $permissionSlug) {
                $permission = OpenCollabPermission::where('slug', $permissionSlug)->first();
                if (!$permission) {
                    continue;
                }

                OpenCollabRolePermission::firstOrCreate([
                    'role_id' => $role->id,
                    'permission_id' => $permission->id,
                ]);
            }
        }
    }

    public function ensureSiteRolesForSite(int $siteId): void
    {
        foreach (OpenCollabRole::all() as $role) {
            OpenCollabSiteRole::firstOrCreate(
                ['site_id' => $siteId, 'role_id' => $role->id],
                ['name' => $role->name, 'is_active' => true]
            );
        }
    }
}
