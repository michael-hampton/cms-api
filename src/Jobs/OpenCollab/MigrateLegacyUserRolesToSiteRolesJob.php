<?php

namespace App\Jobs\OpenCollab;

use App\Jobs\BaseJob;
use App\Models\User;
use App\Repositories\OpenCollab\UserSiteRepository;
use App\Services\OpenCollab\RbacAuditLogger;
use App\Services\OpenCollab\SiteRoleAssignmentService;

class MigrateLegacyUserRolesToSiteRolesJob extends BaseJob
{
    private ?UserSiteRepository $userSiteRepository;
    private ?SiteRoleAssignmentService $siteRoleAssignmentService;
    private ?RbacAuditLogger $rbacAuditLogger;

    public function __construct(
        ?UserSiteRepository $userSiteRepository = null,
        ?SiteRoleAssignmentService $siteRoleAssignmentService = null,
        ?RbacAuditLogger $rbacAuditLogger = null,
    ) {
        $this->userSiteRepository = $userSiteRepository;
        $this->siteRoleAssignmentService = $siteRoleAssignmentService;
        $this->rbacAuditLogger = $rbacAuditLogger;
    }

    public function handle(): void
    {
        $this->userSiteRepository ??= $this->resolveProperty('userSiteRepository', UserSiteRepository::class);
        $this->siteRoleAssignmentService ??= $this->resolveProperty('siteRoleAssignmentService', SiteRoleAssignmentService::class);
        $this->rbacAuditLogger ??= $this->resolveProperty('rbacAuditLogger', RbacAuditLogger::class);

        foreach (User::all() as $user) {
            $userId = (int) (is_array($user) ? ($user['id'] ?? 0) : $user->id);
            $legacyRole = is_array($user) ? ($user['role'] ?? null) : $user->role;
            $siteIds = $this->userSiteRepository->siteIdsForUser($userId);

            if ($siteIds === []) {
                $this->rbacAuditLogger->log(
                    action: 'legacy_role_migration_skipped',
                    targetUserId: $userId,
                    payload: ['legacy_role' => $legacyRole, 'reason' => 'missing_site_membership']
                );
                continue;
            }

            foreach ($siteIds as $siteId) {
                $mappedRole = $this->siteRoleAssignmentService->syncLegacyRole(
                    userId: $userId,
                    siteId: (int) $siteId,
                    legacyRole: $legacyRole,
                );

                $this->rbacAuditLogger->log(
                    action: $mappedRole ? 'legacy_role_migrated' : 'legacy_role_unmapped',
                    siteId: (int) $siteId,
                    targetUserId: $userId,
                    payload: [
                        'legacy_role' => $legacyRole,
                        'mapped_role' => $mappedRole,
                    ]
                );
            }
        }
    }
}
