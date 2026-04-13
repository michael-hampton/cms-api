<?php

namespace App\Mail\OpenCollab;

use App\Framework\Mail\Mailable;
use App\Models\EarningsDispute;
use App\Models\User;

/**
 * Sent to the admin team when a contributor raises an earnings dispute.
 * Recipient address is resolved by AdminEmailChannel — not set here.
 */
class DisputeRaisedAdminMail extends Mailable
{
    public function __construct(
        private readonly EarningsDispute $dispute,
        private readonly User            $contributor,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        return $this
            ->subject("Earnings dispute raised by {$this->contributor->name}")
            ->markdown('emails.open-collab.dispute-raised-admin', [
                'dispute' => $this->dispute,
                'contributor' => $this->contributor,
                'reason' => $this->dispute->reason,
                'adminUrl' => rtrim(config('app.url'), '/') . '/admin/disputes',
            ]);
    }
}