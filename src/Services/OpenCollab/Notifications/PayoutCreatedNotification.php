<?php

namespace App\Services\OpenCollab\Notifications;

use App\Framework\Mail\Mailable;
use App\Framework\Notifications\AbstractNotification;
use App\Framework\Notifications\EmailableNotification;
use App\Mail\OpenCollab\PayoutCreatedMail;
use App\Models\Payout;
use App\Models\User;

final class PayoutCreatedNotification extends AbstractNotification
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
        return "Payout request received — {$amount}";
    }

    public function toMailable(): Mailable
    {
        return new PayoutCreatedMail($this->payout, $this->contributor);
    }
}