<?php

namespace App\Services;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\UploadedFile;
use App\Framework\Support\Collection;
use App\Framework\Support\Str;
use App\Framework\Validation\ValidationResult;
use App\Framework\Validation\Validator;
use App\Models\Image;
use App\Models\ImageCategory;
use App\Repositories\ImageRepository;
use Exception;

class ImageService
{
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml'
    ];

    private const THUMBNAIL_SIZES = [
        'small' => [150, 150],
        'medium' => [300, 300],
        'large' => [600, 600]
    ];

    private ImageRepository $imageRepository;
    private ImageUploadService $imageUploadService;

    private string $uploadPath;
    private string $publicPath;

    public function __construct(
        ImageRepository $imageRepository,
        ImageUploadService $imageUploadService
    ) {
        $this->imageRepository = $imageRepository;
        $this->imageUploadService = $imageUploadService;
        $this->uploadPath = rtrim(config('upload.path', 'uploads'), '/');
        $this->publicPath = rtrim(config('app.url', ''), '/') . '/uploads';
    }

    /**
     * @throws ValidationException
     */
    public function uploadImage(UploadedFile $file, array $metadata = []): Image
    {
        // Validate file
        $this->validateUploadedFile($file);

        // Use ImageUploadService to handle the upload
        $relativePath = $this->imageUploadService->uploadToPath(
            $file,
            'images/' . date('Y-m-d')
        );

        $fullPath = $this->uploadPath . '/' . $relativePath;

        // Get image dimensions
        $dimensions = $_ENV['APP_ENV'] === 'testing' ? null : $this->getImageDimensions($fullPath);

        // Create database record
        $imageData = [
            'filename' => basename($relativePath),
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $relativePath,
            'url' => $this->publicPath . '/' . $relativePath,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'width' => $dimensions['width'] ?? null,
            'height' => $dimensions['height'] ?? null,
            'alt_text' => $metadata['alt_text'] ?? null,
            'caption' => $metadata['caption'] ?? null,
            'description' => $metadata['description'] ?? null,
        ];

        $image = $this->imageRepository->create($imageData);

        // Generate thumbnails for images
        if ($_ENV['APP_ENV'] !== 'testing' && $this->isImage($image->mime_type)) {
            $this->generateThumbnails($image, $fullPath);
        }

        // Assign categories if provided
        if (!empty($metadata['categories'])) {
            $this->assignCategoriesToImage($image, $metadata['categories']);
        }

        return $image;
    }

    public function getImages(array $filters = []): array
    {
        $query = $filters['query'] ?? '';
        $mimeType = $filters['mime_type'] ?? null;
        $categoryId = $filters['category_id'] ?? null;
        $page = (int)($filters['page'] ?? 1);
        $perPage = (int)($filters['per_page'] ?? 20);
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        // Validate per_page limits
        $perPage = max(1, min($perPage, 100));

        return $this->imageRepository->searchImages(
            $query, $mimeType, $categoryId, $page, $perPage, $sortBy, $sortOrder
        );
    }

    public function getImage(int $id): ?Image
    {
        return $this->imageRepository->find($id);
    }

    public function updateImageMetadata(int $imageId, array $metadata): Image
    {
        $image = $this->imageRepository->find($imageId);
        if (!$image) {
            throw new Exception('Image not found');
        }

        // Update image
        $image->updateMetadata($metadata);

        // Update categories if provided
        if (!empty($metadata['categories'])) {
            $this->assignCategoriesToImage($image, $metadata['categories']);
        }

        return $image;
    }

    public function deleteImage(int $imageId, bool $hardDelete = false): bool
    {
        $image = $this->imageRepository->find($imageId);
        if (!$image) {
            throw new Exception('Image not found');
        }

        // Check if image is being used
        if ($image->isUsed() && $hardDelete) {
            throw new Exception('Cannot delete image that is currently in use');
        }

        if ($hardDelete) {
            // Delete physical file using ImageUploadService
            $this->imageUploadService->delete($image->file_path);

            // Delete thumbnails
            $this->deleteThumbnails($image);

            // Delete from database
            return $image->delete();
        } else {
            // Soft delete
            return $image->softDelete();
        }
    }

    public function bulkDeleteImages(array $imageIds, bool $hardDelete = false): array
    {
        $results = [
            'deleted' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($imageIds as $imageId) {
            try {
                if ($this->deleteImage($imageId, $hardDelete)) {
                    $results['deleted']++;
                } else {
                    $results['failed']++;
                }
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Image {$imageId}: " . $e->getMessage();
            }
        }

        return $results;
    }

    public function trackImageUsage(int $imageId, string $usableType, int $usableId, ?string $context = null): void
    {
        $image = $this->imageRepository->find($imageId);
        if (!$image) {
            throw new Exception('Image not found');
        }

        $image->addUsage($usableType, $usableId, $context);
    }

    public function removeImageUsage(int $imageId, string $usableType, int $usableId, ?string $context = null): void
    {
        $image = $this->imageRepository->find($imageId);
        if (!$image) {
            return; // Image might have been deleted
        }

        $image->removeUsage($usableType, $usableId, $context);
    }

    public function getImageStatistics(): array
    {
        return $this->imageRepository->getImageStatistics();
    }

    public function getRecentImages(int $limit = 10): Collection
    {
        return $this->imageRepository->getRecentImages($limit);
    }

    public function getUnusedImages(?int $olderThanDays = null): Collection
    {
        return $this->imageRepository->getUnusedImages($olderThanDays);
    }

    public function cleanupUnusedImages(int $olderThanDays = 30): array
    {
        $unusedImages = $this->getUnusedImages($olderThanDays);
        $results = [
            'deleted' => 0,
            'failed' => 0,
            'freed_space' => 0
        ];

        foreach ($unusedImages as $image) {
            try {
                $results['freed_space'] += $image->file_size;
                if ($this->deleteImage($image->id, true)) {
                    $results['deleted']++;
                }
            } catch (Exception $e) {
                $results['failed']++;
            }
        }

        return $results;
    }

    public function createCategory(array $data): ImageCategory
    {
        // Generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name'], [$this, 'getImageCategoryBySlug']);
        }

        return ImageCategory::create($data);
    }

    public function getImageCategoryBySlug(string $slug): ?ImageCategory
    {
        return ImageCategory::where('slug', $slug)->first();
    }

    public function getCategories(): Collection
    {
        return ImageCategory::active()->orderBy('name')->get();
    }

    private function validateUploadedFile(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new ValidationException('Invalid file upload');
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            $maxSizeMB = self::MAX_FILE_SIZE / (1024 * 1024);
            throw new ValidationException("File size exceeds maximum allowed size of {$maxSizeMB}MB");
        }

        if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES)) {
            throw new ValidationException('File type not allowed');
        }
    }

    private function getImageDimensions(string $filePath): array
    {
        if (!$this->isImage(mime_content_type($filePath))) {
            return [];
        }

        $imageSize = getimagesize($filePath);
        if ($imageSize === false) {
            return [];
        }

        return [
            'width' => $imageSize[0],
            'height' => $imageSize[1]
        ];
    }

    private function isImage(string $mimeType): bool
    {
        return strpos($mimeType, 'image/') === 0 && $mimeType !== 'image/svg+xml';
    }

    private function generateThumbnails(Image $image, string $originalPath): void
    {
        if (!extension_loaded('gd')) {
            return; // Skip thumbnail generation if GD extension is not available
        }

        foreach (self::THUMBNAIL_SIZES as $size => [$width, $height]) {
            try {
                $thumbnailPath = $this->generateThumbnailPath($image->file_path, $size);
                $fullThumbnailPath = $this->uploadPath . '/' . $thumbnailPath;

                $this->imageUploadService->ensureDirectoryExists(dirname($fullThumbnailPath));
                $this->createThumbnail($originalPath, $fullThumbnailPath, $width, $height);
            } catch (Exception $e) {
                // Log thumbnail generation error but don't fail the upload
                error_log("Failed to generate {$size} thumbnail for image {$image->id}: " . $e->getMessage());
            }
        }
    }

    private function generateThumbnailPath(string $originalPath, string $size): string
    {
        $pathInfo = pathinfo($originalPath);
        return $pathInfo['dirname'] . '/thumbs/' . $size . '/' . $pathInfo['basename'];
    }

    private function createThumbnail(string $sourcePath, string $destPath, int $width, int $height): void
    {
        $sourceInfo = getimagesize($sourcePath);
        if (!$sourceInfo) {
            throw new Exception('Cannot read source image');
        }

        [$sourceWidth, $sourceHeight, $sourceType] = $sourceInfo;

        // Create source image resource
        switch ($sourceType) {
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $sourceImage = imagecreatefromgif($sourcePath);
                break;
            case IMAGETYPE_WEBP:
                $sourceImage = imagecreatefromwebp($sourcePath);
                break;
            default:
                throw new Exception('Unsupported image type for thumbnail generation');
        }

        // Calculate dimensions maintaining aspect ratio
        $aspectRatio = $sourceWidth / $sourceHeight;
        if ($width / $height > $aspectRatio) {
            $width = $height * $aspectRatio;
        } else {
            $height = $width / $aspectRatio;
        }

        // Create thumbnail
        $thumbnail = imagecreatetruecolor($width, $height);

        // Preserve transparency for PNG and GIF
        if ($sourceType == IMAGETYPE_PNG || $sourceType == IMAGETYPE_GIF) {
            imagecolortransparent($thumbnail, imagecolorallocatealpha($thumbnail, 0, 0, 0, 127));
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
        }

        imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

        // Save thumbnail
        switch ($sourceType) {
            case IMAGETYPE_JPEG:
                imagejpeg($thumbnail, $destPath, 85);
                break;
            case IMAGETYPE_PNG:
                imagepng($thumbnail, $destPath);
                break;
            case IMAGETYPE_GIF:
                imagegif($thumbnail, $destPath);
                break;
            case IMAGETYPE_WEBP:
                imagewebp($thumbnail, $destPath, 85);
                break;
        }

        imagedestroy($sourceImage);
        imagedestroy($thumbnail);
    }

    private function deleteThumbnails(Image $image): void
    {
        foreach (array_keys(self::THUMBNAIL_SIZES) as $size) {
            $thumbnailPath = $this->generateThumbnailPath($image->file_path, $size);
            $this->imageUploadService->delete($thumbnailPath);
        }
    }

    private function assignCategoriesToImage(Image $image, array $categoryIds): void
    {
        // Validate category IDs exist
        $validCategories = ImageCategory::active()->whereIn('id', $categoryIds)->get();
        $validIds = $validCategories->pluck('id')->toArray();

        // Sync categories (this will replace existing ones)
        $image->categories()->sync($validIds);
    }
}