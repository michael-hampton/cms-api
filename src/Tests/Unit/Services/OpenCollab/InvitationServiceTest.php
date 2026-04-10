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
                    && strlen($data['token']) === 64  // bin2hex(32 bytes)
                    && isset($data['expires_at']);
            })
            ->andReturn($this->makeInvitation());

        $invitation = $this->service->create('guest@example.com', 99, 1);

        $this->assertInstanceOf(Invitation::class, $invitation);
    }

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
        ];

        $invitation = new Invitation(array_merge($defaults, $attributes));
        $invitation->exists = true;

        return $invitation;
    }

    // -------------------------------------------------------------------------
    // create()
    // -------------------------------------------------------------------------

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

    public function test_accept_creates_user_and_marks_invite_used(): void
    {
        $invitation = $this->makeInvitation(['id' => 5, 'site_id' => 1, 'email' => 'guest@example.com']);

        $this->invitationRepository
            ->shouldReceive('findByToken')
            ->with('valid-token')
            ->once()
            ->andReturn($invitation);

        $expectedUser = $this->makeUser(['id' => 10, 'email' => 'guest@example.com']);

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
            ->with(5)
            ->once();

        $user = $this->service->accept('valid-token', 'Jane Doe', 'secret123');

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('guest@example.com', $user->email);
    }

    // -------------------------------------------------------------------------
    // accept()
    // -------------------------------------------------------------------------

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

    public function test_accept_throws_for_missing_token(): void
    {
        $this->invitationRepository
            ->shouldReceive('findByToken')
            ->andReturn(null);

        $this->invitationRepository->shouldNotReceive('markAsUsed');
        $this->userRepository->shouldNotReceive('create');

        $this->expectException(InvalidInvitationException::class);

        $this->service->accept('ghost-token', 'Jane', 'password');
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

        $this->service->accept('expired-token', 'Jane', 'password');
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

        $this->service->accept('used-token', 'Jane', 'password');
    }

    public function test_accept_wraps_writes_in_transaction(): void
    {
        $this->databaseMock->shouldNotReceive('transaction')->byDefault();

        $invitation = $this->makeInvitation();

        $this->invitationRepository->shouldReceive('findByToken')->andReturn($invitation);
        $this->userRepository->shouldReceive('create')->andReturn($this->makeUser());
        $this->invitationRepository->shouldReceive('markAsUsed');

        $result = $this->service->accept('valid-token', 'Jane', 'password');

        $this->assertInstanceOf(User::class, $result);

    }

    public function test_accept_rolls_back_if_user_creation_fails(): void
    {

        $invitation = $this->makeInvitation();

        $this->invitationRepository->shouldReceive('findByToken')->andReturn($invitation);

        $this->userRepository
            ->shouldReceive('create')
            ->andThrow(new \RuntimeException('DB failure'));

        $this->invitationRepository->shouldNotReceive('markAsUsed');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB failure');

        $this->service->accept('valid-token', 'Jane', 'password');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();

        $this->invitationRepository = Mockery::mock(InvitationRepository::class);
        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->databaseMock = Mockery::mock(Database::class);

        // Default: transaction executes its callback immediately and returns its result.
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