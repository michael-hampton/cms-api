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
            ->with('test@test.com', 1)
            ->once()
            ->andReturn($invitation);
        $repo->shouldReceive('expireAllForEmail')->never();

        $state = Mockery::mock(InvitationStateMachine::class);
        $state->shouldReceive('isUsed')->once()->andReturn(false);
        $state->shouldReceive('status')->once()->andReturn(\App\Enums\OpenCollab\InvitationStatus::Pending);
        $state->shouldReceive('isPending')->once()->andReturn(true);

        $factory = Mockery::mock(InvitationStateMachineFactory::class);
        $factory->shouldReceive('make')->with($invitation)->once()->andReturn($state);

        $invitationService = Mockery::mock(InvitationService::class);
        $invitationService->shouldReceive('send')->once()->with($invitation);

        $logger = $this->mockLogger();
        $logger->shouldReceive('info')->once();

        $service = new InvitationResendService($repo, $factory, $invitationService, $logger);
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

        $service = new InvitationResendService(
            $repo,
            $factory,
            $invitationService,
            $this->mockLogger(),
        );

        $service->handle('test@test.com', 1);
        $this->assertTrue(true);
    }

    public function test_expired_invitation_creates_new_one(): void
    {
        $invitation = Mockery::mock(Invitation::class);

        $repo = Mockery::mock(InvitationRepository::class);
        $repo->shouldReceive('findLatestForEmail')
            ->with('expired@test.com', 1)
            ->once()
            ->andReturn($invitation);
        $repo->shouldReceive('expireAllForEmail')
            ->with('expired@test.com', 1)
            ->once();

        $state = Mockery::mock(InvitationStateMachine::class);
        $state->shouldReceive('isUsed')->once()->andReturn(false);
        $state->shouldReceive('status')->twice()->andReturn(\App\Enums\OpenCollab\InvitationStatus::Expired);
        $state->shouldReceive('isPending')->once()->andReturn(false);

        $factory = Mockery::mock(InvitationStateMachineFactory::class);
        $factory->shouldReceive('make')->with($invitation)->once()->andReturn($state);

        $invitationService = Mockery::mock(InvitationService::class);
        $invitationService->shouldReceive('create')->with('expired@test.com', 0, 1)->once();

        $logger = $this->mockLogger();
        $logger->shouldReceive('info')->once();

        $service = new InvitationResendService($repo, $factory, $invitationService, $logger);
        $service->handle('expired@test.com', 1);

        $this->assertTrue(true);
    }

    public function test_invalid_email_returns_early(): void
    {
        $repo = Mockery::mock(InvitationRepository::class);
        $repo->shouldReceive('findLatestForEmail')->never();

        $service = new InvitationResendService(
            $repo,
            Mockery::mock(InvitationStateMachineFactory::class),
            Mockery::mock(InvitationService::class),
            $this->mockLogger(),
        );

        $service->handle('not-an-email', 1);
        $this->assertTrue(true);
    }

    public function test_handle_drops_request_when_rate_limit_exceeded(): void
    {
        $email = 'limited@test.com';
        $siteId = 1;
        $key = 'oc:resend:' . hash('sha256', $email) . ':' . $siteId;

        \App\Framework\Support\Cache\Cache::put($key, 3, 3600);

        $repo = Mockery::mock(InvitationRepository::class);
        $repo->shouldReceive('findLatestForEmail')->never();

        $invitationService = Mockery::mock(InvitationService::class);
        $invitationService->shouldReceive('send')->never();
        $invitationService->shouldReceive('create')->never();

        $logger = $this->mockLogger();
        $logger->shouldReceive('warning')
            ->once()
            ->with(
                'Invitation resend throttled.',
                [
                    'email' => $email,
                    'site_id' => $siteId,
                ],
            );

        $service = new InvitationResendService(
            $repo,
            Mockery::mock(InvitationStateMachineFactory::class),
            $invitationService,
            $logger,
        );

        $service->handle($email, $siteId);

        \App\Framework\Support\Cache\Cache::forget($key);
        $this->assertTrue(true);
    }

    public function test_handle_increments_throttle_counter_on_each_call(): void
    {
        $email = 'counter@test.com';
        $siteId = 1;
        $key = 'oc:resend:' . hash('sha256', $email) . ':' . $siteId;

        \App\Framework\Support\Cache\Cache::forget($key);

        $repo = Mockery::mock(InvitationRepository::class);
        $repo->shouldReceive('findLatestForEmail')
            ->twice()
            ->with($email, $siteId)
            ->andReturn(null);

        $service = new InvitationResendService(
            $repo,
            Mockery::mock(InvitationStateMachineFactory::class),
            Mockery::mock(InvitationService::class),
            $this->mockLogger(),
        );

        $service->handle($email, $siteId);
        $service->handle($email, $siteId);

        $this->assertEquals(2, \App\Framework\Support\Cache\Cache::get($key));
        \App\Framework\Support\Cache\Cache::forget($key);
    }

    public function test_handle_normalises_email_before_throttle_check(): void
    {
        $rawEmail = '  USER@TEST.COM  ';
        $normalisedEmail = 'user@test.com';
        $siteId = 1;
        $key = 'oc:resend:' . hash('sha256', $normalisedEmail) . ':' . $siteId;

        \App\Framework\Support\Cache\Cache::forget($key);

        $repo = Mockery::mock(InvitationRepository::class);
        $repo->shouldReceive('findLatestForEmail')
            ->once()
            ->with($normalisedEmail, $siteId)
            ->andReturn(null);

        $service = new InvitationResendService(
            $repo,
            Mockery::mock(InvitationStateMachineFactory::class),
            Mockery::mock(InvitationService::class),
            $this->mockLogger(),
        );

        $service->handle($rawEmail, $siteId);

        $this->assertSame(1, \App\Framework\Support\Cache\Cache::get($key));
        \App\Framework\Support\Cache\Cache::forget($key);
    }

    private function mockLogger(): Logger
    {
        $logger = Mockery::mock(Logger::class);
        $logger->shouldReceive('info')->byDefault();
        $logger->shouldReceive('warning')->byDefault();
        $logger->shouldReceive('error')->byDefault();

        return $logger;
    }
}
