<?php

namespace App\Framework\Notifications;

use App\Framework\Mail\Mailable;

final class MailableNotification extends AbstractNotification implements EmailableNotification
{
    public function __construct(
        private readonly Mailable $mailable,
        private readonly string   $subject,
        ?int                      $userId = null,
        ?string                   $email = null,
    ) {
        parent::__construct($userId, $email);
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function toMailable(): Mailable
    {
        return $this->mailable;
    }
}
