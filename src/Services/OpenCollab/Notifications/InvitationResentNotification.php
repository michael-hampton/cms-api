<?php

namespace App\Services\OpenCollab\Notifications;

use App\Framework\Mail\Mailable;
use App\Framework\Notifications\AbstractNotification;
use App\Framework\Notifications\EmailableNotification;
use App\Mail\OpenCollab\InvitationCreatedMail;
use App\Models\Invitation;

final class InvitationResentNotification extends AbstractNotification
    implements EmailableNotification
{
    public function __construct(public readonly Invitation $invitation)
    {
        parent::__construct(userId: null, email: $invitation->email);
    }

    public function subject(): string
    {
        return "Your contributor invitation (new link)";
    }

    public function toMailable(): Mailable
    {
        // Resend uses the same mailable as create — the template handles both.
        return new InvitationCreatedMail($this->invitation);
    }
}