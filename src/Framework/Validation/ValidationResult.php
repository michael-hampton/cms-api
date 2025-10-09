<?php

namespace App\Framework\Validation;

class ValidationResult
{
    private bool $isValid;
    private array $errors;
    private array $failedFields;


    public function __construct(bool $isValid, array $errors = [], array $failedFields = [])
    {
        $this->isValid = $isValid;
        $this->errors = $errors;
        $this->failedFields = $failedFields;

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

    public function getFailedFields(): array
    {
        return $this->failedFields;
    }
}