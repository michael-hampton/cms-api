<?php

namespace App\Services\OpenCollab\Notifications;

use App\Framework\Mail\Mailable;
use App\Framework\Notifications\EmailableNotification;
use App\Mail\OpenCollab\WelcomeMail;
use App\Models\Invitation;
use App\Models\User;

final class InvitationAcceptedNotification extends OpenCollabUserNotification
    implements EmailableNotification
{
    public function __construct(
        public readonly User       $contributor,
        public readonly Invitation $invitation,
    )
    {
        parent::__construct(userId: $contributor->id, email: $contributor->email);
    }

    public function subject(): string
    {
        return "Welcome — your account is ready";
    }

    public function toMailable(): Mailable
    {
        return new WelcomeMail($this->contributor);
    }
}