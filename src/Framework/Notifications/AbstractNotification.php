<?php

namespace App\Framework\Notifications;

/**
 * Convenience base class for notifications that target a single recipient.
 * Implementing NotificationInterface directly is equally valid.
 */
abstract class AbstractNotification implements NotificationInterface
{
    public function __construct(
        protected readonly ?int    $userId = null,
        protected readonly ?string $email = null,
    )
    {
    }

    public function recipientUserId(): ?int
    {
        return $this->userId;
    }

    public function recipientEmail(): ?string
    {
        return $this->email;
    }
}