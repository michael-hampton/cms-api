<?php

namespace App\Framework\Exceptions;

use Exception;

class CsrfTokenMismatchException extends Exception
{
    protected $code = 419;
    protected $message = 'CSRF token mismatch';
}