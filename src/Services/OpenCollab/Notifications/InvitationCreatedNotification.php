<?php

namespace App\Services\OpenCollab\Notifications;

use App\Framework\Mail\Mailable;
use App\Framework\Notifications\AbstractNotification;
use App\Framework\Notifications\EmailableNotification;
use App\Mail\OpenCollab\InvitationCreatedMail;
use App\Models\Invitation;

final class InvitationCreatedNotification extends AbstractNotification
    implements EmailableNotification
{
    public function __construct(public readonly Invitation $invitation)
    {
        parent::__construct(userId: null, email: $invitation->email);
    }

    public function subject(): string
    {
        return "You've been invited to contribute";
    }

    public function toMailable(): Mailable
    {
        return new InvitationCreatedMail($this->invitation);
    }
}