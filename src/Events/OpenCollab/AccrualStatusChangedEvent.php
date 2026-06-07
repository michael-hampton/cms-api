<?php

namespace App\Events\OpenCollab;

use App\Enums\OpenCollab\AccrualStatus;
use App\Models\EarningsLedger;

class AccrualStatusChangedEvent
{
    public function __construct(
        public readonly EarningsLedger $ledgerEntry,
        public readonly AccrualStatus $from,
        public readonly AccrualStatus $to,
    ) {
    }
}