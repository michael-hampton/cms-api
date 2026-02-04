<?php

namespace App\Services\Product;

use App\Framework\Http\UploadedFile;
use App\Services\Cms\ImageUploadService;
use Exception;

class ProductImageUploadService
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp'
    ];

    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

    public function __construct(
        private readonly ImageUploadService $imageUploadService
    )
    {
        $this->imageUploadService
            ->setAllowedMimeTypes(self::ALLOWED_MIME_TYPES)
            ->setMaxFileSize(self::MAX_FILE_SIZE);
    }

    public function upload(UploadedFile $file, ?string $oldPath = null): string
    {
        if (!$file->isValid()) {
            throw new Exception('Invalid image file');
        }

        return $this->imageUploadService->uploadToPath(
            $file,
            'products/' . date('Y-m'),
            $oldPath
        );
    }

    public function delete(string $path): void
    {
        $this->imageUploadService->delete($path);
    }

    public function saveBase64Image(string $base64String): string
    {
        preg_match('/^data:image\/(\w+);base64,/', $base64String, $matches);
        $extension = $matches[1] ?? 'png';
        $imageData = substr($base64String, strpos($base64String, ',') + 1);
        $imageData = base64_decode($imageData);

        $filename = 'products/' . date('Y-m') . '/' . uniqid() . '.' . $extension;
        $fullPath = rtrim(config('upload.path', 'uploads'), '/') . '/' . $filename;

        $this->imageUploadService->ensureDirectoryExists(dirname($fullPath));
        file_put_contents($fullPath, $imageData);

        return $filename;
    }

    public function isBase64Image(string $string): bool
    {
        return (bool)preg_match('/^data:image\/(\w+);base64,/', $string);
    }
}