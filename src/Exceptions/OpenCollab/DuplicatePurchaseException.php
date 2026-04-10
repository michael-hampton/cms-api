<?php

namespace App\Exceptions\OpenCollab;

use RuntimeException;

class DuplicatePurchaseException extends RuntimeException
{
    public function __construct(string $message = 'You already have access to this article.')
    {
        parent::__construct($message);
    }
}