<?php

namespace App\Framework\Notifications;

interface NotificationInterface
{
    public function subject(): string;

    public function recipientUserId(): ?int;

    public function recipientEmail(): ?string;
}