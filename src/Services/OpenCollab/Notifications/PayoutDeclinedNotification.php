<?php

namespace App\Services\OpenCollab\Notifications;

use App\Framework\Mail\Mailable;
use App\Framework\Notifications\AbstractNotification;
use App\Framework\Notifications\ConsentAwareNotification;
use App\Framework\Notifications\EmailableNotification;
use App\Mail\OpenCollab\PayoutDeclinedMail;
use App\Models\Payout;
use App\Models\User;

final class PayoutDeclinedNotification extends AbstractNotification
    implements EmailableNotification, ConsentAwareNotification
{
    public function __construct(
        public readonly Payout  $payout,
        public readonly User    $contributor,
        public readonly ?string $reason = null,
    )
    {
        parent::__construct(userId: $contributor->id, email: $contributor->email);
    }

    public function subject(): string
    {
        return "Your payout request could not be processed";
    }

    public function toMailable(): Mailable
    {
        return new PayoutDeclinedMail($this->payout, $this->contributor, $this->reason);
    }

    public function consentType(): string
    {
        return 'contributor.payout_failed';
    }
}