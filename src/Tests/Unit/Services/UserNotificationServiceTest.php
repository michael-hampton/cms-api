<?php

namespace App\Tests\Unit\Services;

use App\Models\User;
use App\Repositories\UserNotificationRepository;
use App\Services\UserNotificationService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class UserNotificationServiceTest extends FunctionalTestCase
{
    public function test_it_notifies_user(): void
    {
        $user = new User(['id' => 1]);

        $repo = Mockery::mock(UserNotificationRepository::class);
        $repo->shouldReceive('create')
            ->once()
            ->with(1, 'article_approved', ['article_id' => 10]);

        $service = new UserNotificationService($repo);

        $service->notify($user, 'article_approved', ['article_id' => 10]);
        self::assertTrue(true);
    }

    public function test_it_gets_unread_count(): void
    {
        $user = new User(['id' => 1]);

        $repo = Mockery::mock(UserNotificationRepository::class);
        $repo->shouldReceive('countUnread')
            ->once()
            ->with(1)
            ->andReturn(5);

        $service = new UserNotificationService($repo);

        $count = $service->getUnreadCount($user);

        $this->assertEquals(5, $count);
    }

    public function test_it_marks_notification_as_read(): void
    {
        $user = new User(['id' => 1]);

        $repo = Mockery::mock(UserNotificationRepository::class);
        $repo->shouldReceive('markAsRead')
            ->once()
            ->with(99, 1);

        $service = new UserNotificationService($repo);

        $service->markAsRead($user, 99);
        $this->assertTrue(true);
    }
}