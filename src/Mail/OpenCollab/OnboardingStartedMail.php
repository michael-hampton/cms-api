<?php

namespace App\Mail\OpenCollab;

use App\Framework\Mail\Mailable;
use App\Models\User;

class OnboardingStartedMail extends Mailable
{
    public function __construct(
        private readonly User $contributor,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        return $this
            ->subject("Let's finish setting up your contributor account")
            ->markdown('emails.open-collab.onboarding-started', [
                'contributor' => $this->contributor,
                'onboardingUrl' => rtrim(config('app.url'), '/') . '/onboarding',
            ]);
    }
}