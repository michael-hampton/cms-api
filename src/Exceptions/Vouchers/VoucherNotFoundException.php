<?php

namespace App\Exceptions\Vouchers;

class VoucherNotFoundException extends \RuntimeException
{
    public function __construct(string $identifier)
    {
        parent::__construct("Voucher not found: {$identifier}");
    }
}