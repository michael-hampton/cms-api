<?php

namespace App\Validation\Custom;

use App\Framework\Validation\Rules\BaseValidationRule;

class ImageSourceRule extends BaseValidationRule
{
    private $allowedExtensions = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tiff', 'ico'
    ];

    private $allowedMimeTypes = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
        'image/webp', 'image/svg+xml', 'image/bmp', 'image/tiff', 'image/x-icon'
    ];

    public function validate($value, array $data = []): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $value = trim($value);

        // Check file extension
        $extension = strtolower(pathinfo($value, PATHINFO_EXTENSION));
        $hasExtension = false;

        if (in_array($extension, $this->allowedExtensions)) {
           $hasExtension = true;
        }

        // Check if it's a valid URL format
        if (!$hasExtension && !$this->isValidUrlFormat($value)) {
            return false;
        }

        // Additional checks for data URLs (base64 images)
        if (strpos($value, 'data:image/') === 0) {
            return $this->validateDataUrl($value);
        }

        return true;
    }

    private function isValidUrlFormat(string $value): bool
    {
        // Handle data URLs
        if (strpos($value, 'data:image/') === 0) {
            return true;
        }

        // Handle relative paths
        if (strpos($value, '/') === 0 || strpos($value, './') === 0) {
            return true;
        }

        // Handle full URLs
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private function validateDataUrl(string $value): bool
    {
        // Basic validation for data URL format
        $pattern = '/^data:image\/(jpeg|jpg|png|gif|webp|svg\+xml|bmp);base64,([A-Za-z0-9+\/=]+)$/';
        return preg_match($pattern, $value) === 1;
    }

    protected function getDefaultMessage(): string
    {
        return 'The image source must be a valid image URL with one of the following extensions: ' .
            implode(', ', $this->allowedExtensions);
    }

    public function getAllowedExtensions(): array
    {
        return $this->allowedExtensions;
    }

    public function getAllowedMimeTypes(): array
    {
        return $this->allowedMimeTypes;
    }

    public function addAllowedExtension(string $extension): void
    {
        $extension = strtolower(trim($extension));
        if (!in_array($extension, $this->allowedExtensions)) {
            $this->allowedExtensions[] = $extension;
        }
    }
}