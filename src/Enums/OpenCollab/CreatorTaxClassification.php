<?php

namespace App\Enums\OpenCollab;

enum CreatorTaxClassification: string
{
    case UkVatRegistered = 'uk_vat_registered';
    case UkNonRegistered = 'uk_non_registered';
    case Us = 'us';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::UkVatRegistered => 'UK VAT registered',
            self::UkNonRegistered => 'UK non-registered',
            self::Us => 'US',
            self::Other => 'Other',
        };
    }
}