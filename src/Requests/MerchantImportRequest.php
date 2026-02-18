<?php

namespace App\Requests;

use App\Enums\ImportType;
use App\Framework\Exceptions\ValidationException;

class MerchantImportRequest
{
    public function __construct(
        private readonly array $data,
        private readonly array $files
    )
    {
    }

    public function validated(): array
    {
        $errors = [];

        $file = $this->files['file'] ?? null;
        $type = trim($this->data['type'] ?? '');
        $importType = null;

        if (!$file || ($file->getError() ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors['file'] = 'A file is required.';
        } else {
            $extension = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
            if (!in_array($extension, ['csv', 'txt'], true)) {
                $errors['file'] = 'Only CSV or TXT files are allowed.';
            }
        }

        if ($type === '') {
            $errors['type'] = 'Import type is required.';
        } else {
            $importType = ImportType::tryFrom($type);
            if ($importType === null) {
                $errors['type'] = "Invalid import type '{$type}'. Allowed: voucher, offer, product.";
            }
        }

        if (!empty($errors)) {
            throw new ValidationException('Import Failed', $errors);
        }

        return [
            'file' => $file,
            'type' => $importType,
            'update_existing' => filter_var($this->data['update_existing'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }
}