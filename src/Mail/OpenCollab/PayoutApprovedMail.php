<?php

namespace App\Mail\OpenCollab;

use App\Framework\Mail\Mailable;
use App\Models\Payout;
use App\Models\User;

class PayoutApprovedMail extends Mailable
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
            ->subject("Your payout of {$amount} has been approved")
            ->markdown('emails.open-collab.payout-approved', [
                'contributor' => $this->contributor,
                'payout' => $this->payout,
                'amount' => $amount,
                'method' => ucwords(str_replace('_', ' ', $this->payout->method ?? 'bank transfer')),
                'payoutsUrl' => rtrim(config('app.url'), '/') . '/contributor/payouts',
            ]);
    }
}