<?php

namespace App\Framework\FileUpload;

use App\Framework\Support\Logger;
use Exception;

class FileUpload
{
    private $file;
    protected $uploadPath;
    private $allowedExtensions;
    private $maxSize;

    public function __construct(array $file, string $uploadPath = 'uploads')
    {
        $this->file = $file;
        $this->uploadPath = rtrim($uploadPath, '/');
        $this->allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
        $this->maxSize = 5 * 1024 * 1024; // 5MB
    }

    public function setAllowedExtensions(array $extensions): self
    {
        $this->allowedExtensions = $extensions;
        return $this;
    }

    public function setMaxSize(int $bytes): self
    {
        $this->maxSize = $bytes;
        return $this;
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = $this->getUploadErrorMessage($this->file['error']);
        }

        if ($this->file['size'] > $this->maxSize) {
            $errors[] = "File size exceeds maximum allowed size of " . $this->formatBytes($this->maxSize);
        }

        $extension = strtolower(pathinfo($this->file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            $errors[] = "File type not allowed. Allowed types: " . implode(', ', $this->allowedExtensions);
        }

        return $errors;
    }

    public function store(string $directory = '', string $filename = null): ?string
    {
        $errors = $this->validate();
        if (!empty($errors)) {
            throw new Exception("Upload validation failed: " . implode(', ', $errors));
        }

        $uploadDir = $this->uploadPath . '/' . trim($directory, '/');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (!$filename) {
            $extension = pathinfo($this->file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $extension;
        }

        $filepath = $uploadDir . '/' . $filename;

        if (move_uploaded_file($this->file['tmp_name'], $filepath)) {
            Logger::info('File uploaded successfully', ['file' => $filepath]);
            return $filepath;
        }

        throw new Exception("Failed to upload file");
    }

    public function storeAs(string $directory, string $filename): ?string
    {
        return $this->store($directory, $filename);
    }

    private function getUploadErrorMessage(int $error): string
    {
        switch ($error) {
            case UPLOAD_ERR_INI_SIZE:
                return 'File exceeds upload_max_filesize directive';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File exceeds MAX_FILE_SIZE directive';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'File upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}