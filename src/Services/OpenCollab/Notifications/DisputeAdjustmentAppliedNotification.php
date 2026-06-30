<?php

namespace App\Services\OpenCollab\Notifications;

use App\Framework\Mail\Mailable;
use App\Framework\Notifications\ConsentAwareNotification;
use App\Framework\Notifications\EmailableNotification;
use App\Mail\OpenCollab\DisputeAdjustmentAppliedMail;
use App\Models\EarningsDispute;
use App\Models\User;

final class DisputeAdjustmentAppliedNotification extends OpenCollabUserNotification
    implements EmailableNotification, ConsentAwareNotification
{
    public function __construct(
        public readonly EarningsDispute $dispute,
        public readonly User            $contributor,
        public readonly int             $adjustmentAmountPence,
        public readonly string          $currency,
    )
    {
        parent::__construct(userId: $contributor->id, email: $contributor->email);
    }

    public function subject(): string
    {
        $sign = $this->adjustmentAmountPence >= 0 ? '+' : '−';
        $amount = '£' . number_format(abs($this->adjustmentAmountPence) / 100, 2);
        return "Earnings adjustment applied: {$sign}{$amount}";
    }

    public function toMailable(): Mailable
    {
        return new DisputeAdjustmentAppliedMail(
            $this->dispute,
            $this->contributor,
            $this->adjustmentAmountPence,
            $this->currency,
        );
    }

    public function consentType(): string
    {
        return 'contributor.dispute_resolved';
    }
}