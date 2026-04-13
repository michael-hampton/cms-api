<?php

namespace App\Services\OpenCollab\Notifications;

use App\Framework\Mail\Mailable;
use App\Framework\Notifications\AbstractNotification;
use App\Framework\Notifications\EmailableNotification;
use App\Mail\OpenCollab\OnboardingCompletedMail;
use App\Models\User;

final class OnboardingCompletedNotification extends AbstractNotification
    implements EmailableNotification
{
    public function __construct(
        public readonly User $contributor,
        public readonly int  $siteId,
    )
    {
        parent::__construct(userId: $contributor->id, email: $contributor->email);
    }

    public function subject(): string
    {
        return "You're all set — start publishing!";
    }

    public function toMailable(): Mailable
    {
        return new OnboardingCompletedMail($this->contributor);
    }
}