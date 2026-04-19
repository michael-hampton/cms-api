<?php

namespace App\Framework\Notifications\Channels;

use App\Framework\Database\Database;
use App\Framework\Notifications\ChannelInterface;
use App\Framework\Notifications\NotificationInterface;

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

        return Database::table('notifications')->insert([
            'member_id' => $userId,
            'title' => $notification->subject(),
            'body' => $notification->body ?? null, // if your DTO has it
            'is_read' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}