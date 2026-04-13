<?php

namespace App\Mail\OpenCollab;

use App\Framework\Mail\Mailable;
use App\Models\User;

/**
 * Sent to a contributor immediately after they accept their invitation
 * and their account is created.
 */
class WelcomeMail extends Mailable
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
            ->subject("Welcome — your account is ready")
            ->markdown('emails.open-collab.welcome', [
                'contributor' => $this->contributor,
                'dashboardUrl' => rtrim(config('app.url'), '/') . '/contributor/dashboard',
                'onboardingUrl' => rtrim(config('app.url'), '/') . '/onboarding',
            ]);
    }
}