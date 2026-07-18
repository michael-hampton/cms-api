<?php

namespace App\Mail\Subscriptions;

class FirstIssueNoticeMail extends BaseSubscriptionCommunicationMail
{
    public string $subject = 'Your first issue is on its way';

    public function build(): static
    {
        return $this->view('emails.subscriptions.first-issue-notice');
    }
}
