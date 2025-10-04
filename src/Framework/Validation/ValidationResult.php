<?php

namespace App\Framework\Validation;

class ValidationResult
{
    private $isValid;
    private $errors;

    public function __construct(bool $isValid, array $errors = [])
    {
        $this->isValid = $isValid;
        $this->errors = $errors;
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function setErrors(array $errors): void
    {
        $this->errors = $errors;
    }

    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    public function getError(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field] = $message;
        $this->isValid = false;
    }

    public function merge(ValidationResult $other): self
    {
        return new self(
            $this->isValid && $other->isValid(),
            array_merge($this->errors, $other->getErrors())
        );
    }
}