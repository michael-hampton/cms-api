<?php

namespace App\Services\Subscriptions;

use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Framework\FileUpload\FileUpload;
use App\Framework\Http\UploadedFile;
use App\Models\IssueDelivery;
use App\Models\Model;
use App\Repositories\Subscriptions\IssueDeliveryRepository;

class IssueDeliveryService
{
    /** Relative path (from app root) where issue cover images are stored. */
    private const COVER_IMAGE_DIR = 'storage/uploads/issue-covers';

    /** Allowed extensions for cover images. */
    private const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /** 8 MB upload limit. */
    private const MAX_IMAGE_SIZE = 8 * 1024 * 1024;

    public function __construct(
        private readonly IssueDeliveryRepository $scheduleRepository,
    ) {}

    // =========================================================================
    // Status management
    // =========================================================================

    public function activateSchedule(int $scheduleId): ?Model
    {
        return $this->updateScheduleStatus($scheduleId, IssueScheduleStatus::ACTIVE);
    }

    public function cancelSchedule(int $scheduleId): ?Model
    {
        return $this->updateScheduleStatus($scheduleId, IssueScheduleStatus::CANCELLED);
    }

    public function updateScheduleStatus(int $scheduleId, IssueScheduleStatus $status): ?Model
    {
        return $this->scheduleRepository->update($scheduleId, [
            'status' => $status->value,
        ]);
    }

    // =========================================================================
    // Cover image management
    // =========================================================================

    /**
     * Store a new cover image for an issue delivery and return the public URL.
     *
     * @param  array $file  The raw $_FILES array entry for the uploaded file.
     * @return string       The stored file path / public URL.
     *
     * @throws \Exception   On validation failure or storage error.
     */
    public function storeCoverImage(UploadedFile $file): string
    {
        $upload = new FileUpload($file, self::COVER_IMAGE_DIR);
        $upload->setAllowedExtensions(self::ALLOWED_IMAGE_EXTENSIONS);
        $upload->setMaxSize(self::MAX_IMAGE_SIZE);

        return $upload->store();
    }

    /**
     * Replace the cover image on an existing issue delivery.
     * Deletes the previous image (if any) before storing the new one.
     *
     * @param  IssueDelivery $issue
     * @param  array         $file   Raw $_FILES entry for the new image.
     * @return string                The new stored file path / public URL.
     *
     * @throws \Exception
     */
    public function replaceCoverImage(IssueDelivery $issue, UploadedFile $file): string
    {
        $this->deleteCoverImageFile($issue->cover_image);

        return $this->storeCoverImage($file);
    }

    /**
     * Remove the cover image from an issue delivery (model + disk).
     */
    public function removeCoverImage(IssueDelivery $issue): void
    {
        if (!$issue->cover_image) {
            return;
        }

        $this->deleteCoverImageFile($issue->cover_image);

        $this->scheduleRepository->update($issue->id, ['cover_image' => null]);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function deleteCoverImageFile(?string $path): void
    {
        if ($path && file_exists($path)) {
            @unlink($path);
        }
    }
}