<?php

namespace App\Mail\Boost;

use App\Framework\Mail\Mailable;
use App\Models\Boost;
use App\Models\BoostStat;
use App\Models\Merchant;

class BoostExpiredMail extends Mailable
{
    public function __construct(
        private readonly Boost      $boost,
        private readonly Merchant   $merchant,
        private readonly ?BoostStat $stat,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        return $this
            ->subject("Your boost has ended — #{$this->boost->id}")
            ->view('emails/boost/expired', [
                'boost' => $this->boost,
                'merchant' => $this->merchant,
                'stat' => $this->stat,
            ]);
    }
}