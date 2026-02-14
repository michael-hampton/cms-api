<?php

namespace App\Exceptions\Consents;

class RequiredConsentCannotBeRevokedException extends \RuntimeException
{
    public function __construct(string $code)
    {
        parent::__construct("Cannot revoke required consent: {$code}");
    }
}