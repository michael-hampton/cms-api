<?php

namespace App\Mail;

use App\Framework\Mail\Mailable;

class ForgotPassword extends Mailable
{
    public function __construct(
        public string $email,
        public string $resetToken,
        public int    $expiresInMinutes = 60
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        $resetUrl = config('app.url') . '/reset-password?token=' . $this->resetToken . '&email=' . urlencode($this->email);

        return $this
            ->subject('Password Reset Request')
            ->markdown('emails.auth.forgot-password')
            ->with([
                'email' => $this->email,
                'resetUrl' => $resetUrl,
                'resetToken' => $this->resetToken,
                'expiresInMinutes' => $this->expiresInMinutes,
            ]);
    }
}