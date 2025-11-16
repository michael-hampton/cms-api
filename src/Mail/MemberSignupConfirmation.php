<?php

namespace App\Mail;

use App\Framework\Mail\Mailable;
use App\Models\Member;

class MemberSignupConfirmation extends Mailable
{
    public function __construct(
        public Member $member,
        public string $verificationToken
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        $verificationUrl = config('app.url') . '/verify-email?token=' . $this->verificationToken;

        return $this
            ->subject('Welcome! Please Verify Your Email')
            ->markdown('emails.auth.signup-confirmation')
            ->with([
                'member' => $this->member,
                'verificationUrl' => $verificationUrl,
                'verificationToken' => $this->verificationToken,
            ]);
    }
}