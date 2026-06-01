<?php

namespace App\Mail\Subscriptions;

class CustomerCareMail extends BaseSubscriptionCommunicationMail
{
    public string $subject = 'We\'re here to help';

    public function build(): static
    {
        return $this->view('emails.subscriptions.customer-care');
    }
}