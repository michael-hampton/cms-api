<?php

namespace App\Tests\Unit\Repositories;

use App\Models\UserNotification;
use App\Repositories\UserNotificationRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class UserNotificationRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private UserNotificationRepository $repository;

    public function test_it_creates_notification(): void
    {
        $user = $this->createUser();

        $notification = $this->repository->create(
            $user->id,
            'article_approved',
            ['article_id' => 1]
        );

        $this->assertDatabaseHas('user_notifications', [
            'id' => $notification->id,
            'user_id' => $user->id,
            'type' => 'article_approved',
        ]);
    }

    public function test_it_returns_notifications_for_user(): void
    {
        $user = $this->createUser();

        $this->createUserNotification(['user_id' => $user->id]);
        $this->createUserNotification(['user_id' => $user->id]);
        $this->createUserNotification(['user_id' => $user->id]);

        $results = $this->repository->getForUser($user->id);

        $this->assertCount(3, $results);
    }

    public function test_it_counts_unread_notifications(): void
    {
        $user = $this->createUser();

        $this->createUserNotification(['user_id' => $user->id]);
        $this->createUserNotification(['user_id' => $user->id]);
        $this->createUserNotification(['user_id' => $user->id, 'read_at' => now()]);

        $count = $this->repository->countUnread($user->id);

        $this->assertEquals(2, $count);
    }

    public function test_it_marks_notification_as_read(): void
    {
        $user = $this->createUser();

        $notification = $this->createUserNotification(['user_id' => $user->id]);

        $this->repository->markAsRead($notification->id, $user->id);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_it_marks_all_as_read(): void
    {
        $user = $this->createUser();

        $this->createUserNotification(['user_id' => $user->id]);
        $this->createUserNotification(['user_id' => $user->id]);
        $this->createUserNotification(['user_id' => $user->id]);
        $this->createUserNotification(['user_id' => $user->id]);

        $this->repository->markAllAsRead($user->id);

        $this->assertEquals(
            0,
            UserNotification::where('user_id', $user->id)
                ->whereNull('read_at')
                ->count()
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new UserNotificationRepository();
    }
}