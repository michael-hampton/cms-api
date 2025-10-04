<?php

namespace App\Framework\Exceptions;

use App\Framework\Validation\ValidationResult;
use Exception;

class ValidationException extends Exception
{
    protected array $errors;

    public function __construct(string $message, array $errors = [])
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}