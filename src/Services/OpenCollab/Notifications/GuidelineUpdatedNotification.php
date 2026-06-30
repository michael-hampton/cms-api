<?php

namespace App\Services\OpenCollab\Notifications;

use App\Framework\Mail\Mailable;
use App\Framework\Notifications\ConsentAwareNotification;
use App\Framework\Notifications\EmailableNotification;
use App\Models\Contract;
use App\Models\ContributorViolation;
use App\Models\Guideline;
use App\Models\User;

final class GuidelineUpdatedNotification extends OpenCollabUserNotification
    implements EmailableNotification, ConsentAwareNotification
{
    public function __construct(
        public readonly Guideline $guideline,
        public readonly User      $user,
    )
    {
        parent::__construct(userId: $user->id, email: $user->email);
    }

    public function subject(): string
    {
        return "Guidelines have been updated — please review";
    }

    public function toMailable(): Mailable
    {
        return new GuidelineUpdatedMail($this->guideline, $this->user);
    }

    public function consentType(): string
    {
        return 'contributor.guideline_updated';
    }
}