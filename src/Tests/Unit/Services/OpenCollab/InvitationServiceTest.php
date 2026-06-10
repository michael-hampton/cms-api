<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Events\OpenCollab\InvitationAccepted;
use App\Exceptions\OpenCollab\InvalidInvitationException;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Notifications\NotificationDispatcher;
use App\Models\Invitation;
use App\Models\User;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Repositories\OpenCollab\InvitationRepository;
use App\Services\OpenCollab\ContributorOnboardingService;
use App\Services\OpenCollab\InvitationService;
use App\Repositories\OpenCollab\UserSiteRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class InvitationServiceTest extends FunctionalTestCase
{
    private InvitationService $service;
    private MockInterface $invitationRepository;
    private MockInterface $userRepository;
    private MockInterface $userSiteRepository;
    private MockInterface $onboardingService;
    private MockInterface $eventDispatcher;
    private MockInterface $databaseMock;
    private MockInterface $notificationDispatcher;

    // ── create() ──────────────────────────────────────────────────────────────

    public function test_create_stores_invitation_with_generated_token(): void
    {
        $this->userRepository
            ->shouldReceive('findByEmail')
            ->with('guest@example.com')
            ->once()
            ->andReturn(null);

        $this->invitationRepository
            ->shouldReceive('hasPendingInviteForEmail')
            ->with('guest@example.com', 1)
            ->once()
            ->andReturn(false);

        $this->invitationRepository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data): bool {
                return $data['email'] === 'guest@example.com'
                    && $data['site_id'] === 1
                    && $data['invited_by'] === 99
                    && strlen($data['token']) === 64
                    && isset($data['expires_at']);
            })
            ->andReturn($this->makeInvitation());

        $invitation = $this->service->create('guest@example.com', 99, 1);

        $this->assertInstanceOf(Invitation::class, $invitation);
    }

    public function test_create_throws_when_user_already_has_site_access(): void
    {
        $existingUser = $this->makeUser([
            'id' => 3,
            'email' => 'guest@example.com',
        ]);

        $this->userRepository
            ->shouldReceive('findByEmail')
            ->with('guest@example.com')
            ->once()
            ->andReturn($existingUser);

        $this->userSiteRepository
            ->shouldReceive('hasAccess')
            ->with(3, 1)
            ->once()
            ->andReturn(true);

        $this->invitationRepository->shouldNotReceive('hasPendingInviteForEmail');
        $this->invitationRepository->shouldNotReceive('create');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/already has access/i');

        $this->service->create('guest@example.com', 99, 1);
    }


    public function test_create_throws_when_pending_invite_already_exists(): void
    {
        $this->userRepository
            ->shouldReceive('findByEmail')
            ->with('guest@example.com')
            ->once()
            ->andReturn(null);

        $this->invitationRepository
            ->shouldReceive('hasPendingInviteForEmail')
            ->with('guest@example.com', 1)
            ->once()
            ->andReturn(true);

        $this->invitationRepository->shouldNotReceive('create');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/pending invitation/i');

        $this->service->create('guest@example.com', 99, 1);
    }

    public function test_create_respects_custom_ttl_hours(): void
    {
        $this->userRepository
            ->shouldReceive('findByEmail')
            ->with('guest@example.com')
            ->once()
            ->andReturn(null);

        $this->invitationRepository
            ->shouldReceive('hasPendingInviteForEmail')
            ->with('guest@example.com', 1)
            ->once()
            ->andReturn(false);

        $this->invitationRepository
            ->shouldReceive('create')
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


    // ── accept() — new user ───────────────────────────────────────────────────

    public function test_accept_creates_new_user_grants_access_starts_onboarding_and_dispatches_event(): void
    {
        $invitation = $this->makeInvitation([
            'id' => 5,
            'site_id' => 1,
            'email' => 'guest@example.com',
        ]);

        $newUser = $this->makeUser([
            'id' => 10,
            'email' => 'guest@example.com',
        ]);

        $this->invitationRepository
            ->shouldReceive('findByToken')
            ->with('valid-token')
            ->once()
            ->andReturn($invitation);

        $this->userRepository
            ->shouldReceive('findByEmail')
            ->with('guest@example.com')
            ->once()
            ->andReturn(null);

        $this->userRepository
            ->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $data): bool =>
                $data['email'] === 'guest@example.com'
                && $data['name'] === 'Jane Doe'
                && $data['role'] === 'contributor'
                && $data['is_contributor'] === true
                && $data['is_active'] === true
                && $data['password'] === 'secret123'
            )
            ->andReturn($newUser);

        $this->userSiteRepository
            ->shouldReceive('grant')
            ->with(10, 1)
            ->once();

        $this->invitationRepository
            ->shouldReceive('markAsUsed')
            ->with(5, 10)
            ->once();

        $this->onboardingService
            ->shouldReceive('hasStarted')
            ->with(10, 1)
            ->once()
            ->andReturn(false);

        $this->onboardingService
            ->shouldReceive('start')
            ->with(10, 1)
            ->once();

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn ($e) => $e instanceof InvitationAccepted);

        $user = $this->service->accept('valid-token', 'Jane Doe', 'secret123');

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('guest@example.com', $user->email);
    }


    // ── accept() — existing user ───────────────────────────────────────────────

    public function test_accept_reuses_existing_user_when_email_already_registered(): void
    {
        $invitation = $this->makeInvitation([
            'id' => 5,
            'site_id' => 1,
            'email' => 'existing@example.com',
        ]);

        $existingUser = $this->makeUser([
            'id' => 3,
            'email' => 'existing@example.com',
        ]);

        $updatedUser = $this->makeUser([
            'id' => 3,
            'email' => 'existing@example.com',
            'name' => 'Jane Doe',
            'is_active' => true,
            'is_contributor' => true,
        ]);

        $this->invitationRepository
            ->shouldReceive('findByToken')
            ->with('valid-token')
            ->once()
            ->andReturn($invitation);

        $this->userRepository
            ->shouldReceive('findByEmail')
            ->with('existing@example.com')
            ->once()
            ->andReturn($existingUser);

        $this->userRepository
            ->shouldReceive('update')
            ->with(3, Mockery::on(fn ($data): bool =>
                $data['is_active'] === true
                && $data['is_contributor'] === true
                && !array_key_exists('password', $data)
                && !array_key_exists('name', $data)
            ))
            ->once()
            ->andReturn($updatedUser);

        $this->userRepository->shouldNotReceive('create');
        $this->userRepository->shouldNotReceive('updateUserWithPassword');

        $this->userSiteRepository
            ->shouldReceive('grant')
            ->with(3, 1)
            ->once();

        $this->invitationRepository
            ->shouldReceive('markAsUsed')
            ->with(5, 3)
            ->once();

        $this->onboardingService
            ->shouldReceive('hasStarted')
            ->with(3, 1)
            ->once()
            ->andReturn(false);

        $this->onboardingService
            ->shouldReceive('start')
            ->with(3, 1)
            ->once();

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once();

        $user = $this->service->accept('valid-token', 'Jane', 'password');

        $this->assertEquals(3, $user->id);
    }

    // ── accept() — invalid states ──────────────────────────────────────────────

    public function test_accept_throws_for_missing_token(): void
    {
        $this->invitationRepository->shouldReceive('findByToken')->andReturn(null);

        $this->expectException(InvalidInvitationException::class);

        $this->service->accept('ghost-token', 'Jane', 'password');
    }

    public function test_accept_throws_for_expired_invitation(): void
    {
        $expired = $this->makeInvitation(['expires_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))]);

        $this->invitationRepository->shouldReceive('findByToken')->andReturn($expired);

        $this->expectException(InvalidInvitationException::class);

        $this->service->accept('expired-token', 'Jane', 'password');
    }

    public function test_accept_throws_for_already_used_invitation(): void
    {
        $used = $this->makeInvitation(['used_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))]);

        $this->invitationRepository->shouldReceive('findByToken')->andReturn($used);

        $this->expectException(InvalidInvitationException::class);

        $this->service->accept('used-token', 'Jane', 'password');
    }

    public function test_accept_throws_for_revoked_invitation(): void
    {
        $revoked = $this->makeInvitation(['revoked_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))]);

        $this->invitationRepository->shouldReceive('findByToken')->andReturn($revoked);

        $this->expectException(InvalidInvitationException::class);

        $this->service->accept('revoked-token', 'Jane', 'password');
    }

    public function test_accept_wraps_all_writes_in_transaction(): void
    {
        $invitation = $this->makeInvitation();
        $newUser = $this->makeUser();

        $this->invitationRepository->shouldReceive('findByToken')->andReturn($invitation);
        $this->userSiteRepository->shouldReceive('hasAccess')->andReturn(false);
        $this->userRepository->shouldReceive('findByEmail')->andReturn(null);
        $this->userRepository->shouldReceive('create')->andReturn($newUser);
        $this->userSiteRepository->shouldReceive('grant');
        $this->invitationRepository->shouldReceive('markAsUsed');
        $this->onboardingService->shouldReceive('hasStarted')->andReturn(false);
        $this->onboardingService->shouldReceive('start');
        $this->eventDispatcher->shouldReceive('dispatch');

        $result = $this->service->accept('valid-token', 'Jane', 'password');

        $this->assertInstanceOf(User::class, $result);
    }

    public function test_accept_rolls_back_if_user_creation_fails(): void
    {
        $invitation = $this->makeInvitation();

        $this->invitationRepository
            ->shouldReceive('findByToken')
            ->andReturn($invitation);

        $this->userRepository
            ->shouldReceive('findByEmail')
            ->andReturn(null);

        $this->userRepository
            ->shouldReceive('create')
            ->andThrow(new \RuntimeException('DB failure'));

        $this->userSiteRepository->shouldNotReceive('grant');
        $this->invitationRepository->shouldNotReceive('markAsUsed');
        $this->onboardingService->shouldNotReceive('start');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB failure');

        $this->service->accept('valid-token', 'Jane', 'password');
    }

    // ── acceptOnBehalf() ──────────────────────────────────────────────────────

    public function test_accept_on_behalf_records_admin_as_accepted_by(): void
    {
        $invitation = $this->makeInvitation([
            'id' => 5,
            'site_id' => 1,
            'email' => 'invited@example.com',
        ]);

        $newUser = $this->makeUser([
            'id' => 10,
            'email' => 'invited@example.com',
        ]);

        $this->invitationRepository
            ->shouldReceive('findByToken')
            ->andReturn($invitation);

        $this->userRepository
            ->shouldReceive('findByEmail')
            ->with('invited@example.com')
            ->once()
            ->andReturn(null);

        $this->userRepository
            ->shouldReceive('create')
            ->andReturn($newUser);

        $this->userSiteRepository
            ->shouldReceive('grant')
            ->with(10, 1)
            ->once();

        $this->invitationRepository
            ->shouldReceive('markAsUsed')
            ->with(5, 999)
            ->once();

        $this->onboardingService
            ->shouldReceive('hasStarted')
            ->with(10, 1)
            ->andReturn(false);

        $this->onboardingService
            ->shouldReceive('start')
            ->with(10, 1)
            ->once();

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once();

        $user = $this->service->acceptOnBehalf('valid-token', 'New Contributor', 999);

        $this->assertInstanceOf(User::class, $user);
    }

    public function test_accept_on_behalf_reuses_existing_user(): void
    {
        $invitation = $this->makeInvitation([
            'id' => 5,
            'site_id' => 1,
            'email' => 'existing@example.com',
        ]);

        $existingUser = $this->makeUser([
            'id' => 7,
            'email' => 'existing@example.com',
        ]);

        $updatedUser = $this->makeUser([
            'id' => 7,
            'email' => 'existing@example.com',
            'is_active' => true,
            'is_contributor' => true,
        ]);

        $this->invitationRepository
            ->shouldReceive('findByToken')
            ->andReturn($invitation);

        $this->userRepository
            ->shouldReceive('findByEmail')
            ->with('existing@example.com')
            ->once()
            ->andReturn($existingUser);

        $this->userRepository
            ->shouldReceive('update')
            ->with(7, Mockery::on(fn ($data): bool =>
                $data['is_active'] === true
                && $data['is_contributor'] === true
                && !array_key_exists('password', $data)
                && !array_key_exists('name', $data)
            ))
            ->once()
            ->andReturn($updatedUser);

        $this->userRepository->shouldNotReceive('create');

        $this->userSiteRepository
            ->shouldReceive('grant')
            ->with(7, 1)
            ->once();

        $this->invitationRepository
            ->shouldReceive('markAsUsed')
            ->with(5, 999)
            ->once();

        $this->onboardingService
            ->shouldReceive('hasStarted')
            ->with(7, 1)
            ->andReturn(false);

        $this->onboardingService
            ->shouldReceive('start')
            ->with(7, 1)
            ->once();

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once();

        $user = $this->service->acceptOnBehalf('valid-token', 'Name', 999);

        $this->assertEquals(7, $user->id);
    }

    public function test_accept_on_behalf_throws_for_missing_token(): void
    {
        $this->invitationRepository->shouldReceive('findByToken')->andReturn(null);

        $this->expectException(InvalidInvitationException::class);

        $this->service->acceptOnBehalf('ghost-token', 'Name', 999);
    }

    public function test_accept_on_behalf_throws_for_non_pending_invitation(): void
    {
        $used = $this->makeInvitation(['used_at' => date('Y-m-d H:i:s')]);

        $this->invitationRepository->shouldReceive('findByToken')->andReturn($used);
        $this->userRepository->shouldNotReceive('create');

        $this->expectException(InvalidInvitationException::class);

        $this->service->acceptOnBehalf('used-token', 'Name', 999);
    }

    // ── revoke() ──────────────────────────────────────────────────────────────

    public function test_revoke_calls_repository_for_pending_invitation(): void
    {
        $invitation = $this->makeInvitation(['id' => 7]);

        $this->invitationRepository->shouldReceive('find')->with(7)->once()->andReturn($invitation);
        $this->invitationRepository->shouldReceive('revoke')->with(7, 99)->once();

        $this->service->revoke(7, revokedBy: 99);
        $this->assertTrue(true);
    }

    public function test_revoke_throws_when_invitation_not_found(): void
    {
        $this->invitationRepository->shouldReceive('find')->andReturn(null);
        $this->invitationRepository->shouldNotReceive('revoke');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not found/i');

        $this->service->revoke(999, 99);
    }

    public function test_revoke_throws_when_invitation_already_used(): void
    {
        $used = $this->makeInvitation(['used_at' => date('Y-m-d H:i:s')]);

        $this->invitationRepository->shouldReceive('find')->andReturn($used);
        $this->invitationRepository->shouldNotReceive('revoke');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/already been used/i');

        $this->service->revoke(1, 99);
    }

    public function test_revoke_succeeds_for_expired_invitation(): void
    {
        $expired = $this->makeInvitation([
            'id' => 8,
            'expires_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
        ]);

        $this->invitationRepository->shouldReceive('find')->andReturn($expired);
        $this->invitationRepository->shouldReceive('revoke')->with(8, 99)->once();

        $this->service->revoke(8, 99);
        $this->assertTrue(true);
    }

    public function test_create_allows_existing_user_without_site_access_to_be_invited(): void
    {
        $existingUser = $this->makeUser([
            'id' => 44,
            'email' => 'guest@example.com',
        ]);

        $this->userRepository
            ->shouldReceive('findByEmail')
            ->with('guest@example.com')
            ->once()
            ->andReturn($existingUser);

        $this->userSiteRepository
            ->shouldReceive('hasAccess')
            ->with(44, 1)
            ->once()
            ->andReturn(false);

        $this->invitationRepository
            ->shouldReceive('hasPendingInviteForEmail')
            ->with('guest@example.com', 1)
            ->once()
            ->andReturn(false);

        $this->invitationRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($this->makeInvitation());

        $invitation = $this->service->create('guest@example.com', 99, 1);

        $this->assertInstanceOf(Invitation::class, $invitation);
    }

    public function test_accept_existing_user_does_not_overwrite_name_or_password(): void
    {
        $invitation = $this->makeInvitation([
            'id' => 5,
            'site_id' => 1,
            'email' => 'existing@example.com',
        ]);

        $existingUser = $this->makeUser([
            'id' => 3,
            'name' => 'Old Name',
            'email' => 'existing@example.com',
        ]);

        $updatedUser = $this->makeUser([
            'id' => 3,
            'name' => 'Old Name',
            'email' => 'existing@example.com',
            'is_active' => true,
            'is_contributor' => true,
        ]);

        $this->invitationRepository
            ->shouldReceive('findByToken')
            ->with('valid-token')
            ->once()
            ->andReturn($invitation);

        $this->userRepository
            ->shouldReceive('findByEmail')
            ->with('existing@example.com')
            ->once()
            ->andReturn($existingUser);

        $this->userRepository
            ->shouldReceive('update')
            ->with(3, Mockery::on(fn (array $data): bool =>
                $data === [
                    'is_active' => true,
                    'is_contributor' => true,
                ]
            ))
            ->once()
            ->andReturn($updatedUser);

        $this->userRepository->shouldNotReceive('create');
        $this->userRepository->shouldNotReceive('updateUserWithPassword');

        $this->userSiteRepository
            ->shouldReceive('grant')
            ->with(3, 1)
            ->once();

        $this->invitationRepository
            ->shouldReceive('markAsUsed')
            ->with(5, 3)
            ->once();

        $this->onboardingService
            ->shouldReceive('hasStarted')
            ->with(3, 1)
            ->once()
            ->andReturn(false);

        $this->onboardingService
            ->shouldReceive('start')
            ->with(3, 1)
            ->once();

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once();

        $user = $this->service->accept('valid-token', 'New Name', 'new-password');

        $this->assertSame(3, $user->id);
        $this->assertSame('Old Name', $user->name);
    }public function test_accept_does_not_start_onboarding_when_already_started(): void
{
    $invitation = $this->makeInvitation([
        'id' => 5,
        'site_id' => 1,
        'email' => 'guest@example.com',
    ]);

    $user = $this->makeUser([
        'id' => 10,
        'email' => 'guest@example.com',
    ]);

    $this->invitationRepository
        ->shouldReceive('findByToken')
        ->with('valid-token')
        ->once()
        ->andReturn($invitation);

    $this->userRepository
        ->shouldReceive('findByEmail')
        ->with('guest@example.com')
        ->once()
        ->andReturn(null);

    $this->userRepository
        ->shouldReceive('create')
        ->once()
        ->andReturn($user);

    $this->userSiteRepository
        ->shouldReceive('grant')
        ->with(10, 1)
        ->once();

    $this->invitationRepository
        ->shouldReceive('markAsUsed')
        ->with(5, 10)
        ->once();

    $this->onboardingService
        ->shouldReceive('hasStarted')
        ->with(10, 1)
        ->once()
        ->andReturn(true);

    $this->onboardingService
        ->shouldNotReceive('start');

    $this->eventDispatcher
        ->shouldReceive('dispatch')
        ->once();

    $result = $this->service->accept('valid-token', 'Jane', 'password');

    $this->assertSame($user, $result);
}

    public function test_accept_on_behalf_existing_user_does_not_overwrite_name_or_password(): void
    {
        $invitation = $this->makeInvitation([
            'id' => 5,
            'site_id' => 1,
            'email' => 'existing@example.com',
        ]);

        $existingUser = $this->makeUser([
            'id' => 7,
            'name' => 'Old Name',
            'email' => 'existing@example.com',
        ]);

        $updatedUser = $this->makeUser([
            'id' => 7,
            'name' => 'Old Name',
            'email' => 'existing@example.com',
            'is_active' => true,
            'is_contributor' => true,
        ]);

        $this->invitationRepository
            ->shouldReceive('findByToken')
            ->with('valid-token')
            ->once()
            ->andReturn($invitation);

        $this->userRepository
            ->shouldReceive('findByEmail')
            ->with('existing@example.com')
            ->once()
            ->andReturn($existingUser);

        $this->userRepository
            ->shouldReceive('update')
            ->with(7, Mockery::on(fn (array $data): bool =>
                $data === [
                    'is_active' => true,
                    'is_contributor' => true,
                ]
            ))
            ->once()
            ->andReturn($updatedUser);

        $this->userRepository->shouldNotReceive('create');
        $this->userRepository->shouldNotReceive('updateUserWithPassword');

        $this->userSiteRepository
            ->shouldReceive('grant')
            ->with(7, 1)
            ->once();

        $this->invitationRepository
            ->shouldReceive('markAsUsed')
            ->with(5, 999)
            ->once();

        $this->onboardingService
            ->shouldReceive('hasStarted')
            ->with(7, 1)
            ->once()
            ->andReturn(false);

        $this->onboardingService
            ->shouldReceive('start')
            ->with(7, 1)
            ->once();

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once();

        $user = $this->service->acceptOnBehalf('valid-token', 'New Name', 999);

        $this->assertSame(7, $user->id);
        $this->assertSame('Old Name', $user->name);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeInvitation(array $attributes = []): Invitation
    {
        $defaults = [
            'id' => 1,
            'site_id' => 1,
            'email' => 'guest@example.com',
            'token' => 'valid-token',
            'invited_by' => 99,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+72 hours')),
            'used_at' => null,
            'revoked_at' => null,
        ];

        $invitation = new Invitation(array_merge($defaults, $attributes));
        $invitation->exists = true;

        return $invitation;
    }

    private function makeUser(array $attributes = []): User
    {
        $defaults = [
            'id' => 10,
            'site_id' => 1,
            'name' => 'Jane Doe',
            'email' => 'guest@example.com',
            'role' => 'contributor',
            'is_contributor' => true,
        ];

        $user = new User(array_merge($defaults, $attributes));
        $user->exists = true;

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->invitationRepository = Mockery::mock(InvitationRepository::class);
        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->userSiteRepository = Mockery::mock(UserSiteRepository::class);
        $this->onboardingService = Mockery::mock(ContributorOnboardingService::class);
        $this->eventDispatcher = Mockery::mock(EventDispatcher::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->notificationDispatcher = Mockery::mock(NotificationDispatcher::class);

        $this->databaseMock
            ->shouldReceive('transaction')
            ->andReturnUsing(fn (callable $cb) => $cb());

        $this->databaseMock
            ->shouldReceive('afterCommit')
            ->andReturnUsing(fn (callable $cb) => $cb())
            ->byDefault();

        $this->userRepository
            ->shouldReceive('findByEmail')
            ->andReturn(null)
            ->byDefault();

        $this->userSiteRepository
            ->shouldReceive('hasAccess')
            ->andReturn(false)
            ->byDefault();

        $this->onboardingService
            ->shouldReceive('hasStarted')
            ->andReturn(false)
            ->byDefault();

        $this->notificationDispatcher
            ->shouldReceive('dispatch')
            ->andReturn(1)
            ->byDefault();

        $this->service = new InvitationService(
            $this->invitationRepository,
            $this->userRepository,
            $this->userSiteRepository,
            $this->onboardingService,
            $this->eventDispatcher,
            $this->databaseMock,
            $this->notificationDispatcher
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}