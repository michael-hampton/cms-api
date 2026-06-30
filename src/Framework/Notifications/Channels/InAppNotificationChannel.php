<?php

namespace App\Framework\Notifications\Channels;

use App\Framework\Database\Database;
use App\Framework\Notifications\ChannelInterface;
use App\Framework\Notifications\NotificationInterface;
use App\Framework\Notifications\UserRecipientNotification;

final class InAppNotificationChannel implements ChannelInterface
{
    public function supports(NotificationInterface $notification): bool
    {
        return true; // or add logic later if needed
    }

    public function send(NotificationInterface $notification): bool
    {
        $userId = $notification->recipientUserId();

        if (!$userId) {
            return false;
        }

        if ($notification instanceof UserRecipientNotification) {
            return Database::table('user_notifications')->insert([
                'user_id' => $userId,
                'type' => $this->notificationType($notification),
                'data' => json_encode([
                    'title' => $notification->subject(),
                    'message' => $this->notificationBody($notification),
                    'url' => method_exists($notification, 'url') ? $notification->url() : null,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return Database::table('notifications')->insert([
            'member_id' => $userId,
            'title' => $notification->subject(),
            'body' => $this->notificationBody($notification),
            'is_read' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function notificationBody(NotificationInterface $notification): ?string
    {
        if (method_exists($notification, 'body')) {
            return $notification->body();
        }

        return property_exists($notification, 'body') ? $notification->body : null;
    }

    private function notificationType(NotificationInterface $notification): string
    {
        if (method_exists($notification, 'tag') && $notification->tag()) {
            return $notification->tag();
        }

        return strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', class_basename(get_class($notification))));
    }
}
