<?php

namespace App\Services\OpenCollab\Notifications;

use App\Framework\Mail\Mailable;
use App\Framework\Notifications\ConsentAwareNotification;
use App\Framework\Notifications\EmailableNotification;
use App\Models\ContributorViolation;
use App\Models\User;


final class ViolationRecordedNotification extends OpenCollabUserNotification
    implements EmailableNotification, ConsentAwareNotification
{
    public function __construct(
        public readonly ContributorViolation $violation,
        public readonly User                 $user,
    )
    {
        parent::__construct(userId: $user->id, email: $user->email);
    }

    public function subject(): string
    {
        return "A policy violation has been recorded on your account";
    }

    public function toMailable(): Mailable
    {
        return new ViolationRecordedMail($this->violation, $this->user);
    }

    public function consentType(): string
    {
        return 'contributor.violation_recorded';
    }
}