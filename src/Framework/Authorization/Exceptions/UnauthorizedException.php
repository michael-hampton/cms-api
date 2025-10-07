<?php

namespace App\Framework\Authorization\Exceptions;

use Exception;

class UnauthorizedException extends Exception
{
    protected $message = 'Unauthorized';
}