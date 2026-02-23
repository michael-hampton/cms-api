<?php

namespace App\Mail\Newsletters;

use App\Framework\Mail\Mailable;

class NewsletterWelcome extends Mailable
{
    public function __construct(
        public string  $email,
        public ?string $firstName = null,
        public ?array  $welcomeOffer = null
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        $name = $this->firstName ?? 'there';

        return $this
            ->subject('Welcome to Our Newsletter! 🎉')
            ->markdown('emails.newsletter.welcome')
            ->with([
                'email' => $this->email,
                'name' => $name,
                'welcomeOffer' => $this->welcomeOffer,
            ]);
    }
}