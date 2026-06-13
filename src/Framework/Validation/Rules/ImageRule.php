<?php

namespace App\Framework\Validation\Rules;

use App\Framework\Http\UploadedFile;
use App\Framework\Validation\ValidationRuleInterface;

class ImageRule implements ValidationRuleInterface
{
    public function setParameters(array $parameters): void {}
    private string $message = 'The :field must be a valid image.';

    private array $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'image/bmp',
    ];

    public function validate($value, array $data = []): bool
    {
        if ($value instanceof UploadedFile) {
            if (!$value->isValid()) {
                return false;
            }

            return in_array($value->getMimeType(), $this->allowedMimeTypes, true);
        }

        if (!is_array($value)) {
            return false;
        }

        if (($value['error'] ?? null) !== UPLOAD_ERR_OK) {
            return false;
        }

        $tmpName = $value['tmp_name'] ?? null;

        if (!$tmpName || !is_file($tmpName)) {
            return false;
        }

        $mimeType = mime_content_type($tmpName);

        return in_array($mimeType, $this->allowedMimeTypes, true);
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}