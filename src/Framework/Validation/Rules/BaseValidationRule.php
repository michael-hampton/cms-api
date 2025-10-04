<?php

namespace App\Framework\Validation\Rules;

use App\Framework\Validation\ValidationRuleInterface;

abstract class BaseValidationRule implements ValidationRuleInterface
{
    protected $parameters = [];
    protected $message;

    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }

    public function getMessage(): string
    {
        return $this->message ?? $this->getDefaultMessage();
    }

    abstract protected function getDefaultMessage(): string;
}