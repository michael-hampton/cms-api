<?php

namespace App\Exceptions\Consents;

class ConsentWithdrawalInvalidStateException extends \RuntimeException
{
    public function __construct(string $currentState)
    {
        parent::__construct("Cannot process withdrawal request in state: {$currentState}");
    }
}