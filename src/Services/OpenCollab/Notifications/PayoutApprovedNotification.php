<?php

namespace App\Services\OpenCollab\Notifications;

use App\Framework\Mail\Mailable;
use App\Framework\Notifications\AbstractNotification;
use App\Framework\Notifications\EmailableNotification;
use App\Mail\OpenCollab\PayoutApprovedMail;
use App\Models\Payout;
use App\Models\User;

final class PayoutApprovedNotification extends AbstractNotification
    implements EmailableNotification
{
    public function __construct(
        public readonly Payout $payout,
        public readonly User   $contributor,
    )
    {
        parent::__construct(userId: $contributor->id, email: $contributor->email);
    }

    public function subject(): string
    {
        $amount = '£' . number_format($this->payout->amount / 100, 2);
        return "Your payout of {$amount} has been approved";
    }

    public function toMailable(): Mailable
    {
        return new PayoutApprovedMail($this->payout, $this->contributor);
    }
}