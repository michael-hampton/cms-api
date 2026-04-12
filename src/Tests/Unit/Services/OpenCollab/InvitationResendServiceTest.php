<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Framework\Support\Logger;
use App\Models\Invitation;
use App\Repositories\OpenCollab\InvitationRepository;
use App\Services\OpenCollab\InvitationResendService;
use App\Services\OpenCollab\InvitationService;
use App\Services\OpenCollab\InvitationStateMachine;
use App\Services\OpenCollab\InvitationStateMachineFactory;
use Mockery;
use PHPUnit\Framework\TestCase;

class InvitationResendServiceTest extends TestCase
{
    public function test_pending_invitation_is_resent(): void
    {
        $invitation = Mockery::mock(Invitation::class)->makePartial();
        $invitation->id = 123;

        $repo = Mockery::mock(InvitationRepository::class);
        $repo->shouldReceive('findLatestForEmail')
            ->once()
            ->andReturn($invitation);

        $repo->shouldReceive('expireAllForEmail')->never();

        $state = Mockery::mock(InvitationStateMachine::class);
        $state->shouldReceive('isUsed')->andReturn(false);
        $state->shouldReceive('isPending')->andReturn(true);

        $factory = Mockery::mock(InvitationStateMachineFactory::class);
        $factory->shouldReceive('make')
            ->once()
            ->andReturn($state);

        $invitationService = Mockery::mock(InvitationService::class);
        $invitationService->shouldReceive('send')
            ->once()
            ->with($invitation);

        $logger = Mockery::mock(Logger::class);

        $service = new InvitationResendService(
            $repo,
            $factory,
            $invitationService,
            $logger
        );

        $service->handle('test@test.com', 1);

        $this->assertTrue(true);
    }

    public function test_used_invitation_is_ignored(): void
    {
        $invitation = Mockery::mock(Invitation::class);

        $repo = Mockery::mock(InvitationRepository::class);
        $repo->shouldReceive('findLatestForEmail')->andReturn($invitation);

        $state = Mockery::mock(InvitationStateMachine::class);
        $state->shouldReceive('isUsed')->andReturn(true);

        $factory = Mockery::mock(InvitationStateMachineFactory::class);
        $factory->shouldReceive('make')->andReturn($state);

        $invitationService = Mockery::mock(InvitationService::class);
        $invitationService->shouldReceive('send')->never();

        $logger = Mockery::mock(Logger::class);

        $service = new InvitationResendService(
            $repo,
            $factory,
            $invitationService,
            $logger
        );

        $service->handle('test@test.com', 1);

        $this->assertTrue(true);
    }

    public function test_expired_invitation_creates_new_one(): void
    {
        $invitation = Mockery::mock(Invitation::class);

        $repo = Mockery::mock(InvitationRepository::class);
        $repo->shouldReceive('findLatestForEmail')->andReturn($invitation);

        $repo->shouldReceive('expireAllForEmail')
            ->once();

        $state = Mockery::mock(InvitationStateMachine::class);
        $state->shouldReceive('isUsed')->andReturn(false);
        $state->shouldReceive('isPending')->andReturn(false);
        $state->shouldReceive('shouldCreateNewInvite')->andReturn(true);

        $factory = Mockery::mock(InvitationStateMachineFactory::class);
        $factory->shouldReceive('make')->andReturn($state);

        $invitationService = Mockery::mock(InvitationService::class);
        $invitationService->shouldReceive('create')
            ->once();

        $logger = Mockery::mock(Logger::class);

        $service = new InvitationResendService(
            $repo,
            $factory,
            $invitationService,
            $logger
        );

        $service->handle('test@test.com', 1);

        $this->assertTrue(true);
    }

    public function test_invalid_email_returns_early(): void
    {
        $repo = Mockery::mock(InvitationRepository::class);
        $repo->shouldReceive('findLatestForEmail')->never();

        $factory = Mockery::mock(InvitationStateMachineFactory::class);

        $invitationService = Mockery::mock(InvitationService::class);

        $logger = Mockery::mock(Logger::class);

        $service = new InvitationResendService(
            $repo,
            $factory,
            $invitationService,
            $logger
        );

        $service->handle('not-an-email', 1);

        $this->assertTrue(true);
    }
}