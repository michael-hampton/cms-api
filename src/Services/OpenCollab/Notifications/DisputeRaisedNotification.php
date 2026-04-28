<?php

namespace App\Services\OpenCollab\Notifications;

use App\Framework\Mail\Mailable;
use App\Framework\Notifications\AbstractNotification;
use App\Framework\Notifications\AdminNotification;
use App\Framework\Notifications\ConsentAwareNotification;
use App\Framework\Notifications\EmailableNotification;
use App\Mail\OpenCollab\DisputeRaisedAdminMail;
use App\Models\EarningsDispute;
use App\Models\User;

final class DisputeRaisedNotification extends AbstractNotification
    implements EmailableNotification, AdminNotification, ConsentAwareNotification
{
    public function __construct(
        public readonly EarningsDispute $dispute,
        public readonly User            $contributor,
    )
    {
        parent::__construct(userId: null, email: null);
    }

    public function subject(): string
    {
        return "Earnings dispute raised by {$this->contributor->name}";
    }

    public function toMailable(): Mailable
    {
        return new DisputeRaisedAdminMail($this->dispute, $this->contributor);
    }

    public function consentType(): string
    {
        return 'contributor.dispute_raised';
    }
}