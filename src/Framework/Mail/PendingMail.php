<?php

namespace App\Framework\Mail;

class PendingMail
{
    private array $to = [];
    private array $cc = [];
    private array $bcc = [];

    public function __construct(
        private MailManager $mailManager,
        string|array        $to
    )
    {
        $this->to = is_array($to) ? $to : [$to];
    }

    public function send(Mailable $mailable): bool
    {
        foreach ($this->to as $recipient) {
            $mailable->to($recipient);
        }

        foreach ($this->cc as $recipient) {
            $mailable->cc($recipient);
        }

        foreach ($this->bcc as $recipient) {
            $mailable->bcc($recipient);
        }

        return $this->mailManager->send($mailable);
    }

    public function cc(string|array $address): self
    {
        $this->cc = is_array($address) ? $address : [$address];
        return $this;
    }

    public function bcc(string|array $address): self
    {
        $this->bcc = is_array($address) ? $address : [$address];
        return $this;
    }
}