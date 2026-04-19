<?php

namespace App\Framework\Notifications\Channels;

use App\Framework\Notifications\ChannelInterface;
use App\Framework\Notifications\NotificationInterface;
use App\Models\PushSubscription;

final class PushNotificationChannel implements ChannelInterface
{
    public function __construct(
        private readonly \App\Framework\WebPush\WebPushClient $client
    )
    {
    }

    public function supports(NotificationInterface $notification): bool
    {
        return $notification->recipientUserId() !== null;
    }

    public function send(NotificationInterface $notification): bool
    {
        $subscription = PushSubscription::where('member_id', $notification->recipientUserId())
            ->where('is_active', true)
            ->first();

        if (!$subscription) {
            return false;
        }

        return $this->client->send(
            $subscription->endpoint,
            $subscription->keys,
            [
                'title' => $notification->subject(),
                'body' => $notification->body ?? '',
            ]
        );
    }
}