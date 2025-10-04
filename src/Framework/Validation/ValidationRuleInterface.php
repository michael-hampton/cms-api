<?php

namespace App\Framework\Validation;

interface ValidationRuleInterface
{
    public function validate($value, array $data = []): bool;
    public function getMessage(): string;
    public function setParameters(array $parameters): void;
}