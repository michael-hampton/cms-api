<?php

namespace App\Events\Notifications;

final class EmailNotificationSent
{
    public function __construct(
        public readonly int     $recipientUserId,
        public readonly string  $recipientEmail,
        public readonly string  $subject,
        public readonly ?string $notificationClass = null,
        public readonly ?string $mailableClass = null,
    ) {
    }
}
