<?php

namespace App\Tests\Unit\Framework\Notifications;

use App\Enums\OpenCollab\PayoutStatus;
use App\Framework\Notifications\Channels\InAppNotificationChannel;
use App\Framework\Notifications\UserRecipientNotification;
use App\Models\Notification;
use App\Models\Payout;
use App\Models\UserNotification;
use App\Services\OpenCollab\Notifications\PayoutApprovedNotification;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class InAppNotificationChannelTest extends RepositoryTestCase
{
    use CreatesTestData;

    public function test_open_collab_notifications_are_stored_as_user_notifications(): void
    {
        $user = $this->createUser(['email' => 'contributor@example.com']);
        $payout = Payout::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'amount' => 6299,
            'currency' => 'GBP',
            'status' => PayoutStatus::Approved->value,
            'method' => 'bank_transfer',
        ]);

        $notification = new PayoutApprovedNotification($payout, $user);

        $this->assertInstanceOf(UserRecipientNotification::class, $notification);
        $this->assertTrue((new InAppNotificationChannel())->send($notification));

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'type' => 'payout_approved_notification',
        ]);

        $this->assertSame(0, Notification::where('member_id', $user->id)->count());
    }

    public function test_generic_notifications_are_still_stored_as_member_notifications(): void
    {
        $member = $this->createMember();

        $notification = new class($member->id) extends \App\Framework\Notifications\AbstractNotification {
            public function subject(): string
            {
                return 'Member alert';
            }
        };

        $this->assertTrue((new InAppNotificationChannel())->send($notification));

        $this->assertDatabaseHas('notifications', [
            'member_id' => $member->id,
            'title' => 'Member alert',
        ]);

        $this->assertSame(0, UserNotification::where('user_id', $member->id)->count());
    }
}
