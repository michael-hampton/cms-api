<?php

namespace App\Mail\OpenCollab;

use App\Framework\Mail\Mailable;
use App\Models\User;

class OnboardingCompletedMail extends Mailable
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
            ->subject("You're all set — start publishing!")
            ->markdown('emails.open-collab.onboarding-completed', [
                'contributor' => $this->contributor,
                'dashboardUrl' => rtrim(config('app.url'), '/') . '/contributor/dashboard',
                'createUrl' => rtrim(config('app.url'), '/') . '/articles/create',
            ]);
    }
}