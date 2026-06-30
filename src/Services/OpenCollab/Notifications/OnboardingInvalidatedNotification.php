<?php

namespace App\Services\OpenCollab\Notifications;

use App\Framework\Mail\Mailable;
use App\Framework\Notifications\EmailableNotification;
use App\Mail\OpenCollab\OnboardingInvalidatedMail;
use App\Models\User;

/**
 * Sent to a contributor when their previously complete onboarding becomes
 * invalid — e.g. a new contract was published or guidelines were updated.
 *
 * The notification carries the pending steps so the email template can
 * explain exactly what action is required and link directly to it.
 */
final class OnboardingInvalidatedNotification extends OpenCollabUserNotification
    implements EmailableNotification
{
    /**
     * @param array<int, array{step: string, reason: string, meta: array<string, mixed>}> $pendingSteps
     */
    public function __construct(
        public readonly User  $contributor,
        public readonly int   $siteId,
        public readonly array $pendingSteps,
    )
    {
        parent::__construct(userId: $contributor->id, email: $contributor->email);
    }

    public function subject(): string
    {
        $count = count($this->pendingSteps);
        $label = $count === 1 ? 'action is' : 'actions are';
        $reason = $this->pendingSteps[0]['reason'] ?? 'Your onboarding requires attention.';

        return "{$count} {$label} required: {$reason}";
    }

    public function toMailable(): Mailable
    {
        return new OnboardingInvalidatedMail($this->contributor, $this->pendingSteps);
    }
}