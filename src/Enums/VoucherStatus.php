<?php

namespace App\Enums;

enum VoucherStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case EXPIRED = 'expired';

    public static function fromString(string $status): self
    {
        return match (strtolower($status)) {
            'active' => self::ACTIVE,
            'inactive' => self::INACTIVE,
            'expired' => self::EXPIRED,
            default => throw new \InvalidArgumentException("Invalid voucher status: {$status}")
        };
    }
}