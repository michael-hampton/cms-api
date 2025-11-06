<?php

namespace App\Services;

use App\Framework\FileUpload\FileSystem;
use App\Framework\FileUpload\FileSystemInterface;
use App\Framework\Http\UploadedFile;
use Exception;

class ImageUploadService
{
    private string $uploadPath;
    private array $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private int $maxFileSize = 5242880; // 5MB
    private FileSystemInterface $fileSystem;


    public function __construct(
        string               $uploadPath = 'uploads/authors',
        ?FileSystemInterface $fileSystem = null
    )
    {
        $this->uploadPath = rtrim($uploadPath, '/');
        $this->fileSystem = $fileSystem ?? new FileSystem();
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

        $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $this->uploadPath;
        if (!$this->fileSystem->isDirectory($fullPath)) {
            $this->fileSystem->makeDirectory($fullPath, 0755, true);
        }

        $extension = $file->getClientOriginalExtension();
        $filename = uniqid('author_') . '_' . time() . '.' . $extension;
        $destination = $fullPath . '/' . $filename;

        if ($_ENV['APP_ENV'] !== 'testing' && !$file->moveTo($destination)) {
            throw new Exception('Failed to upload file.');
        }

        if ($oldImagePath && $this->fileSystem->fileExists($_SERVER['DOCUMENT_ROOT'] . $oldImagePath)) {
            $this->fileSystem->deleteFile($_SERVER['DOCUMENT_ROOT'] . $oldImagePath);
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

        if (!in_array($file->getMimeType(), $this->allowedMimeTypes)) {
            throw new Exception('Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.');
        }

        if ($file->getSize() > $this->maxFileSize) {
            throw new Exception('File size exceeds maximum allowed size of 5MB.');
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

        if ($_ENV['APP_ENV'] !== 'testing' && !$file->moveTo($destination)) {
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

        if ($this->fileSystem->fileExists($fullPath)) {
            return $this->fileSystem->deleteFile($fullPath);
        }
        return false;
    }

    /**
     * Ensure directory exists (NEW METHOD)
     */
    public function ensureDirectoryExists(string $directory): void
    {
        if (!$this->fileSystem->isDirectory($directory)) {
            if (!$this->fileSystem->makeDirectory($directory, 0755, true)) {
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

        if ($_ENV['APP_ENV'] === 'testing') {
            return $originalPath;
        }

        if (!$this->fileSystem->fileExists($fullOriginalPath)) {
            throw new Exception("Original file does not exist: {$originalPath}");
        }

        $pathInfo = $this->fileSystem->pathinfo($originalPath);

        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];
        $extension = $pathInfo['extension'] ?? '';

        $newFilename = $filename . '-copy-' . uniqid();
        if ($extension) {
            $newFilename .= '.' . $extension;
        }

        $newPath = $directory . '/' . $newFilename;
        $fullNewPath = $this->getFullPath($newPath);

        $this->ensureDirectoryExists(dirname($fullNewPath));

        if (!$this->fileSystem->copy($fullOriginalPath, $fullNewPath)) {
            throw new Exception("Failed to duplicate file: {$originalPath}");
        }

        return $newPath;
    }

    private function getFullPath(string $relativePath): string
    {
        $relativePath = parse_url($relativePath, PHP_URL_PATH);
        $projectRoot = $this->fileSystem->dirName(__DIR__);
        return $projectRoot . '/' . ltrim($relativePath, '/');
    }

}