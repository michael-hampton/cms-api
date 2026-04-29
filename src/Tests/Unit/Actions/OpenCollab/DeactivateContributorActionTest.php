<?php

namespace App\Tests\Unit\Actions\OpenCollab;

use App\Actions\OpenCollab\DeactivateContributorAction;
use App\Enums\OpenCollab\AdminAction;
use App\Models\User;
use App\Repositories\OpenCollab\AdminActivityLogRepository;
use App\Repositories\OpenCollab\AdminContributorRepository;
use PHPUnit\Framework\TestCase;

class DeactivateContributorActionTest extends TestCase
{
    private $repository;
    private $logger;
    private DeactivateContributorAction $action;

    public function test_it_deactivates_contributor_and_logs_the_action(): void
    {
        $contributor = mock(User::class);
        $contributor->shouldReceive('update')
            ->once()
            ->with(['is_active' => false]);

        $this->repository
            ->shouldReceive('findContributorForSite')
            ->once()
            ->with(5, 1)
            ->andReturn($contributor);

        $this->logger
            ->shouldReceive('log')
            ->once()
            ->withArgs(function ($adminId, $targetUserId, $action, $payload, $reason) {
                return $adminId === 99
                    && $targetUserId === 5
                    && $action === AdminAction::CONTRIBUTOR_DEACTIVATED->value
                    && $payload === []
                    && $reason === 'Violated content guidelines';
            });

        $this->action->execute(
            userId: 5,
            siteId: 1,
            adminId: 99,
            reason: 'Violated content guidelines',
        );

        $this->assertTrue(true);
    }

    public function test_it_throws_when_reason_is_empty(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->action->execute(userId: 5, siteId: 1, adminId: 99, reason: '');
    }

    public function test_it_throws_when_reason_is_whitespace_only(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->action->execute(userId: 5, siteId: 1, adminId: 99, reason: '   ');
    }

    public function test_it_throws_when_contributor_not_found(): void
    {
        $this->repository
            ->shouldReceive('findContributorForSite')
            ->once()
            ->andReturn(null);

        $this->expectException(\InvalidArgumentException::class);

        $this->action->execute(userId: 999, siteId: 1, adminId: 99, reason: 'Valid reason');
    }

    public function test_it_does_not_log_when_contributor_not_found(): void
    {
        $this->repository
            ->shouldReceive('findContributorForSite')
            ->once()
            ->andReturn(null);

        $this->logger->shouldNotReceive('log');

        try {
            $this->action->execute(userId: 999, siteId: 1, adminId: 99, reason: 'Valid reason');
        } catch (\InvalidArgumentException) {
        }

        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = mock(AdminContributorRepository::class);
        $this->logger = mock(AdminActivityLogRepository::class);

        $this->action = new DeactivateContributorAction(
            contributorRepository: $this->repository,
            logger: $this->logger,
        );
    }
}