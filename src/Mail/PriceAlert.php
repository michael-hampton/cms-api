<?php

namespace App\Mail;

use App\Framework\Mail\Mailable;
use App\Models\Member;
use App\Models\Product;

class PriceAlert extends Mailable
{
    public function __construct(
        public Product $product,
        public Member  $member,
        public float   $oldPrice,
        public float   $newPrice,
        public float   $targetPrice
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        $percentageOff = round((($this->oldPrice - $this->newPrice) / $this->oldPrice) * 100);

        return $this
            ->subject('Price Drop Alert: ' . $this->product->name)
            ->markdown('emails.alerts.price')
            ->with([
                'product' => $this->product,
                'member' => $this->member,
                'oldPrice' => $this->oldPrice,
                'newPrice' => $this->newPrice,
                'targetPrice' => $this->targetPrice,
                'percentageOff' => $percentageOff,
                'savings' => $this->oldPrice - $this->newPrice,
            ]);
    }
}