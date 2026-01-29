<?php

namespace App\Mail;

use App\Framework\Mail\Mailable;
use App\Models\Member;
use App\Models\ProductOffer;

class OfferEndingSoon extends Mailable
{
    public function __construct(
        public Member       $member,
        public ProductOffer $offer,
        public int          $hoursRemaining
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        return $this
            ->subject("⏰ Last Chance! Offer Ends in {$this->hoursRemaining} Hours")
            ->markdown('emails.offers.ending-soon')
            ->with([
                'member' => $this->member,
                'offer' => $this->offer,
                'product' => $this->offer->product,
                'hoursRemaining' => $this->hoursRemaining,
                'endDate' => $this->offer->end_date,
            ]);
    }
}