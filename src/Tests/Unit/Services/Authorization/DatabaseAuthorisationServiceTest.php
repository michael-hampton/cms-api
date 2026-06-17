<?php

namespace App\Tests\Unit\Services\Authorization;

use App\Services\Authorization\AccessCapability;
use App\Services\Authorization\AccessGrantRequest;
use App\Services\Authorization\AccessGrantResult;
use App\Services\Authorization\AccessRevocationRequest;
use App\Services\Authorization\AccessRevocationResult;
use App\Services\Authorization\ContributorRoleAssignmentInterface;
use App\Services\Authorization\DatabaseAuthorisationService;
use App\Services\Authorization\UserSiteAccessStoreInterface;
use App\Services\OpenCollab\PermissionCacheInvalidator;
use Mockery;
use PHPUnit\Framework\TestCase;

final class DatabaseAuthorisationServiceTest extends TestCase
{
    public function test_has_access_rejects_unsupported_capability_without_touching_repository(): void
    {
        $userSites = Mockery::mock(UserSiteAccessStoreInterface::class);
        $siteRoles = Mockery::mock(ContributorRoleAssignmentInterface::class);

        $userSites->shouldNotReceive('hasAccess');

        $service = new DatabaseAuthorisationService($userSites, $siteRoles);

        $this->assertFalse($service->hasAccess(7, 3, 'unsupported.capability'));
    }

    public function test_has_access_delegates_contributor_capability_to_user_site_repository(): void
    {
        $userSites = Mockery::mock(UserSiteAccessStoreInterface::class);
        $siteRoles = Mockery::mock(ContributorRoleAssignmentInterface::class);

        $userSites->shouldReceive('hasAccess')
            ->with(7, 3)
            ->once()
            ->andReturn(true);

        $service = new DatabaseAuthorisationService($userSites, $siteRoles);

        $this->assertTrue($service->hasAccess(7, 3, AccessCapability::OPEN_COLLAB_CONTRIBUTOR));
    }

    public function test_grant_access_persists_contributor_access_and_invalidates_cache(): void
    {
        $userSites = Mockery::mock(UserSiteAccessStoreInterface::class);
        $siteRoles = Mockery::mock(ContributorRoleAssignmentInterface::class);
        $cache = Mockery::mock(PermissionCacheInvalidator::class);

        $userSites->shouldReceive('grant')
            ->with(7, 3)
            ->once();

        $cache->shouldReceive('invalidateUser')
            ->with(7)
            ->once();

        $service = new DatabaseAuthorisationService($userSites, $siteRoles, $cache);
        $result = $service->grantAccess(new AccessGrantRequest(
            userId: 7,
            siteId: 3,
            capability: AccessCapability::OPEN_COLLAB_CONTRIBUTOR,
            actorUserId: 99,
            invitationId: 5,
            reason: 'accepted',
        ));

        $this->assertInstanceOf(AccessGrantResult::class, $result);
        $this->assertTrue($result->granted);
    }

    public function test_grant_access_rejects_unsupported_capability(): void
    {
        $userSites = Mockery::mock(UserSiteAccessStoreInterface::class);
        $siteRoles = Mockery::mock(ContributorRoleAssignmentInterface::class);

        $userSites->shouldNotReceive('grant');

        $this->expectException(\InvalidArgumentException::class);

        (new DatabaseAuthorisationService($userSites, $siteRoles))->grantAccess(new AccessGrantRequest(
            userId: 7,
            siteId: 3,
            capability: 'unsupported.capability',
        ));
    }

    public function test_revoke_access_persists_contributor_revocation_and_invalidates_cache(): void
    {
        $userSites = Mockery::mock(UserSiteAccessStoreInterface::class);
        $siteRoles = Mockery::mock(ContributorRoleAssignmentInterface::class);
        $cache = Mockery::mock(PermissionCacheInvalidator::class);

        $userSites->shouldReceive('revoke')
            ->with(7, 3)
            ->once();

        $cache->shouldReceive('invalidateUser')
            ->with(7)
            ->once();

        $service = new DatabaseAuthorisationService($userSites, $siteRoles, $cache);
        $result = $service->revokeAccess(new AccessRevocationRequest(
            userId: 7,
            siteId: 3,
            capability: AccessCapability::OPEN_COLLAB_CONTRIBUTOR,
            actorUserId: 99,
            reason: 'closed',
        ));

        $this->assertInstanceOf(AccessRevocationResult::class, $result);
        $this->assertTrue($result->revoked);
    }

    public function test_assign_role_delegates_to_site_role_assignment_service(): void
    {
        $userSites = Mockery::mock(UserSiteAccessStoreInterface::class);
        $siteRoles = Mockery::mock(ContributorRoleAssignmentInterface::class);

        $siteRoles->shouldReceive('syncLegacyRole')
            ->with(7, 3, 'author')
            ->once()
            ->andReturn('writer');

        $service = new DatabaseAuthorisationService($userSites, $siteRoles);

        $this->assertSame('writer', $service->assignRole(7, 3, 'author', 99));
    }

    public function test_site_queries_delegate_to_user_site_repository(): void
    {
        $userSites = Mockery::mock(UserSiteAccessStoreInterface::class);
        $siteRoles = Mockery::mock(ContributorRoleAssignmentInterface::class);

        $userSites->shouldReceive('userIdsForSite')
            ->with(3)
            ->once()
            ->andReturn([7, 8]);

        $userSites->shouldReceive('hasAnyOtherAccess')
            ->with(7, 3)
            ->once()
            ->andReturn(true);

        $service = new DatabaseAuthorisationService($userSites, $siteRoles);

        $this->assertSame([7, 8], $service->userIdsForSite(3));
        $this->assertTrue($service->hasAnyOtherAccess(7, 3));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
