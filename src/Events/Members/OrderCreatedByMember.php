<?php

namespace App\Events\Members;

class OrderCreatedByMember
{
    public function __construct(
        public readonly int  $memberId,
        public readonly int  $siteId,
        public readonly ?int $orderId = null,
    )
    {
    }
}