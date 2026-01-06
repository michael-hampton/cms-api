<?php

namespace App\Mail;

use App\Framework\Mail\Mailable;
use App\Models\Member;
use App\Models\SupportTicket;

class SupportConfirmation extends Mailable
{
    public function __construct(
        public SupportTicket $ticket,
        public Member        $member
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        return $this
            ->subject("We've received your support request #{$this->ticket->id}")
            ->view('emails.support.confirmation')
            ->with([
                'ticket' => $this->ticket,
                'member' => $this->member,
                'memberName' => $this->member->first_name . ' ' . $this->member->last_name
            ]);
    }
}