<?php

namespace App\Tests\Unit\Services;

use App\Models\User;
use App\Repositories\UserNotificationRepository;
use App\Services\OpenCollab\UserConsentService;
use App\Services\UserNotificationService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class UserNotificationServiceTest extends FunctionalTestCase
{
    private UserConsentService $userConsentService;

    public function test_it_notifies_user(): void
    {
        $user = new User(['id' => 1]);

        $this->repo->shouldReceive('create')
            ->once()
            ->with(1, 'article_approved', ['article_id' => 10]);

        $this->userConsentService->shouldReceive('hasConsent')
            ->once()
            ->andReturn(true);

        $this->service->notify($user, 'article_approved', ['article_id' => 10]);
        $this->assertTrue(true);
    }

    public function test_it_gets_unread_count(): void
    {
        $user = new User(['id' => 1]);

        $this->repo->shouldReceive('countUnread')
            ->once()
            ->with(1)
            ->andReturn(5);

        $count = $this->service->getUnreadCount($user);

        $this->assertEquals(5, $count);
    }

    public function test_it_marks_notification_as_read(): void
    {
        $user = new User(['id' => 1]);

        $this->service->markAsRead($user, 99);
        $this->assertTrue(true);
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->repo = Mockery::mock(UserNotificationRepository::class);
        $this->userConsentService = Mockery::mock(UserConsentService::class);
        $this->repo->shouldReceive('markAsRead')
            ->once()
            ->with(99, 1);

        $this->service = new UserNotificationService(
            $this->repo,
            $this->userConsentService
        );
    }
}