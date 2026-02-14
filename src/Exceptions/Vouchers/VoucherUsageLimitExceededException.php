<?php

namespace App\Exceptions\Vouchers;

class VoucherUsageLimitExceededException extends \RuntimeException
{
    public function __construct(string $code, int $limit)
    {
        parent::__construct("Voucher '{$code}' usage limit of {$limit} exceeded");
    }
}