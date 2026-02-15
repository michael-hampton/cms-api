<?php

namespace App\Framework\Database\Exceptions;

use Exception;

class UniqueConstraintViolationException extends Exception
{
    protected $message = 'Unique constraint violation';
}