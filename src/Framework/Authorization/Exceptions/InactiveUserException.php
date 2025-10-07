<?php

namespace App\Framework\Authorization\Exceptions;

use Exception;

class InactiveUserException extends Exception
{
    protected $message = 'User account is inactive';
}