<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\DTO\OpenCollab\ContributorAccessGrantRequest;
use App\Events\OpenCollab\InvitationAccepted;
use App\Exceptions\OpenCollab\InvalidInvitationException;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Notifications\NotificationDispatcher;
use App\Models\Invitation;
use App\Models\User;
use App\Repositories\OpenCollab\ContributorProfileRepository;
use App\Repositories\OpenCollab\InvitationRepositoryInterface;
use App\Services\Authorization\AccessGrantResult;
use App\Services\OpenCollab\ContributorOnboardingService;
use App\Services\OpenCollab\InvitationService;
use App\Services\OpenCollab\Notifications\InvitationCreatedNotification;
use App\Services\OpenCollab\OpenCollabAuthorisationInterface;
use App\Services\User\UserLifecycleServiceInterface;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

final class InvitationServiceTest extends TestCase
{
    private InvitationService $service;
    private MockInterface $invitations;
    private MockInterface $users;
    private MockInterface $authorisation;
    private MockInterface $onboarding;
    private MockInterface $profiles;
    private MockInterface $events;
    private MockInterface $database;
    private MockInterface $notifications;

    public function test_create_stores_invitation_when_email_has_no_existing_site_access(): void
    {
        $this->users->shouldReceive('findByEmail')
            ->with('guest@example.com')
            ->once()
            ->andReturn(null);

        $this->invitations->shouldReceive('hasPendingInviteForEmail')
            ->with('guest@example.com', 1)
            ->once()
            ->andReturn(false);

        $this->invitations->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $data): bool =>
                $data['email'] === 'guest@example.com'
                && $data['site_id'] === 1
                && $data['invited_by'] === 99
                && strlen($data['token']) === 64
                && isset($data['expires_at'])
            )
            ->andReturn($this->makeInvitation());

        $this->notifications->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn ($notification): bool => $notification instanceof InvitationCreatedNotification);

        $invitation = $this->service->create(' GUEST@example.com ', 99, 1);

        $this->assertInstanceOf(Invitation::class, $invitation);
    }

    public function test_create_rejects_existing_contributor_access_through_authorisation_boundary(): void
    {
        $user = $this->makeUser(['id' => 44, 'email' => 'guest@example.com']);

        $this->users->shouldReceive('findByEmail')
            ->with('guest@example.com')
            ->once()
            ->andReturn($user);

        $this->authorisation->shouldReceive('hasContributorAccess')
            ->with(44, 1)
            ->once()
            ->andReturn(true);

        $this->invitations->shouldNotReceive('create');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/already has access/i');

        $this->service->create('guest@example.com', 99, 1);
    }

    public function test_create_allows_existing_user_without_contributor_access(): void
    {
        $user = $this->makeUser(['id' => 44, 'email' => 'guest@example.com']);
        $invitation = $this->makeInvitation(['email' => 'guest@example.com']);

        $this->users->shouldReceive('findByEmail')
            ->with('guest@example.com')
            ->once()
            ->andReturn($user);

        $this->authorisation->shouldReceive('hasContributorAccess')
            ->with(44, 1)
            ->once()
            ->andReturn(false);

        $this->invitations->shouldReceive('hasPendingInviteForEmail')
            ->with('guest@example.com', 1)
            ->once()
            ->andReturn(false);

        $this->invitations->shouldReceive('create')
            ->once()
            ->andReturn($invitation);

        $this->assertSame($invitation, $this->service->create('guest@example.com', 99, 1));
    }

    public function test_create_rejects_pending_invitation_for_same_email_and_site(): void
    {
        $this->users->shouldReceive('findByEmail')
            ->with('guest@example.com')
            ->once()
            ->andReturn(null);

        $this->invitations->shouldReceive('hasPendingInviteForEmail')
            ->with('guest@example.com', 1)
            ->once()
            ->andReturn(true);

        $this->invitations->shouldNotReceive('create');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/pending invitation/i');

        $this->service->create('guest@example.com', 99, 1);
    }

    public function test_create_uses_custom_ttl_hours(): void
    {
        $this->users->shouldReceive('findByEmail')->andReturn(null);
        $this->invitations->shouldReceive('hasPendingInviteForEmail')->andReturn(false);

        $this->invitations->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data): bool {
                $expected = strtotime('+24 hours');
                $actual = strtotime($data['expires_at']);

                return abs($expected - $actual) < 60;
            })
            ->andReturn($this->makeInvitation());

        $this->service->create('guest@example.com', 99, 1, ttlHours: 24);

        $this->assertTrue(true);
    }

    public function test_accept_requests_user_lifecycle_and_access_grant_then_starts_onboarding(): void
    {
        $invitation = $this->makeInvitation(['id' => 5, 'site_id' => 1]);
        $user = $this->makeUser(['id' => 10, 'email' => 'guest@example.com']);

        $this->invitations->shouldReceive('findByToken')
            ->with('valid-token')
            ->once()
            ->andReturn($invitation);

        $this->users->shouldReceive('ensureContributorAccount')
            ->once()
            ->withArgs(fn (
                string $email,
                ?string $name,
                ?string $password,
                ?int $actorUserId,
                ?string $reason
            ): bool =>
                $email === 'guest@example.com'
                && $name === 'Jane Doe'
                && $password === 'secret123'
                && $actorUserId === null
                && $reason === 'OpenCollab invitation accepted'
            )
            ->andReturn($user);

        $this->authorisation->shouldReceive('grantContributorAccess')
            ->once()
            ->withArgs(fn (ContributorAccessGrantRequest $request): bool =>
                $request->userId === 10
                && $request->siteId === 1
                && $request->actorUserId === 10
                && $request->invitationId === 5
            )
            ->andReturn(new AccessGrantResult(true));

        $this->invitations->shouldReceive('markAsUsed')
            ->with(5, 10)
            ->once();

        $this->profiles->shouldReceive('findOrCreateForUser')
            ->with(10)
            ->once();

        $this->onboarding->shouldReceive('hasStarted')
            ->with(10, 1)
            ->once()
            ->andReturn(false);

        $this->onboarding->shouldReceive('start')
            ->with(10, 1)
            ->once();

        $this->events->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn ($event): bool => $event instanceof InvitationAccepted);

        $result = $this->service->accept('valid-token', 'Jane Doe', 'secret123');

        $this->assertSame($user, $result);
    }

    public function test_accept_does_not_start_onboarding_when_already_started(): void
    {
        $invitation = $this->makeInvitation(['id' => 5, 'site_id' => 1]);
        $user = $this->makeUser(['id' => 10]);

        $this->invitations->shouldReceive('findByToken')->andReturn($invitation);
        $this->users->shouldReceive('ensureContributorAccount')->andReturn($user);
        $this->authorisation->shouldReceive('grantContributorAccess')->andReturn(new AccessGrantResult(true));
        $this->invitations->shouldReceive('markAsUsed')->with(5, 10)->once();
        $this->profiles->shouldReceive('findOrCreateForUser')->with(10)->once();
        $this->onboarding->shouldReceive('hasStarted')->with(10, 1)->andReturn(true);
        $this->onboarding->shouldNotReceive('start');
        $this->events->shouldReceive('dispatch')->once();

        $this->assertSame($user, $this->service->accept('valid-token', 'Jane', 'password'));
    }

    public function test_accept_rolls_back_if_user_lifecycle_request_fails(): void
    {
        $this->invitations->shouldReceive('findByToken')->andReturn($this->makeInvitation());
        $this->users->shouldReceive('ensureContributorAccount')
            ->andThrow(new \RuntimeException('User service failure'));

        $this->authorisation->shouldNotReceive('grantContributorAccess');
        $this->invitations->shouldNotReceive('markAsUsed');
        $this->profiles->shouldNotReceive('findOrCreateForUser');
        $this->onboarding->shouldNotReceive('start');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User service failure');

        $this->service->accept('valid-token', 'Jane', 'password');
    }

    public function test_accept_on_behalf_uses_admin_actor_for_grant_and_accepted_by(): void
    {
        $invitation = $this->makeInvitation(['id' => 5, 'site_id' => 1, 'email' => 'invited@example.com']);
        $user = $this->makeUser(['id' => 10, 'email' => 'invited@example.com']);

        $this->invitations->shouldReceive('findByToken')->andReturn($invitation);

        $this->users->shouldReceive('ensureContributorAccount')
            ->once()
            ->withArgs(fn (
                string $email,
                ?string $name,
                ?string $password,
                ?int $actorUserId,
                ?string $reason
            ): bool =>
                $email === 'invited@example.com'
                && $name === 'New Contributor'
                && $password === null
                && $actorUserId === 999
                && $reason === 'OpenCollab invitation accepted on behalf of contributor'
            )
            ->andReturn($user);

        $this->authorisation->shouldReceive('grantContributorAccess')
            ->once()
            ->withArgs(fn (ContributorAccessGrantRequest $request): bool =>
                $request->userId === 10
                && $request->siteId === 1
                && $request->actorUserId === 999
                && $request->invitationId === 5
            )
            ->andReturn(new AccessGrantResult(true));

        $this->invitations->shouldReceive('markAsUsed')->with(5, 999)->once();
        $this->profiles->shouldReceive('findOrCreateForUser')->with(10)->once();
        $this->onboarding->shouldReceive('hasStarted')->with(10, 1)->andReturn(false);
        $this->onboarding->shouldReceive('start')->with(10, 1)->once();
        $this->events->shouldReceive('dispatch')->once();

        $this->assertSame($user, $this->service->acceptOnBehalf('valid-token', 'New Contributor', 999));
    }

    public function test_accept_throws_for_non_pending_invitation(): void
    {
        $this->invitations->shouldReceive('findByToken')
            ->andReturn($this->makeInvitation(['used_at' => date('Y-m-d H:i:s')]));

        $this->users->shouldNotReceive('ensureContributorAccount');

        $this->expectException(InvalidInvitationException::class);

        $this->service->accept('used-token', 'Jane', 'password');
    }

    public function test_accept_throws_for_missing_invitation_token(): void
    {
        $this->invitations->shouldReceive('findByToken')
            ->with('missing-token')
            ->once()
            ->andReturn(null);

        $this->users->shouldNotReceive('ensureContributorAccount');

        $this->expectException(InvalidInvitationException::class);

        $this->service->accept('missing-token', 'Jane', 'password');
    }

    public function test_accept_throws_for_expired_invitation(): void
    {
        $this->invitations->shouldReceive('findByToken')
            ->andReturn($this->makeInvitation(['expires_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))]));

        $this->users->shouldNotReceive('ensureContributorAccount');

        $this->expectException(InvalidInvitationException::class);

        $this->service->accept('expired-token', 'Jane', 'password');
    }

    public function test_accept_throws_for_revoked_invitation(): void
    {
        $this->invitations->shouldReceive('findByToken')
            ->andReturn($this->makeInvitation(['revoked_at' => date('Y-m-d H:i:s')]));

        $this->users->shouldNotReceive('ensureContributorAccount');

        $this->expectException(InvalidInvitationException::class);

        $this->service->accept('revoked-token', 'Jane', 'password');
    }

    public function test_accept_on_behalf_requires_admin_id(): void
    {
        $this->invitations->shouldNotReceive('findByToken');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Admin ID is required.');

        $this->service->acceptOnBehalf('valid-token', 'Jane', 0);
    }

    public function test_accept_on_behalf_throws_for_missing_invitation_token(): void
    {
        $this->invitations->shouldReceive('findByToken')
            ->with('missing-token')
            ->once()
            ->andReturn(null);

        $this->users->shouldNotReceive('ensureContributorAccount');

        $this->expectException(InvalidInvitationException::class);

        $this->service->acceptOnBehalf('missing-token', 'Jane', 999);
    }

    public function test_accept_on_behalf_throws_for_non_pending_invitation(): void
    {
        $this->invitations->shouldReceive('findByToken')
            ->andReturn($this->makeInvitation(['used_at' => date('Y-m-d H:i:s')]));

        $this->users->shouldNotReceive('ensureContributorAccount');

        $this->expectException(InvalidInvitationException::class);

        $this->service->acceptOnBehalf('used-token', 'Jane', 999);
    }

    public function test_revoke_calls_repository_for_pending_invitation(): void
    {
        $invitation = $this->makeInvitation(['id' => 7]);

        $this->invitations->shouldReceive('find')->with(7)->once()->andReturn($invitation);
        $this->invitations->shouldReceive('revoke')->with(7, 99)->once();

        $this->service->revoke(7, 99);

        $this->assertTrue(true);
    }

    public function test_revoke_rejects_used_invitation(): void
    {
        $this->invitations->shouldReceive('find')
            ->andReturn($this->makeInvitation(['used_at' => date('Y-m-d H:i:s')]));
        $this->invitations->shouldNotReceive('revoke');

        $this->expectException(\InvalidArgumentException::class);

        $this->service->revoke(1, 99);
    }

    public function test_revoke_throws_when_invitation_is_missing(): void
    {
        $this->invitations->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->invitations->shouldNotReceive('revoke');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not found/i');

        $this->service->revoke(999, 99);
    }

    public function test_revoke_allows_expired_invitation_that_was_not_used(): void
    {
        $invitation = $this->makeInvitation([
            'id' => 8,
            'expires_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
        ]);

        $this->invitations->shouldReceive('find')
            ->with(8)
            ->once()
            ->andReturn($invitation);

        $this->invitations->shouldReceive('revoke')
            ->with(8, 99)
            ->once();

        $this->service->revoke(8, 99);

        $this->assertTrue(true);
    }

    private function makeInvitation(array $attributes = []): Invitation
    {
        /** @var Invitation $invitation */
        $invitation = Mockery::mock(Invitation::class)->makePartial();

        foreach (array_merge([
            'id' => 1,
            'site_id' => 1,
            'email' => 'guest@example.com',
            'token' => 'valid-token',
            'invited_by' => 99,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+72 hours')),
            'used_at' => null,
            'revoked_at' => null,
        ], $attributes) as $key => $value) {
            $invitation->setAttribute($key, $value);
        }

        $invitation->setExists(true);

        return $invitation;
    }

    private function makeUser(array $attributes = []): User
    {
        /** @var User $user */
        $user = Mockery::mock(User::class)->makePartial();

        foreach (array_merge([
            'id' => 10,
            'name' => 'Jane Doe',
            'email' => 'guest@example.com',
            'role' => 'contributor',
            'is_contributor' => true,
            'is_active' => true,
        ], $attributes) as $key => $value) {
            $user->setAttribute($key, $value);
        }

        $user->setExists(true);

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->invitations = Mockery::mock(InvitationRepositoryInterface::class);
        $this->users = Mockery::mock(UserLifecycleServiceInterface::class);
        $this->authorisation = Mockery::mock(OpenCollabAuthorisationInterface::class);
        $this->onboarding = Mockery::mock(ContributorOnboardingService::class);
        $this->profiles = Mockery::mock(ContributorProfileRepository::class);
        $this->events = Mockery::mock(EventDispatcher::class);
        $this->database = Mockery::mock(Database::class);
        $this->notifications = Mockery::mock(NotificationDispatcher::class);

        $this->database->shouldReceive('transaction')
            ->andReturnUsing(fn (callable $callback) => $callback());

        $this->database->shouldReceive('afterCommit')
            ->andReturnUsing(fn (callable $callback) => $callback())
            ->byDefault();

        $this->users->shouldReceive('findByEmail')
            ->andReturn(null)
            ->byDefault();

        $this->authorisation->shouldReceive('hasContributorAccess')
            ->andReturn(false)
            ->byDefault();

        $this->notifications->shouldReceive('dispatch')
            ->andReturn(1)
            ->byDefault();

        $this->service = new InvitationService(
            $this->invitations,
            $this->users,
            $this->authorisation,
            $this->onboarding,
            $this->profiles,
            $this->events,
            $this->database,
            $this->notifications,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
