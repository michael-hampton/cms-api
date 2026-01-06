<?php

namespace App\Mail;

use App\Framework\Mail\Mailable;
use App\Models\Member;
use App\Models\SupportTicket;

class SupportNotification extends Mailable
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
            ->subject("New Support Ticket #{$this->ticket->id} - {$this->ticket->reason}")
            ->view('emails.support.notification')
            ->with([
                'ticket' => $this->ticket,
                'member' => $this->member
            ]);
    }
}