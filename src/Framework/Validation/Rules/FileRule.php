<?php

namespace App\Framework\Validation\Rules;

use App\Framework\Http\UploadedFile;
use App\Framework\Validation\ValidationRuleInterface;

class FileRule extends BaseValidationRule implements ValidationRuleInterface
{
    public function setParameters(array $parameters): void {}
    public function validate($value, array $data = []): bool
    {
        if ($value instanceof UploadedFile) {
            return $value->isValid();
        }

        if (!is_array($value)) {
            return false;
        }

        return isset($value['tmp_name'], $value['error'])
            && $value['error'] === UPLOAD_ERR_OK
            && is_file($value['tmp_name']);
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    protected function getDefaultMessage(): string
    {
        return 'The :field must be a valid uploaded file.';
    }
}