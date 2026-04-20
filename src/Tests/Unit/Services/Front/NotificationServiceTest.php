<?php

namespace App\Tests\Unit\Services\Front;

use App\Framework\Support\Collection;
use App\Models\Member;
use App\Repositories\Members\GiftedArticleRepository;
use App\Repositories\Members\NotificationRepository;
use App\Repositories\Rewards\RewardsRepository;
use App\Services\Members\NotificationService;
use Mockery;
use PHPUnit\Framework\TestCase;

class NotificationServiceTest extends TestCase
{
    private NotificationService $service;
    private RewardsRepository $rewardsRepo;
    private GiftedArticleRepository $giftsRepo;
    private Member $member;
    private NotificationRepository $notificationRepository;

    public function testGetNotificationsWithUnclaimedRewards(): void
    {
        $this->member->shouldReceive('isEmailVerified')
            ->andReturn(true);

        $rewards = new Collection([
            (object)['id' => 1, 'status' => 'pending'],
            (object)['id' => 2, 'status' => 'pending']
        ]);

        $this->rewardsRepo->shouldReceive('getMemberRewards')
            ->with(1, 1, 'pending')
            ->andReturn($rewards);

        $this->giftsRepo->shouldReceive('getPendingGiftsForMember')
            ->andReturn(new Collection());

        $notifications = $this->service->getNotifications($this->member, 1);

        $this->assertCount(1, $notifications);
        $this->assertEquals('reward', $notifications[0]['type']);
        $this->assertEquals(2, $notifications[0]['count']);
        $this->assertEquals('high', $notifications[0]['priority']);
    }

    public function testGetNotificationsWithPendingGifts(): void
    {
        $this->member->shouldReceive('isEmailVerified')
            ->andReturn(true);

        $gifts = new Collection([
            (object)['id' => 1, 'status' => 'pending']
        ]);

        $this->rewardsRepo->shouldReceive('getMemberRewards')
            ->andReturn(new Collection());

        $this->giftsRepo->shouldReceive('getPendingGiftsForMember')
            ->with(1, 'test@example.com')
            ->andReturn($gifts);

        $notifications = $this->service->getNotifications($this->member, 1);

        $this->assertCount(1, $notifications);
        $this->assertEquals('gift', $notifications[0]['type']);
        $this->assertEquals(1, $notifications[0]['count']);
    }

    public function testGetNotificationsWithNewBadges(): void
    {
        $_SESSION['new_badges_earned'] = 3;

        $this->rewardsRepo->shouldReceive('getMemberRewards')
            ->andReturn(new Collection());
        $this->giftsRepo->shouldReceive('getPendingGiftsForMember')
            ->andReturn(new Collection());

        $notifications = $this->service->getNotifications($this->member, 1);

        $badgeNotification = array_filter($notifications, fn($n) => $n['type'] === 'badge');
        $this->assertCount(1, $badgeNotification);
        $this->assertEquals(3, array_values($badgeNotification)[0]['count']);
    }

    public function testGetNotificationsWithUnverifiedEmail(): void
    {
        $this->member->shouldReceive('isEmailVerified')->andReturn(false);

        $this->rewardsRepo->shouldReceive('getMemberRewards')
            ->andReturn(new Collection());
        $this->giftsRepo->shouldReceive('getPendingGiftsForMember')
            ->andReturn(new Collection());

        $notifications = $this->service->getNotifications($this->member, 1);
        $verificationNotification = array_filter($notifications, fn($n) => $n['type'] === 'verification');

        $this->assertCount(1, $verificationNotification);
    }

    public function testGetNotificationCount(): void
    {
        $this->member->shouldReceive('isEmailVerified')
            ->andReturn(true);

        $rewards = new Collection([
            (object)['id' => 1], (object)['id' => 2]
        ]);
        $gifts = new Collection([
            (object)['id' => 1]
        ]);

        $this->rewardsRepo->shouldReceive('getMemberRewards')
            ->andReturn($rewards);
        $this->giftsRepo->shouldReceive('getPendingGiftsForMember')
            ->andReturn($gifts);

        $count = $this->service->getNotificationCount($this->member, 1);

        $this->assertEquals(3, $count); // 2 rewards + 1 gift
    }

    public function testMarkAsSeen(): void
    {
        $this->service->markAsSeen('reward');

        $this->assertArrayHasKey('notification_seen_reward', $_SESSION);
        $this->assertIsInt($_SESSION['notification_seen_reward']);
    }

    public function testWasRecentlySeen(): void
    {
        $_SESSION['notification_seen_gift'] = time();

        $this->assertTrue($this->service->wasRecentlySeen('gift'));
        $this->assertFalse($this->service->wasRecentlySeen('badge'));
    }

    public function testWasRecentlySeenExpired(): void
    {
        $_SESSION['notification_seen_reward'] = time() - 7200; // 2 hours ago

        $this->assertFalse($this->service->wasRecentlySeen('reward', 3600)); // 1 hour threshold
        $this->assertTrue($this->service->wasRecentlySeen('reward', 10000)); // Large threshold
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->rewardsRepo = Mockery::mock(RewardsRepository::class);
        $this->giftsRepo = Mockery::mock(GiftedArticleRepository::class);
        $this->notificationRepository = Mockery::mock(NotificationRepository::class);

        $this->service = new NotificationService($this->rewardsRepo, $this->giftsRepo, $this->notificationRepository);

        $this->member = Mockery::mock(Member::class)->makePartial();
        $this->member->id = 1;
        $this->member->email = 'test@example.com';
        //$this->member->shouldReceive('isEmailVerified')->andReturn(true);

        $this->notificationRepository->shouldReceive('findUnreadForMember')
            ->andReturn(collect())
            ->byDefault();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        unset($_SESSION['new_badges_earned']);
        parent::tearDown();
    }
}