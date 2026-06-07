<?php

namespace App\Enums\OpenCollab;

enum CreatorLiabilityStatus: string
{
    case Open = 'open';
    case PartiallyRecovered = 'partially_recovered';
    case Recovered = 'recovered';
    case WrittenOff = 'written_off';

    public function isOpen(): bool
    {
        return in_array($this, [
            self::Open,
            self::PartiallyRecovered,
        ], true);
    }

    public function isClosed(): bool
    {
        return in_array($this, [
            self::Recovered,
            self::WrittenOff,
        ], true);
    }
}