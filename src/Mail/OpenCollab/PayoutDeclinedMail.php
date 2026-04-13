<?php

namespace App\Mail\OpenCollab;

use App\Framework\Mail\Mailable;
use App\Models\Payout;
use App\Models\User;

class PayoutDeclinedMail extends Mailable
{
    public function __construct(
        private readonly Payout  $payout,
        private readonly User    $contributor,
        private readonly ?string $reason = null,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        $amount = '£' . number_format($this->payout->amount / 100, 2);

        return $this
            ->subject("Your payout request could not be processed")
            ->markdown('emails.open-collab.payout-declined', [
                'contributor' => $this->contributor,
                'payout' => $this->payout,
                'amount' => $amount,
                'reason' => $this->reason,
                'payoutsUrl' => rtrim(config('app.url'), '/') . '/contributor/payouts',
                'settingsUrl' => rtrim(config('app.url'), '/') . '/contributor/settings',
            ]);
    }
}