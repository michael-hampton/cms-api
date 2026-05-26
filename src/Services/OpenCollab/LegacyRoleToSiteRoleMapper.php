<?php

namespace App\Services\OpenCollab;

use App\Framework\Support\Config;

final class LegacyRoleToSiteRoleMapper
{
    public function mapRole(?string $legacyRole): ?string
    {
        $map = Config::get('rbac.legacy_role_map', config('rbac.legacy_role_map', []));

        return $legacyRole && isset($map[$legacyRole]) ? $map[$legacyRole]['site_role'] : null;
    }

    public function permissionsForRole(?string $legacyRole): array
    {
        $map = Config::get('rbac.legacy_role_map', config('rbac.legacy_role_map', []));

        return $legacyRole && isset($map[$legacyRole]) ? $map[$legacyRole]['permissions'] : [];
    }
}
