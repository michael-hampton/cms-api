<?php

namespace App\Mail\Subscriptions;

class AcknowledgementMail extends BaseSubscriptionCommunicationMail
{
    public string $subject = 'Thanks for your subscription';

    public function build(): static
    {
        return $this->view('emails.subscriptions.acknowledgement');
    }
}