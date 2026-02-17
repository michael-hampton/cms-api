<?php

namespace App\Exceptions\Members;

use RuntimeException;

class AccountAlreadyActivatedException extends RuntimeException
{
    protected $message = 'This account is already active.';
}