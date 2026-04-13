<?php

namespace App\Mail\OpenCollab;

use App\Framework\Mail\Mailable;
use App\Models\EarningsDispute;
use App\Models\User;

/**
 * Sent to the contributor when a ledger adjustment is applied as part
 * of a dispute resolution.
 */
class DisputeAdjustmentAppliedMail extends Mailable
{
    public function __construct(
        private readonly EarningsDispute $dispute,
        private readonly User            $contributor,
        private readonly int             $adjustmentAmountPence,
        private readonly string          $currency,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        $isCredit = $this->adjustmentAmountPence >= 0;
        $sign = $isCredit ? '+' : '−';
        $amount = '£' . number_format(abs($this->adjustmentAmountPence) / 100, 2);

        return $this
            ->subject("Earnings adjustment applied: {$sign}{$amount}")
            ->markdown('emails.open-collab.dispute-adjustment', [
                'contributor' => $this->contributor,
                'dispute' => $this->dispute,
                'isCredit' => $isCredit,
                'sign' => $sign,
                'amount' => $amount,
                'amountPence' => $this->adjustmentAmountPence,
                'currency' => strtoupper($this->currency),
                'earningsUrl' => rtrim(config('app.url'), '/') . '/contributor/earnings',
            ]);
    }
}