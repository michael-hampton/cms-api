<?php

namespace App\Mail\OpenCollab;

use App\Framework\Mail\Mailable;
use App\Models\Payout;
use App\Models\User;

class PayoutPaidMail extends Mailable
{
    public function __construct(
        private readonly Payout  $payout,
        private readonly User    $contributor,
        private readonly ?string $reference = null,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        $amount = '£' . number_format($this->payout->amount / 100, 2);

        return $this
            ->subject("Your payout of {$amount} has been sent")
            ->markdown('emails.open-collab.payout-paid', [
                'contributor' => $this->contributor,
                'payout' => $this->payout,
                'amount' => $amount,
                'reference' => $this->reference,
                'method' => ucwords(str_replace('_', ' ', $this->payout->method ?? 'bank transfer')),
                'processedDate' => $this->payout->processed_at instanceof \DateTimeInterface
                    ? $this->payout->processed_at->format('d M Y')
                    : date('d M Y'),
                'payoutsUrl' => rtrim(config('app.url'), '/') . '/contributor/payouts',
            ]);
    }
}