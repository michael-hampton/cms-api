<?php

namespace App\Mail\OpenCollab;

use App\Framework\Mail\Mailable;
use App\Models\Payout;
use App\Models\User;

/**
 * Sent to the contributor when they request a payout (or the scheduler
 * creates one automatically).
 */
class PayoutCreatedMail extends Mailable
{
    public function __construct(
        private readonly Payout $payout,
        private readonly User   $contributor,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        $amount = '£' . number_format($this->payout->amount / 100, 2);

        return $this
            ->subject("Payout request received — {$amount}")
            ->markdown('emails.open-collab.payout-created', [
                'contributor' => $this->contributor,
                'payout' => $this->payout,
                'amount' => $amount,
                'method' => ucwords(str_replace('_', ' ', $this->payout->method ?? 'bank transfer')),
                'payoutsUrl' => rtrim(config('app.url'), '/') . '/contributor/payouts',
            ]);
    }
}