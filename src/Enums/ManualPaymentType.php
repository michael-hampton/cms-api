<?php

namespace App\Enums;

enum ManualPaymentType: string
{
    case BANK_TRANSFER      = 'bank_transfer';
    case CASH               = 'cash';
    case CHEQUE             = 'cheque';
    case EXTERNAL           = 'external';
    case MANUAL_ADJUSTMENT  = 'manual_adjustment';
    case OTHER              = 'other';

    public function label(): string
    {
        return match($this) {
            self::BANK_TRANSFER     => 'Bank Transfer',
            self::CASH              => 'Cash',
            self::CHEQUE            => 'Cheque',
            self::EXTERNAL          => 'External',
            self::MANUAL_ADJUSTMENT => 'Manual Adjustment',
            self::OTHER             => 'Other',
        };
    }
}