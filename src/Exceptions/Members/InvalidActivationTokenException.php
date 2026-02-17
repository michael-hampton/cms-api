<?php

namespace App\Exceptions\Members;

use RuntimeException;

class InvalidActivationTokenException extends RuntimeException
{
    protected $message = 'The activation link is invalid or has expired.';
}