<?php

namespace App\Exceptions\OpenCollab;

use App\Enums\OpenCollab\AccrualStatus;

class InvalidAccrualTransitionException extends \DomainException
{
    public function __construct(
        public readonly int           $ledgerEntryId,
        public readonly AccrualStatus $from,
        public readonly AccrualStatus $to,
    ) {
        parent::__construct(
            "Cannot transition ledger entry [{$ledgerEntryId}] from [{$from->value}] to [{$to->value}]. " .
            "Allowed transitions from [{$from->value}]: " .
            implode(', ', array_map(fn(AccrualStatus $s) => $s->value, $from->allowedTransitions()) ?: ['none']) . '.'
        );
    }
}