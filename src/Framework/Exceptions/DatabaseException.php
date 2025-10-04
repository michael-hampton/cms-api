<?php

namespace App\Framework\Exceptions;

use Exception;

class DatabaseException extends Exception
{
    public function __construct(string $message, Exception $previous = null)
    {
        parent::__construct("Database error: {$message}", 0, $previous);
    }
}