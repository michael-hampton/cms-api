<?php

namespace App\DTO\OpenCollab;

class StripeEvent
{
    public function __construct(
        public string  $type,
        public ?string $paymentIntentId
    )
    {
    }
}