<?php

namespace App\Services\Subscriptions\Refunds;

class RefundResult
{
    public function __construct(
        public readonly float  $amount,
        public readonly string $type,
        public readonly array  $meta = [],
        /**
         * True when the calculation determined no refund is owed.
         * The service layer MUST check this before executing any I/O.
         * Zero-refund is a valid, non-exceptional business outcome — not an error.
         */
        public readonly bool   $noRefundDue = false,
    )
    {
    }
}