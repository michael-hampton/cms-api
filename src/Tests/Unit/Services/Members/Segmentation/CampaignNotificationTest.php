<?php

namespace App\Tests\Unit\Services\Members\Segmentation;

use App\Framework\Mail\Mailable;
use App\Services\Members\Segmentation\CampaignNotification;
use Mockery;
use PHPUnit\Framework\TestCase;

class CampaignNotificationTest extends TestCase
{
    public function test_subject_delegates_to_mailable_subject(): void
    {
        $mailable = Mockery::mock(Mailable::class)->makePartial();
        $mailable->subject = 'We miss you';

        $notification = new CampaignNotification($mailable, 'test@example.com', 42);

        $this->assertSame('We miss you', $notification->subject());
    }

    public function test_recipient_email_returns_provided_email(): void
    {
        $mailable = Mockery::mock(Mailable::class)->makePartial();

        $notification = new CampaignNotification($mailable, 'test@example.com', 42);

        $this->assertSame('test@example.com', $notification->recipientEmail());
    }

    public function test_recipient_user_id_returns_provided_id(): void
    {
        $mailable = Mockery::mock(Mailable::class)->makePartial();

        $notification = new CampaignNotification($mailable, 'test@example.com', 42);

        $this->assertSame(42, $notification->recipientUserId());
    }

    public function test_to_mailable_returns_the_injected_mailable(): void
    {
        $mailable = Mockery::mock(Mailable::class)->makePartial();

        $notification = new CampaignNotification($mailable, 'test@example.com', 42);

        $this->assertSame($mailable, $notification->toMailable());
    }
}