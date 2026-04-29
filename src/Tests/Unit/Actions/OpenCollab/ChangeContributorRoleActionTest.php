<?php

namespace App\Tests\Unit\Actions\OpenCollab;

use App\Actions\OpenCollab\ChangeContributorRoleAction;
use App\Enums\OpenCollab\AdminAction;
use App\Models\AdminActivityLog;
use App\Models\User;
use App\Repositories\OpenCollab\AdminActivityLogRepository;
use App\Repositories\OpenCollab\AdminContributorRepository;
use App\Services\Cms\UserService;
use Mockery;
use PHPUnit\Framework\TestCase;

class ChangeContributorRoleActionTest extends TestCase
{
    private AdminContributorRepository $repository;
    private UserService $userService;
    private AdminActivityLogRepository $logger;
    private ChangeContributorRoleAction $action;

    public function test_it_changes_role_and_logs_with_before_after_payload(): void
    {
        $contributor = mock(User::class)->makePartial();

        $contributor->shouldReceive('getAttribute')
            ->with('role')
            ->andReturn('editor');

        $this->repository
            ->shouldReceive('findContributorForSite')
            ->once()
            ->with(5, 1)
            ->andReturn($contributor);

        $this->userService
            ->shouldReceive('updateUser')
            ->once()
            ->with(5, ['role' => 'author']);

        $this->logger
            ->shouldReceive('log')
            ->once()
            ->withArgs(function (
                $adminId,
                $targetUserId,
                $action,
                $payload,
                $reason
            ) {
                return $adminId === 99
                    && $targetUserId === 5
                    && $action === AdminAction::CONTRIBUTOR_ROLE_CHANGED->value
                    && $payload === ['from' => 'editor', 'to' => 'author']
                    && $reason === 'Promotion to author';
            });

        $this->action->execute(
            userId: 5,
            siteId: 1,
            adminId: 99,
            newRole: 'author',
            reason: 'Promotion to author',
        );

        $this->assertTrue(true);
    }

    public function test_it_throws_when_reason_is_empty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Reason is required');

        $this->action->execute(userId: 5, siteId: 1, adminId: 99, newRole: 'author', reason: '');
    }

    public function test_it_throws_when_role_is_empty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Role is required.');

        $this->action->execute(userId: 5, siteId: 1, adminId: 99, newRole: '', reason: 'Valid reason');
    }

    public function test_it_throws_when_contributor_not_found(): void
    {
        $this->repository
            ->shouldReceive('findContributorForSite')
            ->andReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Contributor not found.');

        $this->action->execute(userId: 999, siteId: 1, adminId: 99, newRole: 'author', reason: 'Reason');
    }

    public function test_payload_captures_from_and_to_role(): void
    {
        $contributor = mock(User::class)->makePartial();
        $contributor->role = 'viewer';

        $this->repository
            ->shouldReceive('findContributorForSite')
            ->andReturn($contributor);

        $this->userService->shouldReceive('updateUser');

        $this->logger
            ->shouldReceive('log')
            ->once()
            ->withArgs(function ($adminId, $targetUserId, $action, $payload, $reason) {
                return $payload === ['from' => 'viewer', 'to' => 'editor'];
            })
            ->andReturn(Mockery::mock(AdminActivityLog::class));

        $this->action->execute(
            userId: 5,
            siteId: 1,
            adminId: 99,
            newRole: 'editor',
            reason: 'Upgrade role',
        );

        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = mock(AdminContributorRepository::class)->makePartial();
        $this->userService = mock(UserService::class)->makePartial();
        $this->logger = mock(AdminActivityLogRepository::class)->makePartial();

        $this->action = new ChangeContributorRoleAction(
            contributorRepository: $this->repository,
            userService: $this->userService,
            logger: $this->logger,
        );
    }
}