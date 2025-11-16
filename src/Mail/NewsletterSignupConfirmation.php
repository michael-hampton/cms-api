<?php

namespace App\Mail;

use App\Framework\Mail\Mailable;

class NewsletterSignupConfirmation extends Mailable
{
    public function __construct(
        public string  $email,
        public string  $confirmationToken,
        public ?string $firstName = null,
        public array   $preferences = []
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        $confirmationUrl = config('app.url') . '/newsletter/confirm?token=' . $this->confirmationToken . '&email=' . urlencode($this->email);
        $name = $this->firstName ?? 'there';

        return $this
            ->subject('Confirm Your Newsletter Subscription')
            ->markdown('emails.newsletter.confirmation')
            ->with([
                'email' => $this->email,
                'name' => $name,
                'confirmationUrl' => $confirmationUrl,
                'confirmationToken' => $this->confirmationToken,
                'preferences' => $this->preferences,
            ]);
    }
}