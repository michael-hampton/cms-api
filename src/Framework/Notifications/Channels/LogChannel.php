<?php

namespace App\Framework\Notifications\Channels;

use App\Framework\Notifications\ChannelInterface;
use App\Framework\Notifications\NotificationInterface;
use App\Framework\Support\Logger;

/**
 * Logs every notification regardless of type.
 * Useful in development, staging, and as a permanent audit trail.
 */
final class LogChannel implements ChannelInterface
{
    public function __construct(
        private readonly Logger $logger,
    )
    {
    }

    public function supports(NotificationInterface $notification): bool
    {
        return true;
    }

    public function send(NotificationInterface $notification): bool
    {
        $this->logger->info('[Notification] dispatched.', [
            'type' => get_class($notification),
            'subject' => $notification->subject(),
            'recipient_id' => $notification->recipientUserId(),
            'email' => $notification->recipientEmail(),
        ]);

        return true;
    }
}