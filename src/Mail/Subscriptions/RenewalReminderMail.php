<?php

namespace App\Mail\Subscriptions;

class RenewalReminderMail extends BaseSubscriptionCommunicationMail
{
    public string $subject = 'Your subscription is up for renewal';

    public function build(): static
    {
        return $this->view('emails.subscriptions.renewal-reminder');
    }
}