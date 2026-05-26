<?php

namespace App\Services\OpenCollab;

use App\Models\OpenCollabRole;
use App\Models\OpenCollabSiteUserRole;

class SiteRoleAssignmentService
{
    public function __construct(
        private readonly LegacyRoleToSiteRoleMapper $legacyRoleMapper,
        private readonly RbacBootstrapper $bootstrapper,
        private readonly SitePermissionResolver $resolver,
    ) {
    }

    public function syncLegacyRole(int $userId, int $siteId, ?string $legacyRole): ?string
    {
        $siteRoleSlug = $this->legacyRoleMapper->mapRole($legacyRole);

        if ($siteRoleSlug === null) {
            return null;
        }

        $this->bootstrapper->ensureSeeded($siteId);

        $siteRole = OpenCollabRole::where('slug', $siteRoleSlug)->first();
        if (!$siteRole) {
            return null;
        }

        OpenCollabSiteUserRole::where('site_id', $siteId)
            ->where('user_id', $userId)
            ->delete();

        OpenCollabSiteUserRole::firstOrCreate([
            'site_id' => $siteId,
            'user_id' => $userId,
            'role_id' => $siteRole->id,
        ]);

        $this->resolver->invalidate($userId, $siteId);

        return $siteRoleSlug;
    }
}
