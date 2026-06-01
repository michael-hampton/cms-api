<?php

namespace App\Services\Subscriptions\Communications;

use App\Framework\Mail\Mailable;
use App\Framework\Notifications\AbstractNotification;
use App\Framework\Notifications\EmailableNotification;

final class SubscriptionCommunicationNotification extends AbstractNotification implements EmailableNotification
{
    public function __construct(
        private readonly Mailable $mailable,
        private readonly string   $recipientEmail,
        private readonly int      $recipientUserId,
    ) {
        parent::__construct(userId: $recipientUserId, email: $recipientEmail);
    }

    public function subject(): string
    {
        return $this->mailable->subject;
    }

    public function recipientEmail(): ?string
    {
        return $this->recipientEmail;
    }

    public function recipientUserId(): ?int
    {
        return $this->recipientUserId;
    }

    public function toMailable(): Mailable
    {
        return $this->mailable;
    }
}