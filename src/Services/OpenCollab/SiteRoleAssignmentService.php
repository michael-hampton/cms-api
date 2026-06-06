<?php

namespace App\Services\OpenCollab;

use App\Repositories\OpenCollab\RbacRepository;

class SiteRoleAssignmentService
{
    public function __construct(
        private readonly LegacyRoleToSiteRoleMapper $legacyRoleMapper,
        private readonly RbacBootstrapper $bootstrapper,
        private readonly SitePermissionResolver $resolver,
        private ?RbacRepository $rbacRepository = null,
    ) {
    }

    public function syncLegacyRole(int $userId, int $siteId, ?string $legacyRole): ?string
    {
        $siteRoleSlug = $this->legacyRoleMapper->mapRole($legacyRole);

        if ($siteRoleSlug === null) {
            return null;
        }

        $this->bootstrapper->ensureSeeded($siteId);

        $this->rbacRepository ??= new RbacRepository();

        if (!$this->rbacRepository->replaceUserRoleWithSlug($siteId, $userId, $siteRoleSlug)) {
            return null;
        }

        $this->resolver->invalidate($userId, $siteId);

        return $siteRoleSlug;
    }
}
