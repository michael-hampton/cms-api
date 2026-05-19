<?php

namespace App\DTO\Cart;

class TaxData
{
    public function __construct(
        public float   $rate,
        public float   $ratePercentage = 0,
        public ?string $jurisdiction = null,
        public bool    $includesShipping = false,
        public int   $taxCents = 0,
        public int   $taxableAmountCents = 0,
        public bool    $exempt = false
    )
    {

    }
}