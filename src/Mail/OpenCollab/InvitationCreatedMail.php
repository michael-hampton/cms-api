<?php

namespace App\Mail\OpenCollab;

use App\Framework\Mail\Mailable;
use App\Models\Invitation;

/**
 * Sent when an admin creates a new invitation, and when an existing
 * invitation is resent. The view differentiates via $isResend.
 */
class InvitationCreatedMail extends Mailable
{
    public function __construct(
        private readonly Invitation $invitation,
        private readonly bool       $isResend = false,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        $subject = $this->isResend
            ? 'Your contributor invitation (new link)'
            : "You've been invited to contribute";

        return $this
            ->subject($subject)
            ->markdown('emails.open-collab.invitation-created', [
                'invitation' => $this->invitation,
                'isResend' => $this->isResend,
                'acceptUrl' => $this->buildAcceptUrl(),
                'expiresAt' => $this->invitation->expires_at,
            ]);
    }

    private function buildAcceptUrl(): string
    {
        return rtrim(config('app.url'), '/') . '/invite/' . $this->invitation->token;
    }
}