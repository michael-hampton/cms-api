<?php

namespace App\Tests\Unit\Listeners\Cms;

use App\Events\Cms\ContentSubmittedForApproval;
use App\Listeners\Cms\SendContentWorkflowNotification;
use App\Repositories\OpenCollab\RbacRepository;
use App\Repositories\UserNotificationRepository;
use App\Services\OpenCollab\SitePermissionResolver;
use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;

class SendContentWorkflowNotificationTest extends TestCase
{
    private MockInterface $notifications;
    private MockInterface $rbacRepository;
    private $permissionResolver;

    public function test_submitted_page_notifies_active_reviewers_with_expected_payload(): void
    {
        $this->rbacRepository
            ->shouldReceive('usersForSite')
            ->with(3)
            ->andReturn([
                ['id' => 10, 'is_active' => true],
                ['id' => 11, 'is_active' => false],
                ['id' => 12, 'is_active' => true],
            ]);

        $this->permissionResolver
            ->shouldReceive('allows')
            ->with(10, 3, 'pages.review')
            ->andReturn(true);

        $this->permissionResolver
            ->shouldReceive('allows')
            ->with(12, 3, 'pages.review')
            ->andReturn(false);
        $this->permissionResolver
            ->shouldReceive('allows')
            ->with(12, 3, 'content.review')
            ->andReturn(false);

        $this->notifications
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (int $userId, string $type, array $payload): bool {
                return $userId === 10
                    && $type === 'page_submitted_for_approval'
                    && $payload['notification_type'] === 'page_submitted_for_approval'
                    && $payload['page_id'] === 44
                    && $payload['page_title'] === 'Contributor article'
                    && $payload['action_user_id'] === 7
                    && $payload['url'] === '/admin/pages/44/edit';
            });

        $this->listener()->handle(new ContentSubmittedForApproval(
            contentType: 'pages',
            contentId: 44,
            siteId: 3,
            actorId: 7,
            title: 'Contributor article',
            ownerId: 7,
        ));

        $this->addToAssertionCount(1);
    }

    protected function setUp(): void
    {
        $this->notifications = Mockery::mock(UserNotificationRepository::class);
        $this->rbacRepository = Mockery::mock(RbacRepository::class);
        $this->permissionResolver = Mockery::mock(SitePermissionResolver::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function listener(): SendContentWorkflowNotification
    {
        return new SendContentWorkflowNotification(
            $this->notifications,
            $this->rbacRepository,
            $this->permissionResolver,
        );
    }
}
