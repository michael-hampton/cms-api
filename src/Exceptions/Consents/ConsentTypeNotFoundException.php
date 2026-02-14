<?php

namespace App\Exceptions\Consents;

class ConsentTypeNotFoundException extends \RuntimeException
{
    public function __construct(string $code)
    {
        parent::__construct("Consent type not found: {$code}");
    }
}