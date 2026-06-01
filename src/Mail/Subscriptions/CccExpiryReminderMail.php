<?php

namespace App\Mail\Subscriptions;

class CccExpiryReminderMail extends BaseSubscriptionCommunicationMail
{
    public string $subject = 'Your card on file is expiring soon';

    public function build(): static
    {
        return $this->view('emails.subscriptions.ccc-expiry-reminder');
    }
}