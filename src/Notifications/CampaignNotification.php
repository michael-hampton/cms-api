<?php

namespace App\Notifications;

use App\Framework\Notifications\NotificationInterface;

final class CampaignNotification implements NotificationInterface
{
    public function __construct(
        private int    $userId,
        private string $subject,
        public ?string $body = null,
    )
    {
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function recipientUserId(): ?int
    {
        return $this->userId;
    }

    public function recipientEmail(): ?string
    {
        return null;
    }

    public function body(): ?string
    {
        return $this->body;
    }
}