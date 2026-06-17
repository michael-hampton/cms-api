<?php

namespace App\Tests\Unit\Actions\OpenCollab;

use App\Actions\OpenCollab\ReactivateContributorAction;
use App\Enums\OpenCollab\AdminAction;
use App\Models\User;
use App\Repositories\OpenCollab\AdminActivityLogRepository;
use App\Repositories\OpenCollab\AdminContributorRepository;
use App\Services\User\UserLifecycleServiceInterface;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;
use PHPUnit\Framework\TestCase;

class ReactivateContributorActionTest extends TestCase
{
    use CreatesTestData;

    private AdminContributorRepository $repository;
    private UserLifecycleServiceInterface $userLifecycle;
    private AdminActivityLogRepository $logger;
    private ReactivateContributorAction $action;
    private User $user;

    public function test_it_reactivates_contributor_and_logs_the_action(): void
    {
        $contributor = mock(User::class)->makePartial();

        $this->userLifecycle
            ->shouldReceive('reactivateContributor')
            ->once()
            ->with(5, 99, 'Reinstated after review');

        $this->repository
            ->shouldReceive('findContributorForSite')
            ->with(5, 1)
            ->andReturn($contributor);

        $this->logger
            ->shouldReceive('log')
            ->once()
            ->withArgs(function ($adminId, $targetUserId, $action, $payload, $reason) {
                return $adminId === 99
                    && $targetUserId === 5
                    && $action === AdminAction::CONTRIBUTOR_REACTIVATED->value
                    && $payload === []
                    && $reason === 'Reinstated after review';
            });

        $this->action->execute(
            userId: 5,
            siteId: 1,
            adminId: 99,
            reason: 'Reinstated after review',
        );

        $this->assertTrue(true);
    }

    public function test_it_throws_when_reason_is_empty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Reason is required to reactivate a contributor.');

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
            ->andReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Contributor not found.');

        $this->action->execute(userId: 999, siteId: 1, adminId: 99, reason: 'Valid reason');
    }

    public function test_it_does_not_log_when_contributor_not_found(): void
    {
        $this->repository
            ->shouldReceive('findContributorForSite')
            ->andReturn(null);

        $this->logger->shouldNotReceive('log');

        try {
            $this->action->execute(userId: 999, siteId: 1, adminId: 99, reason: 'Valid reason');
        } catch (\InvalidArgumentException) {
            // expected
        }

        $this->assertTrue(true);
    }

    public function test_it_handles_array_contributor_and_fetches_model(): void
    {

        $model = mock(User::class)->makePartial();
        $this->userLifecycle
            ->shouldReceive('reactivateContributor')
            ->once()
            ->with(5, 99, 'Valid reason');

        $this->repository
            ->shouldReceive('findContributorForSite')
            ->andReturn($model);

        $this->logger->shouldReceive('log')->once();

        $this->action->execute(
            userId: 5,
            siteId: 1,
            adminId: 99,
            reason: 'Valid reason',
        );

        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = mock(AdminContributorRepository::class)->makePartial();
        $this->userLifecycle = mock(UserLifecycleServiceInterface::class);
        $this->logger = mock(AdminActivityLogRepository::class)->makePartial();
        $this->user = Mockery::mock(User::class)->makePartial();
        $this->user->id = 1;

        $this->action = new ReactivateContributorAction(
            contributorRepository: $this->repository,
            userLifecycle: $this->userLifecycle,
            logger: $this->logger,
        );
    }
}
