<?php

namespace App\Tests\Unit\Services;

use App\Models\User;
use App\Repositories\UserNotificationRepository;
use App\Services\OpenCollab\UserConsentService;
use App\Services\UserNotificationService;
use App\Tests\Unit\UnitTestCase;
use Mockery;
use Mockery\MockInterface;

class UserNotificationServiceTest extends UnitTestCase
{
    private UserNotificationRepository&MockInterface $repo;
    private UserConsentService&MockInterface $userConsentService;
    private UserNotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = Mockery::mock(UserNotificationRepository::class);
        $this->userConsentService = Mockery::mock(UserConsentService::class);
        $this->service = new UserNotificationService(
            $this->repo,
            $this->userConsentService
        );
    }

    public function test_it_notifies_user(): void
    {
        $user = new User(['id' => 1]);

        $this->userConsentService->shouldReceive('hasConsent')
            ->once()
            ->andReturn(true);

        $this->repo->shouldReceive('create')
            ->once()
            ->with(1, 'article_approved', ['article_id' => 10]);

        $this->service->notify($user, 'article_approved', ['article_id' => 10]);
    }

    public function test_it_gets_unread_count(): void
    {
        $this->repo->shouldReceive('countUnread')
            ->once()
            ->with(1)
            ->andReturn(5);

        $count = $this->service->getUnreadCount(1);

        $this->assertEquals(5, $count);
    }

    public function test_it_marks_notification_as_read(): void
    {
        $user = new User(['id' => 1]);

        $this->repo->shouldReceive('markAsRead')
            ->once()
            ->with(99, 1);

        $this->service->markAsRead($user, 99);
    }
}
