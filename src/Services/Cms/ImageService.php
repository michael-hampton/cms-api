<?php

namespace App\Services\Cms;

use App\Enums\ImageRights;
use App\Enums\MimeType;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\UploadedFile;
use App\Framework\Support\Collection;
use App\Framework\Support\SiteContext;
use App\Framework\Support\Str;
use App\Models\Image;
use App\Models\ImageCategory;
use App\Models\Model;
use App\Models\Site;
use App\Repositories\Cms\ImageRepository;
use App\Search\PaginatedResult;
use App\Search\SearchCriteria;
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

        // Validate image_rights if provided
        if (!empty($metadata['image_rights'])) {
            try {
                ImageRights::from($metadata['image_rights']);
            } catch (\ValueError $e) {
                throw new ValidationException('Invalid image rights value');
            }
        }

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
            'name' => $metadata['name'] ?? $file->getClientOriginalName(),
            'credit' => $metadata['credit'] ?? null,
            'image_rights' => $metadata['image_rights'] ?? null,
            'file_path' => $relativePath,
            'url' => $this->publicPath . '/' . $relativePath,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'width' => $dimensions['width'] ?? null,
            'height' => $dimensions['height'] ?? null,
            'alt_text' => $metadata['alt_text'] ?? null,
            'caption' => $metadata['caption'] ?? null,
            'description' => $metadata['description'] ?? null,
            'site_id' => SiteContext::getId()
        ];

        $image = $this->imageRepository->create($imageData);

        $this->imageRepository->syncTags($image, $metadata['tags'] ?? []);

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

    public function getImages(array $filters = []): PaginatedResult
    {
        $criteria = new SearchCriteria(
            filters: array_filter([
                'mime_type' => $filters['mime_type'] ?? null,
                'category' => $filters['category_id'] ?? null,
            ]),
            sortBy: $filters['sort_by'] ?? 'created_at',
            sortOrder: $filters['sort_order'] ?? 'desc',
            page: (int)($filters['page'] ?? 1),
            perPage: max(1, min((int)($filters['per_page'] ?? 20), 100)),
            searchQuery: $filters['query'] ?? ''
        );

        return $this->imageRepository->search($criteria);
    }

    public function getImage(int $id): ?Image
    {
        return $this->imageRepository->getImage($id);
    }

    public function updateImageMetadata(int $imageId, array $metadata): Model
    {
        $image = $this->imageRepository->find($imageId);

        if (!$image) {
            throw new Exception('Image not found');
        }

        $imageRights = ImageRights::values();

        // Validate image_rights if provided
        if (!empty($metadata['image_rights']) && !in_array($metadata['image_rights'], $imageRights)) {
            throw new ValidationException('Invalid image rights value');
        }

        // Update image
        $this->imageRepository->update($imageId, $metadata);

        // Update categories if provided
        if (!empty($metadata['categories'])) {
            $this->assignCategoriesToImage($image, $metadata['categories']);
        }

        $this->imageRepository->syncTags($image, $metadata['tags'] ?? []);

        return $image->fresh();
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

    public function getCategories(string $siteName): Collection
    {
        $site = Site::resolveSite($siteName);
        return ImageCategory::active()
            ->where('site_id', $site)
            ->orderBy('name')
            ->get();
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

        if (!in_array($file->getMimeType(), MimeType::allowed())) {
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
        try {
            $mime = MimeType::from($mimeType);
            return $mime->isRasterImage();
        } catch (\ValueError) {
            return false;
        }
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

    private function createThumbnail(string $sourcePath, string $destPath, int $maxWidth, int $maxHeight): void
    {
        $sourceInfo = getimagesize($sourcePath);

        if ($sourceInfo === false) {
            throw new Exception('Cannot read source image');
        }

        [$sourceWidth, $sourceHeight, $sourceType] = $sourceInfo;

        $sourceImage = match ($sourceType) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG  => imagecreatefrompng($sourcePath),
            IMAGETYPE_GIF  => imagecreatefromgif($sourcePath),
            IMAGETYPE_WEBP => imagecreatefromwebp($sourcePath),
            default => throw new Exception('Unsupported image type for thumbnail generation'),
        };

        if (!$sourceImage) {
            throw new Exception('Could not create source image');
        }

        $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);

        $thumbWidth = max(1, (int) round($sourceWidth * $ratio));
        $thumbHeight = max(1, (int) round($sourceHeight * $ratio));

        $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);

        if (!$thumbnail) {
            throw new Exception('Could not create thumbnail image');
        }

        if ($sourceType === IMAGETYPE_JPEG) {
            $white = imagecolorallocate($thumbnail, 255, 255, 255);
            imagefilledrectangle($thumbnail, 0, 0, $thumbWidth, $thumbHeight, $white);
        } else {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);

            $transparent = imagecolorallocatealpha($thumbnail, 0, 0, 0, 127);
            imagefilledrectangle($thumbnail, 0, 0, $thumbWidth, $thumbHeight, $transparent);

            imagealphablending($thumbnail, true);
        }

        $resampled = imagecopyresampled(
            $thumbnail,
            $sourceImage,
            0,
            0,
            0,
            0,
            $thumbWidth,
            $thumbHeight,
            $sourceWidth,
            $sourceHeight
        );

        if (!$resampled) {
            throw new Exception('Failed to resize image');
        }

        $saved = match ($sourceType) {
            IMAGETYPE_JPEG => imagejpeg($thumbnail, $destPath, 85),
            IMAGETYPE_PNG  => imagepng($thumbnail, $destPath),
            IMAGETYPE_GIF  => imagegif($thumbnail, $destPath),
            IMAGETYPE_WEBP => imagewebp($thumbnail, $destPath, 85),
            default => false,
        };

        if (!$saved) {
            throw new Exception('Failed to save thumbnail');
        }
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

        // Use the repository to sync categories instead of calling on the model
        // This ensures we're working with the relationship properly
        $this->imageRepository->syncCategories($image, $validIds);
    }

    /**
     * Duplicate an existing image
     *
     * @throws Exception
     */
    public function duplicateImage(int $imageId, array $metadata = []): Image
    {
        $originalImage = $this->imageRepository->find($imageId);
        if (!$originalImage) {
            throw new Exception('Image not found');
        }

        // Duplicate the physical file
        $newFilePath = $this->imageUploadService->duplicate($originalImage->file_path);

        // Create new database record
        $imageData = [
            'filename' => basename($newFilePath),
            'original_name' => $metadata['original_name'] ?? $this->generateCopyName($originalImage->original_name),
            'name' => $metadata['name'] ?? ($originalImage->name ? $originalImage->name . ' (copy)' : basename($newFilePath)),
            'file_path' => $newFilePath,
            'url' => $this->publicPath . '/' . $newFilePath,
            'mime_type' => $originalImage->mime_type,
            'file_size' => $originalImage->file_size,
            'width' => $originalImage->width,
            'height' => $originalImage->height,
            'alt_text' => $metadata['alt_text'] ?? $this->generateCopyText($originalImage->alt_text),
            'caption' => $metadata['caption'] ?? $this->generateCopyText($originalImage->caption),
            'description' => $metadata['description'] ?? $originalImage->description,
        ];

        $newImage = $this->imageRepository->create($imageData);

        if (!isset($metadata['tags'])) {
            $originalTags = $this->imageRepository->getTagsForImage($originalImage);
            if ($originalTags->count() > 0) {
                $tagIds = $originalTags->map(function ($tag) {
                    return $tag->tag_id;
                })->toArray();
                $this->imageRepository->syncTags($newImage, $tagIds);
            }
        } else {
            $this->imageRepository->syncTags($newImage, $metadata['tags']);
        }

        // Copy thumbnails if they exist
        if ($_ENV['APP_ENV'] !== 'testing' && $this->isImage($originalImage->mime_type)) {
            $this->duplicateThumbnails($originalImage, $newImage);
        }

        // Copy categories if not overridden
        if (!empty($metadata['categories'])) {
            $this->imageRepository->syncCategories($newImage, $metadata['categories']);
        } else {
            $originalCategories = $this->imageRepository->getCategoriesForImage($originalImage);
            if ($originalCategories->count() > 0) {
                $categoryIds = $originalCategories->pluck('id')->toArray();
                $this->imageRepository->syncCategories($newImage, $categoryIds);
            }
        }

        return $newImage;
    }

    private function generateCopyName(string $originalName): string
    {
        $pathInfo = pathinfo($originalName);
        $basename = $pathInfo['filename'];
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';

        return $basename . '-copy' . $extension;
    }

    private function generateCopyText(?string $originalText): ?string
    {
        if ($originalText === null) {
            return null;
        }

        return $originalText . ' (copy)';
    }

    private function duplicateThumbnails(Image $originalImage, Image $newImage): void
    {
        foreach (array_keys(self::THUMBNAIL_SIZES) as $size) {
            try {
                $originalThumbPath = $this->generateThumbnailPath($originalImage->file_path, $size);
                $newThumbPath = $this->generateThumbnailPath($newImage->file_path, $size);

                $fullOriginalPath = $this->uploadPath . '/' . $originalThumbPath;
                $fullNewPath = $this->uploadPath . '/' . $newThumbPath;

                if (file_exists($fullOriginalPath)) {
                    $this->imageUploadService->ensureDirectoryExists(dirname($fullNewPath));
                    copy($fullOriginalPath, $fullNewPath);
                }
            } catch (Exception $e) {
                error_log("Failed to duplicate {$size} thumbnail: " . $e->getMessage());
            }
        }
    }

    public function archiveImage(int $imageId): bool
    {
        $image = $this->imageRepository->find($imageId);
        if (!$image) {
            throw new Exception('Image not found');
        }

        return $this->imageRepository->update($imageId, ['is_archived' => true]) !== null;
    }

    public function unarchiveImage(int $imageId): bool
    {
        $image = $this->imageRepository->find($imageId);
        if (!$image) {
            throw new Exception('Image not found');
        }

        return $this->imageRepository->update($imageId, ['is_archived' => false]) !== null;
    }

    public function bulkArchiveImages(array $imageIds): array
    {
        $results = [
            'archived' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($imageIds as $imageId) {
            try {
                if ($this->archiveImage($imageId)) {
                    $results['archived']++;
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
}