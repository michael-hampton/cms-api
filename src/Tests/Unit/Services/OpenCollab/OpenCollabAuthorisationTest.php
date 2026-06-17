<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\DTO\OpenCollab\ContributorAccessGrantRequest;
use App\DTO\OpenCollab\ContributorAccessRevocationRequest;
use App\DTO\OpenCollab\ContributorRoleAssignmentRequest;
use App\Services\Authorization\AccessCapability;
use App\Services\Authorization\AccessGrantRequest;
use App\Services\Authorization\AccessGrantResult;
use App\Services\Authorization\AccessRevocationRequest;
use App\Services\Authorization\AccessRevocationResult;
use App\Services\Authorization\AuthorisationServiceInterface;
use App\Services\OpenCollab\OpenCollabAuthorisation;
use Mockery;
use PHPUnit\Framework\TestCase;

final class OpenCollabAuthorisationTest extends TestCase
{
    public function test_has_contributor_access_delegates_to_central_authorisation_capability(): void
    {
        $central = Mockery::mock(AuthorisationServiceInterface::class);
        $central->shouldReceive('hasAccess')
            ->with(7, 3, AccessCapability::OPEN_COLLAB_CONTRIBUTOR)
            ->once()
            ->andReturn(true);

        $this->assertTrue((new OpenCollabAuthorisation($central))->hasContributorAccess(7, 3));
    }

    public function test_grant_contributor_access_translates_request_to_central_authorisation(): void
    {
        $central = Mockery::mock(AuthorisationServiceInterface::class);
        $result = new AccessGrantResult(true);

        $central->shouldReceive('grantAccess')
            ->once()
            ->withArgs(fn (AccessGrantRequest $request): bool =>
                $request->userId === 7
                && $request->siteId === 3
                && $request->capability === AccessCapability::OPEN_COLLAB_CONTRIBUTOR
                && $request->actorUserId === 99
                && $request->invitationId === 5
                && $request->reason === 'accepted'
            )
            ->andReturn($result);

        $actual = (new OpenCollabAuthorisation($central))->grantContributorAccess(
            new ContributorAccessGrantRequest(7, 3, 99, 5, 'accepted')
        );

        $this->assertSame($result, $actual);
    }

    public function test_revoke_contributor_access_translates_request_to_central_authorisation(): void
    {
        $central = Mockery::mock(AuthorisationServiceInterface::class);
        $result = new AccessRevocationResult(true);

        $central->shouldReceive('revokeAccess')
            ->once()
            ->withArgs(fn (AccessRevocationRequest $request): bool =>
                $request->userId === 7
                && $request->siteId === 3
                && $request->capability === AccessCapability::OPEN_COLLAB_CONTRIBUTOR
                && $request->actorUserId === 99
                && $request->reason === 'closed'
            )
            ->andReturn($result);

        $actual = (new OpenCollabAuthorisation($central))->revokeContributorAccess(
            new ContributorAccessRevocationRequest(7, 3, 99, 'closed')
        );

        $this->assertSame($result, $actual);
    }

    public function test_assign_contributor_role_delegates_to_central_authorisation(): void
    {
        $central = Mockery::mock(AuthorisationServiceInterface::class);
        $central->shouldReceive('assignRole')
            ->with(7, 3, 'author', 99)
            ->once()
            ->andReturn('writer');

        $actual = (new OpenCollabAuthorisation($central))->assignContributorRole(
            new ContributorRoleAssignmentRequest(7, 3, 'author', 99, 'promotion')
        );

        $this->assertSame('writer', $actual);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
