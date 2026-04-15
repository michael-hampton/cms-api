<?php

namespace App\Mail\OpenCollab;

use App\Framework\Mail\Mailable;
use App\Models\User;

/**
 * Email sent when a contributor's previously complete onboarding becomes
 * invalid due to a contract update or guidelines version bump.
 */
class OnboardingInvalidatedMail extends Mailable
{
    /**
     * @param array<int, array{step: string, reason: string, meta: array<string, mixed>}> $pendingSteps
     */
    public function __construct(
        public readonly User  $contributor,
        public readonly array $pendingSteps,
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