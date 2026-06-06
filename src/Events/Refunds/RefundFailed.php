<?php

namespace App\Events\Refunds;

use App\Models\Refund;

class RefundFailed
{
    public function __construct(
        public readonly Refund $refund,
        public readonly ?int $userId,
        public readonly string $reason,
    ) {}
}