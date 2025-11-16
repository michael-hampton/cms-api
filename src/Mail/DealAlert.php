<?php

namespace App\Mail;

use App\Framework\Mail\Mailable;
use App\Models\Member;

class DealAlert extends Mailable
{
    public function __construct(
        public Member $member,
        public array  $deals
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        return $this
            ->subject('🔥 Hot Deals Alert - Don\'t Miss Out!')
            ->markdown('emails.alerts.deal')
            ->with([
                'member' => $this->member,
                'deals' => $this->deals,
                'dealCount' => count($this->deals),
            ]);
    }
}