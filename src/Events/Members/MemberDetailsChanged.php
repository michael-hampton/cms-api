<?php

namespace App\Events\Members;

final class MemberDetailsChanged
{
    public function __construct(
        public readonly int    $memberId,
        public readonly string $stripeCustomerId = '',
        public ?int            $addressId = null
    )
    {
    }
}