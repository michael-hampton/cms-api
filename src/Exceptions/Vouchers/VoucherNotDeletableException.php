<?php

namespace App\Exceptions\Vouchers;

class VoucherNotDeletableException extends \RuntimeException
{
    public function __construct(int $usageCount)
    {
        parent::__construct("Cannot delete voucher with {$usageCount} redemptions");
    }
}