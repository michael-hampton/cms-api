<?php

namespace App\Mail;

use App\Framework\Mail\Mailable;
use App\Models\Member;

class ResetPassword extends Mailable
{
    public function __construct(
        public Member $member,
        public string $resetToken,
        public int    $expiresInMinutes = 60
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        $resetUrl = config('app.url') . '/reset-password?token=' . $this->resetToken . '&email=' . urlencode($this->member->email);

        return $this
            ->subject('Reset Your Password')
            ->markdown('emails.auth.reset-password')
            ->with([
                'member' => $this->member,
                'resetUrl' => $resetUrl,
                'resetToken' => $this->resetToken,
                'expiresInMinutes' => $this->expiresInMinutes,
            ]);
    }
}