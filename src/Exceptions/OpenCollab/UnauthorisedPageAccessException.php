<?php

namespace App\Exceptions\OpenCollab;

use RuntimeException;

class UnauthorisedPageAccessException extends RuntimeException
{
    public function __construct(string $message = 'You do not have permission to modify this page.')
    {
        parent::__construct($message);
    }
}