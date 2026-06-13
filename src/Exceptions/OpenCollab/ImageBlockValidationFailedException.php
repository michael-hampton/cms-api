<?php

namespace App\Exceptions\OpenCollab;

class ImageBlockValidationFailedException extends \RuntimeException
{
    /** @param array<string, string[]> $errors */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('One or more image blocks failed validation.');
    }

    /** @return array<string, string[]> */
    public function getErrors(): array
    {
        return $this->errors;
    }
}