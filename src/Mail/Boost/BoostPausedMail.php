<?php

namespace App\Mail\Boost;

use App\Framework\Mail\Mailable;
use App\Models\Boost;
use App\Models\Merchant;

class BoostPausedMail extends Mailable
{
    public function __construct(
        private readonly Boost    $boost,
        private readonly Merchant $merchant,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        return $this
            ->subject("Your boost has been paused — #{$this->boost->id}")
            ->view('emails/boost/paused', [
                'boost' => $this->boost,
                'merchant' => $this->merchant,
            ]);
    }
}