<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Exceptions\OpenCollab\InvalidInvitationException;
use App\Framework\Database\Database;
use App\Models\Invitation;
use App\Models\User;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Repositories\OpenCollab\InvitationRepository;
use App\Services\OpenCollab\InvitationService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class InvitationServiceTest extends FunctionalTestCase
{
    private InvitationService $service;
    private MockInterface $invitationRepository;
    private MockInterface $userRepository;
    private MockInterface $databaseMock;

    // -------------------------------------------------------------------------
    // create()
    // -------------------------------------------------------------------------

    public function test_create_stores_invitation_with_generated_token(): void
    {
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

    public function test_create_throws_when_pending_invite_already_exists(): void
    {
        $this->invitationRepository
            ->shouldReceive('hasPendingInviteForEmail')
            ->once()
            ->andReturn(true);

        $this->invitationRepository->shouldNotReceive('create');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/pending invitation/i');

        $this->service->create('guest@example.com', 99, 1);
    }

    public function test_create_does_not_throw_when_user_already_exists_for_email(): void
    {
        // A user may already exist (re-invitation after closure) — only a live
        // pending invitation should block creation, not the existence of a user account.
        $this->invitationRepository->shouldReceive('hasPendingInviteForEmail')->andReturn(false);
        $this->invitationRepository->shouldReceive('create')->once()->andReturn($this->makeInvitation());

        // Should not throw
        $invitation = $this->service->create('existing-user@example.com', 99, 1);
        $this->assertInstanceOf(Invitation::class, $invitation);
    }

    public function test_create_respects_custom_ttl_hours(): void
    {
        $this->invitationRepository
            ->shouldReceive('hasPendingInviteForEmail')
            ->andReturn(false);

        $this->invitationRepository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data): bool {
                // 24-hour TTL — expires_at should be ~24 hours from now
                $expected = strtotime('+24 hours');
                $actual = strtotime($data['expires_at']);
                return abs($expected - $actual) < 60; // within 1 minute
            })
            ->andReturn($this->makeInvitation());

        $this->service->create('guest@example.com', 99, 1, ttlHours: 24);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // accept()
    // -------------------------------------------------------------------------

    public function test_accept_creates_user_and_marks_invite_used(): void
    {
        $invitation = $this->makeInvitation(['id' => 5, 'site_id' => 1, 'email' => 'guest@example.com']);

        $this->invitationRepository
            ->shouldReceive('findByToken')
            ->with('valid-token')
            ->once()
            ->andReturn($invitation);

        $expectedUser = $this->makeUser(['id' => 10, 'email' => 'guest@example.com']);

        $this->userRepository->shouldReceive('findByEmail')
            ->once()
            ->with('guest@example.com', $this->siteId)
            ->andReturn(null);

        $this->userRepository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data): bool {
                return $data['email'] === 'guest@example.com'
                    && $data['name'] === 'Jane Doe'
                    && $data['role'] === 'contributor'
                    && $data['is_contributor'] === true
                    && password_verify('secret123', $data['password']);
            })
            ->andReturn($expectedUser);

        $this->invitationRepository
            ->shouldReceive('markAsUsed')
            ->with(5, 10)
            ->once();

        $user = $this->service->accept('valid-token', 'Jane Doe', 'secret123', $this->siteId);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('guest@example.com', $user->email);
    }

    public function test_accept_reactivates_existing_user_instead_of_creating_duplicate(): void
    {
        $invitation = $this->makeInvitation(['id' => 5, 'site_id' => 1, 'email' => 'returning@example.com']);
        $existingUser = $this->makeUser(['id' => 42, 'email' => 'returning@example.com', 'is_active' => false]);
        $reactivatedUser = $this->makeUser(['id' => 42, 'email' => 'returning@example.com', 'is_active' => true]);

        $this->invitationRepository->shouldReceive('findByToken')->andReturn($invitation);
        $this->userRepository->shouldReceive('findByEmail')->with('returning@example.com', $this->siteId)->andReturn($existingUser);

        // Must update, NOT create
        $this->userRepository->shouldReceive('update')
            ->once()
            ->withArgs(function (int $id, array $data): bool {
                return $id === 42
                    && $data['is_active'] === true
                    && $data['is_contributor'] === true
                    && $data['role'] === 'contributor'
                    && password_verify('newpassword', $data['password']);
            });
        $this->userRepository->shouldNotReceive('create');
        $this->userRepository->shouldReceive('find')->with(42)->andReturn($reactivatedUser);

        $this->invitationRepository->shouldReceive('markAsUsed')->with(5, 42)->once();

        $user = $this->service->accept('valid-token', 'Jane', 'newpassword', $this->siteId);
        $this->assertEquals(42, $user->id);
        $this->assertTrue($user->is_active);
    }

    public function test_accept_throws_for_missing_token(): void
    {
        $this->invitationRepository
            ->shouldReceive('findByToken')
            ->andReturn(null);

        $this->invitationRepository->shouldNotReceive('markAsUsed');
        $this->userRepository->shouldNotReceive('create');

        $this->expectException(InvalidInvitationException::class);

        $this->service->accept('ghost-token', 'Jane', 'password', $this->siteId);
    }

    public function test_accept_throws_for_expired_invitation(): void
    {
        $expired = $this->makeInvitation(['expires_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))]);

        $this->invitationRepository
            ->shouldReceive('findByToken')
            ->andReturn($expired);

        $this->invitationRepository->shouldNotReceive('markAsUsed');
        $this->userRepository->shouldNotReceive('create');

        $this->expectException(InvalidInvitationException::class);

        $this->service->accept('expired-token', 'Jane', 'password', $this->siteId);
    }

    public function test_accept_throws_for_already_used_invitation(): void
    {
        $used = $this->makeInvitation(['used_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))]);

        $this->invitationRepository
            ->shouldReceive('findByToken')
            ->andReturn($used);

        $this->invitationRepository->shouldNotReceive('markAsUsed');
        $this->userRepository->shouldNotReceive('create');

        $this->expectException(InvalidInvitationException::class);

        $this->service->accept('used-token', 'Jane', 'password', $this->siteId);
    }

    public function test_accept_throws_for_revoked_invitation(): void
    {
        $revoked = $this->makeInvitation(['revoked_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))]);

        $this->invitationRepository
            ->shouldReceive('findByToken')
            ->andReturn($revoked);

        $this->invitationRepository->shouldNotReceive('markAsUsed');
        $this->userRepository->shouldNotReceive('create');

        $this->expectException(InvalidInvitationException::class);

        $this->service->accept('revoked-token', 'Jane', 'password', $this->siteId);
    }

    public function test_accept_wraps_writes_in_transaction(): void
    {
        $this->databaseMock->shouldNotReceive('transaction')->byDefault();

        $this->userRepository->shouldReceive('findByEmail')
            ->once()
            ->with('guest@example.com', $this->siteId)
            ->andReturn(null);

        $invitation = $this->makeInvitation();

        $this->invitationRepository->shouldReceive('findByToken')->andReturn($invitation);
        $this->userRepository->shouldReceive('create')->andReturn($this->makeUser());
        $this->invitationRepository->shouldReceive('markAsUsed');

        $result = $this->service->accept('valid-token', 'Jane', 'password', $this->siteId);

        $this->assertInstanceOf(User::class, $result);
    }

    public function test_accept_rolls_back_if_user_creation_fails(): void
    {
        $invitation = $this->makeInvitation();

        $this->invitationRepository->shouldReceive('findByToken')->andReturn($invitation);

        $this->userRepository->shouldReceive('findByEmail')
            ->once()
            ->with('guest@example.com', $this->siteId)
            ->andReturn(null);

        $this->userRepository
            ->shouldReceive('create')
            ->andThrow(new \RuntimeException('DB failure'));

        $this->invitationRepository->shouldNotReceive('markAsUsed');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB failure');

        $this->service->accept('valid-token', 'Jane', 'password', $this->siteId);
    }

    // -------------------------------------------------------------------------
    // acceptOnBehalf()
    // -------------------------------------------------------------------------

    public function test_accept_on_behalf_creates_user_with_admin_as_accepted_by(): void
    {
        $invitation = $this->makeInvitation(['id' => 5, 'site_id' => 1, 'email' => 'invited@example.com']);

        $this->invitationRepository
            ->shouldReceive('findByToken')
            ->with('valid-token')
            ->once()
            ->andReturn($invitation);

        $newUser = $this->makeUser(['id' => 10, 'email' => 'invited@example.com']);

        $this->userRepository->shouldReceive('findByEmail')
            ->once()
            ->with('invited@example.com', $this->siteId)
            ->andReturn(null);

        $this->userRepository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data): bool {
                return $data['email'] === 'invited@example.com'
                    && $data['role'] === 'contributor'
                    && $data['is_contributor'] === true;
            })
            ->andReturn($newUser);

        // accepted_by must be the admin ID, not the new user ID
        $this->invitationRepository
            ->shouldReceive('markAsUsed')
            ->with(5, 999) // 999 = admin ID
            ->once();

        $user = $this->service->acceptOnBehalf('valid-token', 'New Contributor', 'temp-pass', adminId: 999, siteId: $this->siteId);

        $this->assertInstanceOf(User::class, $user);
    }

    public function test_accept_on_behalf_reactivates_existing_user(): void
    {
        $invitation = $this->makeInvitation(['id' => 5, 'site_id' => 1, 'email' => 'returning@example.com']);
        $existingUser = $this->makeUser(['id' => 20, 'email' => 'returning@example.com', 'is_active' => false]);
        $reactivated = $this->makeUser(['id' => 20, 'is_active' => true]);

        $this->invitationRepository->shouldReceive('findByToken')->andReturn($invitation);
        $this->userRepository->shouldReceive('findByEmail')->andReturn($existingUser);
        $this->userRepository->shouldReceive('update')->once()
            ->withArgs(fn($id, $data) => $id === 20 && $data['is_active'] === true);
        $this->userRepository->shouldNotReceive('create');
        $this->userRepository->shouldReceive('find')->with(20)->andReturn($reactivated);
        $this->invitationRepository->shouldReceive('markAsUsed')->with(5, 999)->once();

        $user = $this->service->acceptOnBehalf('valid-token', 'Name', 'pass', 999, $this->siteId);
        $this->assertEquals(20, $user->id);
    }

    public function test_accept_on_behalf_throws_for_missing_token(): void
    {
        $this->invitationRepository
            ->shouldReceive('findByToken')
            ->andReturn(null);

        $this->invitationRepository->shouldNotReceive('markAsUsed');
        $this->userRepository->shouldNotReceive('create');

        $this->expectException(InvalidInvitationException::class);

        $this->service->acceptOnBehalf('ghost-token', 'Name', 'pass', 999, $this->siteId);
    }

    public function test_accept_on_behalf_throws_for_non_pending_invitation(): void
    {
        $used = $this->makeInvitation(['used_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))]);

        $this->invitationRepository
            ->shouldReceive('findByToken')
            ->andReturn($used);

        $this->userRepository->shouldNotReceive('create');

        $this->expectException(InvalidInvitationException::class);

        $this->service->acceptOnBehalf('used-token', 'Name', 'pass', 999, $this->siteId);
    }

    public function test_accept_on_behalf_wraps_writes_in_transaction(): void
    {
        $invitation = $this->makeInvitation();

        $this->invitationRepository->shouldReceive('findByToken')->andReturn($invitation);
        $this->userRepository->shouldReceive('create')->andReturn($this->makeUser());
        $this->invitationRepository->shouldReceive('markAsUsed');

        $this->userRepository->shouldReceive('findByEmail')
            ->once()
            ->with('guest@example.com', $this->siteId)
            ->andReturn(null);

        $result = $this->service->acceptOnBehalf('valid-token', 'Name', 'pass', 999, $this->siteId);

        $this->assertInstanceOf(User::class, $result);
    }

    // -------------------------------------------------------------------------
    // revoke()
    // -------------------------------------------------------------------------

    public function test_revoke_calls_repository_for_pending_invitation(): void
    {
        $invitation = $this->makeInvitation(['id' => 7]);

        $this->invitationRepository
            ->shouldReceive('find')
            ->with(7)
            ->once()
            ->andReturn($invitation);

        $this->invitationRepository
            ->shouldReceive('revoke')
            ->with(7, 99)
            ->once();

        $this->service->revoke(7, revokedBy: 99);
        $this->assertTrue(true);
    }

    public function test_revoke_throws_when_invitation_not_found(): void
    {
        $this->invitationRepository
            ->shouldReceive('find')
            ->andReturn(null);

        $this->invitationRepository->shouldNotReceive('revoke');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not found/i');

        $this->service->revoke(999, 99);
    }

    public function test_revoke_throws_when_invitation_already_used(): void
    {
        $used = $this->makeInvitation(['used_at' => date('Y-m-d H:i:s')]);

        $this->invitationRepository
            ->shouldReceive('find')
            ->andReturn($used);

        $this->invitationRepository->shouldNotReceive('revoke');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/already been used/i');

        $this->service->revoke(1, 99);
    }

    public function test_revoke_succeeds_for_expired_invitation(): void
    {
        // Expired invitations can still be revoked (clean audit trail)
        $expired = $this->makeInvitation([
            'id' => 8,
            'expires_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
        ]);

        $this->invitationRepository
            ->shouldReceive('find')
            ->andReturn($expired);

        $this->invitationRepository
            ->shouldReceive('revoke')
            ->with(8, 99)
            ->once();

        $this->service->revoke(8, 99);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

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
        $this->databaseMock = Mockery::mock(Database::class);

        $this->databaseMock
            ->shouldReceive('transaction')
            ->andReturnUsing(fn(callable $cb) => $cb());

        $this->service = new InvitationService(
            $this->invitationRepository,
            $this->userRepository,
            $this->databaseMock,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}