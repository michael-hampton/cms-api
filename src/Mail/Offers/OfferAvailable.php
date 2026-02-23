<?php

namespace App\Mail\Offers;

use App\Framework\Mail\Mailable;
use App\Models\Member;
use App\Models\ProductOffer;

class OfferAvailable extends Mailable
{
    public function __construct(
        public Member       $member,
        public ProductOffer $offer
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        return $this
            ->subject('🔥 Special Offer Just for You!')
            ->markdown('emails.offers.available')
            ->with([
                'member' => $this->member,
                'offer' => $this->offer,
                'product' => $this->offer->product,
                'startDate' => $this->offer->start_date,
                'endDate' => $this->offer->end_date,
            ]);
    }
}