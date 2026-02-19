<?php

namespace App\Mail\Boost;

use App\Framework\Mail\Mailable;
use App\Models\Boost;
use App\Models\Merchant;

class BoostLimitBreachedMail extends Mailable
{
    public function __construct(
        private readonly Boost     $boost,
        private readonly Merchant  $merchant,
        private readonly string    $limitType,
        private readonly float|int $limitValue,
        private readonly float|int $currentValue,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        return $this
            ->subject("Your boost has been paused — limit reached — #{$this->boost->id}")
            ->view('emails/boost/limit-breached', [
                'boost' => $this->boost,
                'merchant' => $this->merchant,
                'limitType' => $this->limitType,
                'limitValue' => $this->limitValue,
                'currentValue' => $this->currentValue,
            ]);
    }
}