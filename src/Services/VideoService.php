<?php

namespace App\Services;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\UploadedFile;
use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\Video;
use App\Repositories\VideoRepository;
use App\Search\PaginatedResult;
use App\Search\SearchCriteria;
use Exception;

class VideoService
{
    private const MAX_FILE_SIZE = 100 * 1024 * 1024; // 100MB
    private const ALLOWED_MIME_TYPES = [
        'video/mp4',
        'video/quicktime',
        'video/x-msvideo',
        'video/mpeg',
        'video/webm',  // Add WebM
        'application/pdf',  // Add PDF
        'application/zip',  // Add ZIP
        'application/x-zip-compressed'  // Alternative ZIP mime type
    ];

    private VideoRepository $videoRepository;
    private VideoUploadService $videoUploadService;
    private string $uploadPath;
    private string $publicPath;

    public function __construct(
        VideoRepository $videoRepository,
        VideoUploadService $videoUploadService,
        ?string $appUrl = null,
    ) {
        $this->videoRepository = $videoRepository;
        $this->videoUploadService = $videoUploadService;
        $this->uploadPath = rtrim(config('upload.path', 'uploads'), '/');
        $appUrl = $appUrl ?? rtrim(config('app.url', ''), '/');
        $this->publicPath = $appUrl . '/uploads';
    }

    /**
     * Upload video file
     */
    public function uploadVideo(UploadedFile $file, array $metadata = []): Model
    {
        // Validate file
        $this->validateUploadedFile($file);

        $mimeType = $file->getMimeType();
        $isVideo = str_starts_with($mimeType, 'video/');

        // Upload video and generate thumbnails
        $uploadResult = $this->videoUploadService->upload($file);

        $thumbnails = [];
        if ($isVideo && !empty($uploadResult['thumbnails'])) {
            $thumbnails = collect($uploadResult['thumbnails'])->map(function ($thumbnail) {
                return $this->publicPath . str_replace('/uploads', '', $thumbnail);
            })->toArray();
        }

        // Create database record
        $videoData = [
            'filename' => $uploadResult['filename'],
            'original_name' => $file->getClientOriginalName(),
            'file_path' => 'videos/' . $uploadResult['path'],
            'url' => $this->publicPath . '/videos/' . $uploadResult['path'],
            'mime_type' => $mimeType,
            'file_size' => $uploadResult['size'],
            'duration' => $isVideo ? $uploadResult['duration'] : 0,
            'width' => $isVideo ? $uploadResult['width'] : null,
            'height' => $isVideo ? $uploadResult['height'] : null,
            'thumbnails' => json_encode($thumbnails),
            'title' => $metadata['title'] ?? null,
            'description' => $metadata['description'] ?? null,
        ];

        return $this->videoRepository->create($videoData);
    }

    /**
     * Get videos with filters
     */
    public function getVideos(array $filters = []): PaginatedResult
    {
        $criteria = new SearchCriteria(
            filters: array_filter([
                'mime_type' => $filters['mime_type'] ?? null,
            ]),
            sortBy: $filters['sort_by'] ?? 'created_at',
            sortOrder: $filters['sort_order'] ?? 'desc',
            page: (int)($filters['page'] ?? 1),
            perPage: max(1, min((int)($filters['per_page'] ?? 20), 100)),
            searchQuery: $filters['query'] ?? ''
        );

        return $this->videoRepository->search($criteria);
    }

    /**
     * Get single video
     */
    public function getVideo(int $id): ?Video
    {
        return $this->videoRepository->find($id);
    }

    /**
     * Update video metadata
     */
    public function updateVideoMetadata(int $videoId, array $metadata): Video
    {
        $video = $this->videoRepository->find($videoId);
        if (!$video) {
            throw new Exception('Video not found');
        }

        $video->updateMetadata($metadata);
        return $video;
    }

    /**
     * Delete video
     */
    public function deleteVideo(int $videoId, bool $hardDelete = false): bool
    {
        $video = $this->videoRepository->find($videoId);
        if (!$video) {
            throw new Exception('Video not found');
        }

        // Check if video is being used
        if ($video->isUsed() && $hardDelete) {
            throw new Exception('Cannot delete video that is currently in use');
        }

        if ($hardDelete) {
            // Delete physical file and thumbnails
            $this->videoUploadService->delete($video->file_path);

            // Delete from database
            return $video->delete();
        } else {
            // Soft delete
            return $video->softDelete();
        }
    }

    /**
     * Track video usage
     */
    public function trackVideoUsage(int $videoId, string $usableType, int $usableId, ?string $context = null): void
    {
        $video = $this->videoRepository->find($videoId);
        if (!$video) {
            throw new Exception('Video not found');
        }

        $video->addUsage($usableType, $usableId, $context);
    }

    /**
     * Remove video usage
     */
    public function removeVideoUsage(int $videoId, string $usableType, int $usableId, ?string $context = null): void
    {
        $video = $this->videoRepository->find($videoId);
        if (!$video) {
            return;
        }

        $video->removeUsage($usableType, $usableId, $context);
    }

    /**
     * Get recent videos
     */
    public function getRecentVideos(int $limit = 10): Collection
    {
        return $this->videoRepository->getRecentVideos($limit);
    }

    /**
     * Duplicate video
     */
    public function duplicateVideo(int $videoId, array $metadata = []): Video
    {
        $originalVideo = $this->videoRepository->find($videoId);
        if (!$originalVideo) {
            throw new Exception('Video not found');
        }

        // Duplicate the physical file
        $duplicateResult = $this->videoUploadService->duplicate($originalVideo->file_path);

        // Create new database record
        $videoData = [
            'filename' => $duplicateResult['filename'],
            'original_name' => $metadata['original_name'] ?? $this->generateCopyName($originalVideo->original_name),
            'file_path' => 'videos/' . $duplicateResult['path'],
            'url' => $this->publicPath . '/videos/' . $duplicateResult['path'],
            'mime_type' => $originalVideo->mime_type,
            'file_size' => $duplicateResult['size'],
            'duration' => $duplicateResult['duration'],
            'width' => $duplicateResult['width'],
            'height' => $duplicateResult['height'],
            'thumbnails' => json_encode($duplicateResult['thumbnails']),
            'title' => $metadata['title'] ?? $this->generateCopyText($originalVideo->title),
            'description' => $metadata['description'] ?? $originalVideo->description,
        ];

        return $this->videoRepository->create($videoData);
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

        if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES) && !in_array($file->getFileInfo()['type'], self::ALLOWED_MIME_TYPES) ) {
            throw new ValidationException('File type not allowed. Only MP4, MOV, and AVI files are supported.');
        }
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
}