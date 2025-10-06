<?php

namespace App\Services;

use App\Framework\Http\UploadedFile;
use Exception;

class ImageUploadService
{
    private string $uploadPath;
    private array $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private int $maxFileSize = 5242880; // 5MB

    public function __construct(string $uploadPath = 'uploads/authors')
    {
        $this->uploadPath = rtrim($uploadPath, '/');
    }

    public function upload(UploadedFile $file, ?string $oldImagePath = null): string
    {
        if (!$file->isValid()) {
            throw new Exception($file->getErrorMessage());
        }

        if (!in_array($file->getMimeType(), $this->allowedMimeTypes)) {
            throw new Exception('Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.');
        }

        if ($file->getSize() > $this->maxFileSize) {
            throw new Exception('File size exceeds maximum allowed size of 5MB.');
        }

        // Create upload directory if it doesn't exist
        $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $this->uploadPath;
        if ($_ENV['APP_ENV'] !== 'testing' && !is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
        }

        // Generate unique filename
        $extension = $file->getClientOriginalExtension();
        $filename = uniqid('author_') . '_' . time() . '.' . $extension;
        $destination = $fullPath . '/' . $filename;

        if ($_ENV['APP_ENV'] === 'testing') {
            return '/' . $this->uploadPath . '/' . $filename;
        }

        // Move uploaded file
        if (!$file->moveTo($destination)) {
            throw new Exception('Failed to upload file.');
        }

        // Delete old image if exists
        if ($oldImagePath && file_exists($_SERVER['DOCUMENT_ROOT'] . $oldImagePath)) {
            unlink($_SERVER['DOCUMENT_ROOT'] . $oldImagePath);
        }

        return '/' . $this->uploadPath . '/' . $filename;
    }

    /**
     * Upload file to a specific path (NEW METHOD)
     */
    public function uploadToPath(UploadedFile $file, string $relativePath, ?string $oldImagePath = null): string
    {
        if (!$file->isValid()) {
            throw new Exception($file->getErrorMessage());
        }

        $baseUploadPath = rtrim(config('upload.path', 'uploads'), '/');
        $fullPath = $baseUploadPath . '/' . $relativePath;

        $this->ensureDirectoryExists($fullPath);

        $extension = $file->getClientOriginalExtension();
        $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $baseName = preg_replace('/[^a-zA-Z0-9\-_]/', '', $baseName);
        $baseName = substr($baseName, 0, 50);

        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        $filename = $baseName . '_' . $timestamp . '_' . $random . '.' . $extension;

        $destination = $fullPath . '/' . $filename;
        $relativeFilePath = $relativePath . '/' . $filename;

        if ($_ENV['APP_ENV'] === 'testing') {
            return $relativeFilePath;
        }

        if (!$file->moveTo($destination)) {
            throw new Exception('Failed to upload file.');
        }

        if ($oldImagePath) {
            $this->delete($oldImagePath);
        }

        return $relativeFilePath;
    }


    /**
     * Delete a file by its path
     */
    public function delete(string $imagePath): bool
    {
        if (strpos($imagePath, '/') === 0) {
            $fullPath = $_SERVER['DOCUMENT_ROOT'] . $imagePath;
        } else {
            $baseUploadPath = rtrim(config('upload.path', 'uploads'), '/');
            $fullPath = $baseUploadPath . '/' . $imagePath;
        }

        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        return false;
    }

    /**
     * Ensure directory exists (NEW METHOD)
     */
    public function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new Exception('Failed to create upload directory');
            }
        }
    }

    public function getAllowedMimeTypes(): array
    {
        return $this->allowedMimeTypes;
    }

    public function setAllowedMimeTypes(array $mimeTypes): self
    {
        $this->allowedMimeTypes = $mimeTypes;
        return $this;
    }

    public function getMaxFileSize(): int
    {
        return $this->maxFileSize;
    }

    public function setMaxFileSize(int $bytes): self
    {
        $this->maxFileSize = $bytes;
        return $this;
    }

    public function duplicate(string $originalPath): string
    {
        $fullOriginalPath = $this->getFullPath($originalPath);

        if (!file_exists($fullOriginalPath)) {
            throw new \Exception("Original file does not exist: {$originalPath}");
        }

        // Get file info
        $pathInfo = pathinfo($originalPath);
        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];
        $extension = $pathInfo['extension'] ?? '';

        // Generate new filename with unique suffix
        $newFilename = $filename . '-copy-' . uniqid();
        if ($extension) {
            $newFilename .= '.' . $extension;
        }

        $newPath = $directory . '/' . $newFilename;
        $fullNewPath = $this->getFullPath($newPath);

        // Ensure directory exists
        $this->ensureDirectoryExists(dirname($fullNewPath));

        // Copy the file
        if (!copy($fullOriginalPath, $fullNewPath)) {
            throw new \Exception("Failed to duplicate file: {$originalPath}");
        }

        return $newPath;
    }

    private function getFullPath(string $relativePath): string
    {
        // Absolute path to project root
        $projectRoot = realpath(__DIR__ . '/../'); // adjust depending on where this file lives

        // Upload folder inside src/
        $uploadPath = rtrim(config('upload.path', 'uploads'), '/');

        // Build full path under src/
        return $projectRoot . '/' . $uploadPath . '/' . ltrim($relativePath, '/');
    }

}