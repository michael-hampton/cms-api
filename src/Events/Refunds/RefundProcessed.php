<?php

namespace App\Events\Refunds;

use App\Models\Refund;

class RefundProcessed
{
    public function __construct(
        public readonly Refund $refund,
        public readonly ?int   $processedBy
    )
    {
    }
}